<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments;

use Hubleto\App\Community\Customers\Models\Customer;
use Hubleto\App\Community\Documents\Models\Document;
use Hubleto\App\Community\Workflow\Models\Workflow as WorkflowModel;
use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\OhsBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\OhsCore\Services\DemoData;
use Hubleto\App\External\WaiBlue\OhsDocuments\Services\DocumentColumns;
use Hubleto\App\External\WaiBlue\OhsDocuments\Services\DocumentLinks;

class Loader extends \Hubleto\App\External\WaiBlue\OhsCore\OhsApp
{
  // Tags of the document workflow steps: a neutral list, because the two legacy apps have different states.
  const STEP_WAITING_FOR_INFORMATION = 'ohs-document-waiting-for-information';
  const STEP_WAITING_FOR_PROCESSING = 'ohs-document-waiting-for-processing';
  const STEP_IN_PROGRESS = 'ohs-document-in-progress';
  const STEP_RETURNED_WITH_COMMENTS = 'ohs-document-returned-with-comments';
  const STEP_DONE = 'ohs-document-done';
  const STEP_SENT_TO_CLIENT = 'ohs-document-sent-to-client';
  const STEP_INVOICED = 'ohs-document-invoiced';

  // A document in these steps is done (the calendar's "done" filter).
  const DONE_STEP_TAGS = [self::STEP_DONE, self::STEP_SENT_TO_CLIENT, self::STEP_INVOICED];

  // Apps are constructed before any model is used, so the replaced model applies everywhere.
  public function __construct()
  {
    parent::__construct();
    HubletoOverrides\Document::register();
  }

  public function init(): void
  {
    parent::init();

    // Work documents are Hubleto's documents; priority and file are their custom columns.
    $this->getService(DocumentColumns::class)->registerInDocumentModel();

    $this->router()->get([
      '/^ohs\/documents\/?$/' => Controllers\Home::class,
      '/^ohs\/documents\/settings\/?$/' => Controllers\Settings::class,
      '/^ohs\/documents\/documents\/(?<idDocument>\d+)\/download\/?$/' => Controllers\DownloadDocument::class,
    ]);
    $this->router()->crud('ohs/documents/documents', Controllers\Documents::class);

    $this->addSearchSwitch('d', 'documents');

    /** @var \Hubleto\App\Community\Calendar\Manager $calendarManager */
    $calendarManager = $this->getService(\Hubleto\App\Community\Calendar\Manager::class);
    $calendarManager->addCalendar($this, 'ohs-documents', Calendar::class);

    // One listener object for both events: it keeps the documents between them.
    $linkedRecordChanges = $this->getService(EventListeners\LinkedRecordChanges::class);
    $this->eventManager()->addEventListener('onModelBeforeDelete', $linkedRecordChanges);
    $this->eventManager()->addEventListener('onModelAfterDelete', $linkedRecordChanges);

    /** @var \Hubleto\App\Community\Workflow\Manager $workflowManager */
    $workflowManager = $this->getService(\Hubleto\App\Community\Workflow\Manager::class);
    $workflowManager->addWorkflowGroup($this, HubletoOverrides\Document::WORKFLOW_GROUP, Workflow::class);
  }

  public function installApp(int $round): void
  {
    if ($round == 1) {
      // The former table: its last migration moves the documents into Hubleto's table and drops it.
      $this->getModel(Models\Document::class)->upgradeSchema();
      // "php hubleto app install" runs only round 1, so foreign keys are created here too (Hubleto skips them later).
      $this->installForeignKeys();
      $this->getService(DocumentColumns::class)->installMissingColumns();
      $this->installDocumentWorkflow();
    }
  }

  /**
   * Creates the document workflow, or adds the steps an older install does not have yet.
   * The first step is the default for new documents.
   */
  private function installDocumentWorkflow(): void
  {
    /** @var WorkflowModel */
    $mWorkflow = $this->getModel(WorkflowModel::class);
    /** @var WorkflowStep */
    $mWorkflowStep = $this->getModel(WorkflowStep::class);

    $workflow = $mWorkflow->record->where('group', HubletoOverrides\Document::WORKFLOW_GROUP)->first();
    if ($workflow) {
      $idWorkflow = (int) $workflow->id;
    } else {
      $idWorkflow = (int) $mWorkflow->record->recordCreate([
        'name' => $this->translate('Work documents'),
        'show_in_kanban' => 1,
        'order' => 60,
        'group' => HubletoOverrides\Document::WORKFLOW_GROUP,
      ])['id'];
    }

    $steps = [
      ['name' => $this->translate('Waiting for information'), 'color' => '#9e9e9e', 'tag' => self::STEP_WAITING_FOR_INFORMATION],
      ['name' => $this->translate('Waiting for processing'), 'color' => '#b0bec5', 'tag' => self::STEP_WAITING_FOR_PROCESSING],
      ['name' => $this->translate('In progress'), 'color' => '#e27300', 'tag' => self::STEP_IN_PROGRESS],
      ['name' => $this->translate('Returned with comments'), 'color' => '#c62828', 'tag' => self::STEP_RETURNED_WITH_COMMENTS],
      ['name' => $this->translate('Done'), 'color' => '#4caf50', 'tag' => self::STEP_DONE],
      ['name' => $this->translate('Sent to the client'), 'color' => '#0062b1', 'tag' => self::STEP_SENT_TO_CLIENT],
      ['name' => $this->translate('Invoiced'), 'color' => '#5f5f5f', 'tag' => self::STEP_INVOICED],
    ];
    $order = 1;
    foreach ($steps as $step) {
      $existingStep = $mWorkflowStep->record->where('id_workflow', $idWorkflow)->where('tag', $step['tag'])->first();
      if ($existingStep) {
        $existingStep->update(['order' => $order]);
      } else {
        $mWorkflowStep->record->recordCreate([
          'id_workflow' => $idWorkflow,
          'name' => $step['name'],
          'color' => $step['color'],
          'tag' => $step['tag'],
          'order' => $order,
        ]);
      }
      $order++;
    }
  }

