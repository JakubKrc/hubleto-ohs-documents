<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments;

use Hubleto\App\Community\Documents\Models\Document;
use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\OhsCore\Services\CalendarAuthor;
use Hubleto\App\External\WaiBlue\OhsDocuments\Services\DocumentLinks;

/**
 * Work documents in the Hubleto calendar, one all-day event on the creation date (as in the legacy calendar).
 * Events are derived from the documents, so there is no event table to keep in sync.
 */
class Calendar extends \Hubleto\App\Community\Calendar\Calendar
{
  // Legacy calendar colour of documents (yellow).
  const COLOR = '#e0a800';

  public string $translationContext = 'hubleto-app-external-waiblue-ohsdocuments-loader';
  public string $translationContextInner = 'Calendar';

  public function getCalendarConfig(): array
  {
    return [
      'position' => 7,
      'color' => self::COLOR,
      'title' => $this->translate('Work documents'),
      'icon' => 'fas fa-file-lines',
    ];
  }

  public function loadEvent(int $id): array
  {
    $document = $this->getModel(Document::class)->record->prepareReadQuery()
      ->whereIn('documents.model', DocumentLinks::LINKED_MODELS)
      ->where('documents.id', $id)
      ->first()
    ;
    if (!$document) return [];
    return $document->toArray();
  }

  public function loadEvents(string $dateStart, string $dateEnd, array $filter = []): array
  {
    // Only work documents; Hubleto's own documents are not in the legacy calendar.
    $query = $this->getModel(Document::class)->record->prepareReadQuery()
      ->whereIn('documents.model', DocumentLinks::LINKED_MODELS)
      ->where('documents.created_on', '>=', $dateStart)
      ->where('documents.created_on', '<=', $dateEnd)
    ;

    $idAuthor = $this->getService(CalendarAuthor::class)->getAuthorIdToShow($filter);
    if ($idAuthor > 0) {
      $query = $query->where('documents.id_created_by', $idAuthor);
    }

    $doneStepIds = $this->getModel(WorkflowStep::class)->record
      ->whereIn('tag', Loader::DONE_STEP_TAGS)
      ->pluck('id')
      ->toArray()
    ;

    // Hubleto's calendar filter "not done" is 1, "done" is 2.
    $completedFilter = (int) ($filter['fCompleted'] ?? 0);
    if ($completedFilter == 1) {
      $query = $query->where(function ($notDoneQuery) use ($doneStepIds) {
        $notDoneQuery->whereNotIn('documents.id_workflow_step', $doneStepIds)
          ->orWhereNull('documents.id_workflow_step');
      });
    }
    if ($completedFilter == 2) $query = $query->whereIn('documents.id_workflow_step', $doneStepIds);

    $documents = $query->get();

    $events = [];
    foreach ($documents as $document) {
      $isDone = in_array((int) $document->id_workflow_step, $doneStepIds);
      $events[] = [
        'id' => (int) $document->id,
        'start' => substr((string) $document->created_on, 0, 10),
        'allDay' => true,
        'title' => $this->translate('Document - {{ name }}', ['name' => $document->name]),
        'color' => self::COLOR,
        'source' => 'ohs-documents',
        'id_owner' => $document->id_created_by,
        'completed' => $isDone ? 1 : 0,
        'url' => 'ohs/documents/documents/' . $document->id,
      ];
    }

    return $events;
  }
}
