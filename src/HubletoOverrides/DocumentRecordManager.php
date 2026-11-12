<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments\HubletoOverrides;

use Hubleto\App\External\WaiBlue\OhsCore\Models\RecordManagers\Traits\DefaultOrder;
use Hubleto\App\External\WaiBlue\OhsDocuments\Services\DocumentLinks;

/**
 * The filters of the Ohs document lists.
 */
class DocumentRecordManager extends \Hubleto\App\Community\Documents\Models\RecordManagers\Document
{
  use DefaultOrder;

  const PRIORITY_FILTER = 'fDocumentPriority';
  const PRIORITY_FILTER_ALL = 0;

  public function addUrlFiltersToQuery(mixed $query): mixed
  {
    $query = parent::addUrlFiltersToQuery($query);
    $router = \Hubleto\Erp\Loader::getGlobalApp()->router();

    // The work documents list shows only documents of clients, branches and audits.
    if ($router->urlParamAsBool('ohsDocuments')) {
      $query = $query->whereIn($this->table . '.model', DocumentLinks::LINKED_MODELS);
    }

    // The client, branch and audit tabs list only the documents linked to them directly (both legacy apps).
    $linkedModel = $this->documentLinks()->normalizeModelName($router->urlParamAsString('linkedModel'));
    $linkedRecordId = $router->urlParamAsInteger('linkedRecordId');
    if ($linkedModel != '' && $linkedRecordId > 0) {
      $query = $query->where($this->table . '.model', $linkedModel)->where($this->table . '.record_id', $linkedRecordId);
    }

    $filters = $router->urlParamAsArray('filters');
    $priority = (int) ($filters[self::PRIORITY_FILTER] ?? self::PRIORITY_FILTER_ALL);
    if ($priority != self::PRIORITY_FILTER_ALL) {
      $query = $query->where($this->table . '.ohs_priority', $priority);
    }

    return $query;
  }

  private function documentLinks(): DocumentLinks
  {
    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    return $hubleto->getService(DocumentLinks::class);
  }
}
