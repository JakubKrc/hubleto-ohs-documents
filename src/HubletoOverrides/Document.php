<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments\HubletoOverrides;

use Hubleto\App\Community\Documents\Models\Document as CommunityDocument;
use Hubleto\App\Community\Workflow\Models\Workflow;
use Hubleto\App\External\WaiBlue\OhsCore\Db\Column\StoredFile;
use Hubleto\App\External\WaiBlue\OhsCore\Services\ProtectedFile;
use Hubleto\App\External\WaiBlue\OhsDocuments\Services\DocumentColumns;
use Hubleto\App\External\WaiBlue\OhsDocuments\Services\DocumentLinks;
use Hubleto\Framework\Db\Column\Virtual;
use Hubleto\Framework\Exceptions\RecordSaveException;

/**
 * Hubleto's document as the work document of a client, a branch or an audit (registered in the OhsDocuments loader).
 * It is linked by Hubleto's model + record_id; priority and file are custom columns (Services/DocumentColumns).
 * Documents of other apps keep Hubleto's behaviour.
 */
class Document extends CommunityDocument
{
  use \Hubleto\App\External\WaiBlue\OhsCore\Models\Traits\BehavesAsCommunityModel;
  use \Hubleto\App\External\WaiBlue\OhsCore\Models\Traits\UsesCommunityCustomColumns;

  const WORKFLOW_GROUP = 'ohs_documents';

  public string $recordManagerClass = DocumentRecordManager::class;

  /** @var string File of the document being deleted; removed after the record is gone. */
  private string $fileOfDeletedDocument = '';

  public static function register(): void
  {
    \Hubleto\Framework\DependencyInjection::setServiceProvider(CommunityDocument::class, self::class);
  }

  public function __construct()
  {
    parent::__construct();

    $this->behaveAsCommunityModel(CommunityDocument::class, 'hubleto-app-community-documents-loader');

    // Hubleto describes the columns in its constructor, before the name is reset; custom columns depend on it.
    $this->columns = $this->describeColumns();
  }

  public function describeColumns(): array
  {
    $columns = $this->addCommunityCustomColumns(parent::describeColumns(), CommunityDocument::class);
    /** @var DocumentLinks */
    $documentLinks = $this->getService(DocumentLinks::class);

    // The file is downloaded through a checked route: the upload folder is closed (health data).
    $columns['ohs_file'] = (new StoredFile($this, $this->translate('Document')))
      ->setDownloadUrl('ohs/documents/documents/{%ID%}/download')
      ->setFolderPath('ohs/documents')
      ->setRenamePattern('{%FILENAME_ASCII%}__{%Y%}{%M%}{%D%}_{%H%}{%I%}{%S%}.{%EXT%}')
      ->setDefaultHidden()
    ;

    $columns['virt_ohs_customer'] = (new Virtual($this, $this->translate('Client')))
      ->setProperty('sql', $documentLinks->getCustomerNameSql())
      ->setDefaultVisible()
    ;
    $columns['virt_ohs_linked_record'] = (new Virtual($this, $this->translate('Belongs to')))
      ->setProperty('sql', $documentLinks->getLinkedRecordTextSql())
      ->setDefaultVisible()
    ;

    // The author is shown, not chosen: it stays as when the document was created (both legacy apps).
    if (isset($columns['id_created_by'])) $columns['id_created_by']->setReadonly();
    if (isset($columns['id_workflow_step'])) $columns['id_workflow_step']->setTitle($this->translate('State'));

    return $columns;
  }

  public function describeTable(): \Hubleto\Framework\Description\Table
  {
    $description = parent::describeTable();

    $isWorkDocumentsList = $this->router()->urlParamAsBool('ohsDocuments') || $this->router()->urlParamAsInteger('linkedRecordId') > 0;
    if ($isWorkDocumentsList) {
      $description->ui['addButtonText'] = $this->translate('Add document');
      $description->ui['orderBy'] = ['field' => 'created_on', 'direction' => 'desc'];
      $description->hideColumns(['uid', 'model', 'record_id', 'id_workflow', 'ohs_file']);

      $description->addFilter('fOrderWorkflowStep', Workflow::buildTableFilterForWorkflowSteps($this, $this->translate('State')));
      $description->addFilter(DocumentRecordManager::PRIORITY_FILTER, [
        'title' => $this->translate('Priority'),
        'options' => [
          DocumentRecordManager::PRIORITY_FILTER_ALL => $this->translate('All'),
          DocumentColumns::PRIORITY_HIGH => '1',
          DocumentColumns::PRIORITY_MEDIUM => '2',
          DocumentColumns::PRIORITY_LOW => '3',
        ],
      ]);
    }

    // The main list has no "Add": a document is added in the tab of its client, branch or audit (both legacy apps).
    if ($this->router()->urlParamAsBool('ohsDocuments')) {
      $description->permissions['canCreate'] = false;
    }

    return $description;
  }

