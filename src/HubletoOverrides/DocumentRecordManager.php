<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\HubletoOverrides;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Traits\DefaultOrder;
use Hubleto\App\External\WaiBlue\HlthcrDocuments\Services\DocumentLinks;

/**
 * Documents the user may see (Services/DocumentLinks), and the filters of the Hlthcr document lists.
 */
class DocumentRecordManager extends \Hubleto\App\Community\Documents\Models\RecordManagers\Document
{
  use DefaultOrder;

  const PRIORITY_FILTER = 'fDocumentPriority';
  const PRIORITY_FILTER_ALL = 0;

  public function prepareReadQuery(mixed $query = null, int $level = 0, array|null $includeRelations = null): mixed
  {
    $query = parent::prepareReadQuery($query, $level, $includeRelations);
    return $this->documentLinks()->restrictToVisibleDocuments($query);
  }

  public function prepareLookupQuery(string $search): mixed
  {
    $query = parent::prepareLookupQuery($search);
    return $this->documentLinks()->restrictToVisibleDocuments($query);
  }

  public function addUrlFiltersToQuery(mixed $query): mixed
  {
    $query = parent::addUrlFiltersToQuery($query);
    $router = \Hubleto\Erp\Loader::getGlobalApp()->router();

    // The work documents list shows only documents of clients, branches and audits.
    if ($router->urlParamAsBool('hlthcrDocuments')) {
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
      $query = $query->where($this->table . '.hlthcr_priority', $priority);
    }

    return $query;
  }

  private function documentLinks(): DocumentLinks
  {
    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    return $hubleto->getService(DocumentLinks::class);
  }
}
