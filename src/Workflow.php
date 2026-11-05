<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments;

/**
 * Work documents on the Workflow kanban board, grouped by state.
 */
class Workflow extends \Hubleto\App\Community\Workflow\Workflow
{
  public function loadItems(int $idWorkflow, array $filters): array
  {
    /** @var HubletoOverrides\Document */
    $mDocument = $this->getModel(\Hubleto\App\Community\Documents\Models\Document::class);
    $query = $mDocument->record->prepareReadQuery()
      ->where('documents.id_workflow', $idWorkflow)
    ;

    // The board's user buttons: documents of that author.
    $idOwner = (int) ($filters['fOwner'] ?? 0);
    if ($idOwner > 0) {
      $query = $query->where('documents.id_created_by', $idOwner);
    }

    $documents = $query->get()->toArray();

    $items = [];
    foreach ($documents as $document) {
      $document['_WORKFLOW_ITEM_TITLE'] = $document['name'];
      $document['_WORKFLOW_ITEM_SUBTITLE'] = $document['virt_ohs_customer'] ?? '';
      $document['_DETAIL_URL'] = 'ohs/documents/documents/' . $document['id'];
      $document['_DETAIL_VIEW'] = '@Hubleto:App:Community:Workflow/WorkflowItemDetail.twig';
      $items[] = $document;
    }

    return $items;
  }
}