  public function describeForm(): \Hubleto\Framework\Description\Form
  {
    $description = parent::describeForm();
    $description->defaultValues['ohs_priority'] = DocumentColumns::PRIORITY_LOW;
    $description->defaultValues['id_created_by'] = $this->authProvider()->getUserId();
    return $description;
  }

  public function getRelationsIncludedInLoadTableData(): array|null
  {
    return ['CREATED_BY', 'WORKFLOW_STEP'];
  }

  public function getMaxReadLevelForLoadTableData(): int
  {
    return 1;
  }

  public function onBeforeCreate(array $record): array
  {
    $record = parent::onBeforeCreate($record);
    $record = $this->keepStoredFile($record, 'ohs_file', null);

    // None when the document is created on the command line (demo data).
    $idUser = (int) $this->authProvider()->getUserId();
    $record['id_created_by'] = $idUser > 0 ? $idUser : null;
    $record['created_on'] = date('Y-m-d H:i:s');

    /** @var DocumentLinks */
    $documentLinks = $this->getService(DocumentLinks::class);
    $modelName = $documentLinks->normalizeModelName((string) ($record['model'] ?? ''));
    $record['model'] = $modelName == '' ? null : $modelName;
    if (!$documentLinks->isLinkedModel($modelName)) return $record;

    if (!$documentLinks->linkedRecordExists($record['model'], (int) ($record['record_id'] ?? 0))) {
      throw new RecordSaveException($this->translate('The document must belong to a client, a branch or an audit.'));
    }
    $record['ohs_priority'] = (int) ($record['ohs_priority'] ?? DocumentColumns::PRIORITY_LOW);

    // New work documents start in the first step of the work document workflow.
    /** @var Workflow */
    $mWorkflow = $this->getModel(Workflow::class);
    return $mWorkflow->applyDefaultWorkflow($record, self::WORKFLOW_GROUP);
  }

  public function onBeforeUpdate(array $record): array
  {
    $record = parent::onBeforeUpdate($record);

    $originalDocument = $this->record->where('id', (int) $record['id'])->first();
    $record = $this->keepStoredFile($record, 'ohs_file', $originalDocument);

    // The link, the author and the creation date stay as when the document was created (both legacy apps).
    // Read from the array: on a stored record, "->model" is the record manager's own property, not the column.
    if ($originalDocument) {
      $storedValues = $originalDocument->toArray();
      foreach (['model', 'record_id', 'id_created_by', 'created_on'] as $column) {
        $record[$column] = $storedValues[$column] ?? null;
      }
      // A save that leaves out the priority would store 0 for it (Hubleto), so the stored value is used.
      if (!array_key_exists('ohs_priority', $record)) $record['ohs_priority'] = $storedValues['ohs_priority'] ?? null;
    }

    return $record;
  }

  /**
   * A saved file path can only stay as it is, a new file must be uploaded. Otherwise a crafted save could
   * point the document to another record's file and download it. $originalDocument is null for a new document.
   */
  private function keepStoredFile(array $record, string $column, mixed $originalDocument): array
  {
    if (!array_key_exists($column, $record)) return $record;

    $value = $record[$column];
    // Hubleto's File column stores the file name of an unchanged value as the path, so only a changed one is an upload.
    $isNewUpload = is_array($value) && ($value['changed'] ?? false) === true;
    $isRemoved = $value === null || $value === '';
    if ($isNewUpload || $isRemoved) return $record;

    $originalPath = $originalDocument ? (string) $originalDocument->{$column} : '';
    $record[$column] = $originalPath == '' ? null : $originalPath;
    return $record;
  }

  public function onBeforeDelete(int $id): int
  {
    $id = parent::onBeforeDelete($id);
    $document = $this->record->where('id', $id)->first();
    $this->fileOfDeletedDocument = $document ? (string) $document->ohs_file : '';
    return $id;
  }

  // Deleting a document also deletes its file (both legacy apps).
  public function onAfterDelete(int $id): int
  {
    $id = parent::onAfterDelete($id);
    if ($this->fileOfDeletedDocument == '') return $id;

    $this->getService(ProtectedFile::class)->delete($this->fileOfDeletedDocument);
    $this->fileOfDeletedDocument = '';

    return $id;
  }
}
