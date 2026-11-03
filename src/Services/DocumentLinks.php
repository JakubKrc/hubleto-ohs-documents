<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Services;

use Hubleto\App\External\WaiBlue\HlthcrBranches\Services\BranchScope;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\AccessScope;

/**
 * A work document belongs to a client, a branch or an audit through Hubleto's "model" + "record_id"
 * (both legacy apps link documents the same way, with lookup_model + lookup_id).
 */
class DocumentLinks extends \Hubleto\Erp\Core
{
  const CUSTOMER_MODEL = 'Hubleto\\App\\Community\\Customers\\Models\\Customer';
  const BRANCH_MODEL = 'Hubleto\\App\\External\\WaiBlue\\HlthcrBranches\\Models\\Branch';
  const AUDIT_MODEL = 'Hubleto\\App\\External\\WaiBlue\\HlthcrAudits\\Models\\Audit';

  const LINKED_MODELS = [self::CUSTOMER_MODEL, self::BRANCH_MODEL, self::AUDIT_MODEL];

  // The forms send model names with slashes ("Hubleto/App/..."); the table keeps them with backslashes.
  public function normalizeModelName(string $modelName): string
  {
    return trim(str_replace('/', '\\', $modelName), '\\');
  }

  public function isLinkedModel(string $modelName): bool
  {
    return in_array($this->normalizeModelName($modelName), self::LINKED_MODELS);
  }

  // The same visibility rules as for reading the linked record.
  public function isLinkedRecordVisible(string $modelName, int $recordId): bool
  {
    $modelName = $this->normalizeModelName($modelName);
    if (!$this->isLinkedModel($modelName) || $recordId <= 0) return false;

    $linkedModel = $this->getModel($modelName);
    return $linkedModel->record->prepareReadQuery()->where($linkedModel->table . '.id', $recordId)->exists();
  }

  // Id and file of every document of a client, a branch or an audit.
  public function getDocumentsOfRecord(string $modelName, int $recordId): array
  {
    return $this->db()->fetchAll(
      'SELECT id, hlthcr_file FROM documents WHERE model = ? AND record_id = ?',
      [$this->normalizeModelName($modelName), $recordId]
    );
  }

  /**
   * The client of a document of the "documents" table: the client itself, the client of the branch or of the audit's branch.
   */
  public function getCustomerIdSql(): string
  {
    return 'case documents.model
      when ' . $this->quote(self::CUSTOMER_MODEL) . ' then documents.record_id
      when ' . $this->quote(self::BRANCH_MODEL) . ' then (select hlthcr_branches.id_customer from hlthcr_branches where hlthcr_branches.id = documents.record_id)
      when ' . $this->quote(self::AUDIT_MODEL) . ' then (select hlthcr_branches.id_customer from hlthcr_audits
        join hlthcr_branches on hlthcr_branches.id = hlthcr_audits.id_branch where hlthcr_audits.id = documents.record_id)
    end';
  }

  public function getCustomerNameSql(): string
  {
    return 'select customers.name from customers where customers.id = (' . $this->getCustomerIdSql() . ')';
  }

  // What the document belongs to, as the legacy column "Priradenie záznamu": the client, the branch or the audit with its branch.
  public function getLinkedRecordTextSql(): string
  {
    return 'case documents.model
      when ' . $this->quote(self::CUSTOMER_MODEL) . ' then (select customers.name from customers where customers.id = documents.record_id)
      when ' . $this->quote(self::BRANCH_MODEL) . ' then (select hlthcr_branches.name from hlthcr_branches where hlthcr_branches.id = documents.record_id)
      when ' . $this->quote(self::AUDIT_MODEL) . ' then (select concat(date_format(hlthcr_audits.date_executed, "%d.%m.%Y"), " - ", hlthcr_branches.name)
        from hlthcr_audits join hlthcr_branches on hlthcr_branches.id = hlthcr_audits.id_branch where hlthcr_audits.id = documents.record_id)
    end';
  }

  /**
   * A client document follows the client's hygienist, a branch or audit document the branch's hygienist (as before the move
   * to Hubleto's documents). Medical staff see no work documents. Documents of other apps keep Hubleto's rule (everybody),
   * but of the Hlthcr roles only administrators and managers see them (the others do not have Hubleto's Documents app).
   */
  public function restrictToVisibleDocuments(mixed $query): mixed
  {
    /** @var AccessScope */
    $accessScope = $this->getService(AccessScope::class);
    /** @var BranchScope */
    $branchScope = $this->getService(BranchScope::class);

    $seesWorkDocuments = $accessScope->activeUserSeesWorkRecords();
    $roles = $this->getService(\Hubleto\App\External\WaiBlue\HlthcrCore\Services\Roles::class);
    $seesOtherDocuments = !$accessScope->activeUserHasHlthcrRole() || $roles->isActiveUserAdministratorOrManager();

    return $query->where(function ($visibleDocuments) use ($accessScope, $branchScope, $seesWorkDocuments, $seesOtherDocuments) {
      if ($seesWorkDocuments) {
        $visibleDocuments->orWhere(function ($clientDocuments) use ($accessScope) {
          $clientDocuments->where('documents.model', self::CUSTOMER_MODEL);
          $accessScope->restrictToVisibleCustomers($clientDocuments, 'documents.record_id');
        });
        $visibleDocuments->orWhere(function ($branchDocuments) use ($branchScope) {
          $branchDocuments->where('documents.model', self::BRANCH_MODEL);
          $branchScope->restrictToVisibleBranches($branchDocuments, 'documents.record_id');
        });
        $visibleDocuments->orWhere(function ($auditDocuments) use ($branchScope) {
          $auditDocuments->where('documents.model', self::AUDIT_MODEL);
          $auditDocuments->whereIn('documents.record_id', function ($visibleAudits) use ($branchScope) {
            $visibleAudits->select('hlthcr_audits.id')->from('hlthcr_audits');
            $branchScope->restrictToVisibleBranches($visibleAudits, 'hlthcr_audits.id_branch');
          });
        });
      }

      if ($seesOtherDocuments) {
        $visibleDocuments->orWhere(function ($otherDocuments) {
          $otherDocuments->whereNotIn('documents.model', self::LINKED_MODELS)->orWhereNull('documents.model');
        });
      }

      // Without any rule above the user sees no documents at all.
      if (!$seesWorkDocuments && !$seesOtherDocuments) {
        $visibleDocuments->whereRaw('1 = 0');
      }
    });
  }

  // A model name as an SQL string (backslashes doubled for MySQL).
  private function quote(string $modelName): string
  {
    return "'" . str_replace('\\', '\\\\', $modelName) . "'";
  }
}