  public function search(array $expressions): array
  {
    /** @var HubletoOverrides\Document */
    $mDocument = $this->getModel(Document::class);
    $documentsQuery = $mDocument->record->prepareReadQuery()->whereIn('documents.model', DocumentLinks::LINKED_MODELS);
    foreach ($expressions as $expression) {
      $documentsQuery = $documentsQuery->where('documents.name', 'like', '%' . $expression . '%');
    }

    $results = [];
    foreach ($documentsQuery->limit(20)->get()->toArray() as $document) {
      $results[] = [
        'id' => $document['id'],
        'label' => $document['name'],
        'url' => 'ohs/documents/documents/' . $document['id'],
        'description' => $document['virt_ohs_customer'] ?? '',
      ];
    }
    return $results;
  }

  public function renderSecondSidebar(): string
  {
    return '
      ' . $this->secondSidebarTitle() . '
      <div class="app-sidebar-buttons">
        ' . $this->secondSidebarButton('ohs/documents/documents', 'fas fa-file-lines', $this->translate('Work documents')) . '
        ' . $this->secondSidebarButton('ohs/documents/settings', 'fas fa-cog', $this->translate('Settings')) . '
      </div>
    ';
  }

  public function renderAlerts(): string
  {
    return '';
  }

  // No number in the menu: neither legacy app shows one.
  public function getSidebarBadgeNumber(): int
  {
    return 0;
  }

  /**
   * Demo documents of the demo clients (FAKE data, no real files).
   * Records are inserted directly: the model rules use the logged-in user, and there is none on the command line.
   */
  public function generateOhsDemoData(): void
  {
    $demoCompanyIds = $this->getService(DemoData::class)->getDemoCompanyIds();
    $demoCustomers = $this->getModel(Customer::class)->record->whereIn('company_id', $demoCompanyIds)->get();

    foreach ($demoCustomers as $demoCustomer) {
      $customerHasDocuments = $this->getModel(Document::class)->record
        ->where('model', DocumentLinks::CUSTOMER_MODEL)
        ->where('record_id', $demoCustomer->id)
        ->exists()
      ;
      if ($customerHasDocuments) continue;

      $clientDocuments = [
        ['Posudok o riziku', DocumentColumns::PRIORITY_HIGH, '-20 days', self::STEP_IN_PROGRESS],
        ['Kategorizácia prác', DocumentColumns::PRIORITY_MEDIUM, '-5 days', self::STEP_WAITING_FOR_INFORMATION],
        ['Hodnotenie zdravotných rizík', DocumentColumns::PRIORITY_MEDIUM, '-2 days', self::STEP_WAITING_FOR_PROCESSING],
        ['Program ochrany zdravia pri práci', DocumentColumns::PRIORITY_HIGH, '-30 days', self::STEP_RETURNED_WITH_COMMENTS],
      ];
      foreach ($clientDocuments as $clientDocument) {
        [$name, $priority, $createdAgo, $stepTag] = $clientDocument;
        $this->createDemoDocument([
          'name' => $name,
          'model' => DocumentLinks::CUSTOMER_MODEL,
          'record_id' => $demoCustomer->id,
          'ohs_priority' => $priority,
          'id_created_by' => $demoCustomer->id_owner,
          'created_on' => date('Y-m-d H:i:s', strtotime($createdAgo)),
        ], $stepTag);
      }

      $firstBranch = $this->getModel(Branch::class)->record->where('id_customer', $demoCustomer->id)->orderBy('id')->first();
      if ($firstBranch) {
        $this->createDemoDocument([
          'name' => 'Prevádzkový poriadok prevádzky',
          'model' => DocumentLinks::BRANCH_MODEL,
          'record_id' => $firstBranch->id,
          'ohs_priority' => DocumentColumns::PRIORITY_LOW,
          'id_created_by' => $demoCustomer->id_owner,
          'created_on' => date('Y-m-d H:i:s', strtotime('-60 days')),
        ], self::STEP_DONE);
      }
    }
  }

  private function createDemoDocument(array $document, string $stepTag): void
  {
    $workflowStep = $this->getModel(WorkflowStep::class)->record->where('tag', $stepTag)->first();

    $document['uid'] = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $document['id_workflow'] = $workflowStep ? $workflowStep->id_workflow : null;
    $document['id_workflow_step'] = $workflowStep ? $workflowStep->id : null;

    $this->getModel(Document::class)->record->create($document);
  }
}
