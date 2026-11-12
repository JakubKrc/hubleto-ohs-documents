<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments\Services;

/**
 * A work document belongs to a client, a branch or an audit through Hubleto's "model" + "record_id"
 * (both legacy apps link documents the same way, with lookup_model + lookup_id).
 */
class DocumentLinks extends \Hubleto\Erp\Core
{
  const CUSTOMER_MODEL = 'Hubleto\\App\\Community\\Customers\\Models\\Customer';
  const BRANCH_MODEL = 'Hubleto\\App\\External\\WaiBlue\\OhsBranches\\Models\\Branch';
  const AUDIT_MODEL = 'Hubleto\\App\\External\\WaiBlue\\OhsAudits\\Models\\Audit';

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

  public function linkedRecordExists(string $modelName, int $recordId): bool
  {
    $modelName = $this->normalizeModelName($modelName);
    if (!$this->isLinkedModel($modelName) || $recordId <= 0) return false;

    $linkedModel = $this->getModel($modelName);
    return $linkedModel->record->where($linkedModel->table . '.id', $recordId)->exists();
  }

  // Id and file of every document of a client, a branch or an audit.
  public function getDocumentsOfRecord(string $modelName, int $recordId): array
  {
    return $this->db()->fetchAll(
      'SELECT id, ohs_file FROM documents WHERE model = ? AND record_id = ?',
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
      when ' . $this->quote(self::BRANCH_MODEL) . ' then (select ohs_branches.id_customer from ohs_branches where ohs_branches.id = documents.record_id)
      when ' . $this->quote(self::AUDIT_MODEL) . ' then (select ohs_branches.id_customer from ohs_audits
        join ohs_branches on ohs_branches.id = ohs_audits.id_branch where ohs_audits.id = documents.record_id)
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
      when ' . $this->quote(self::BRANCH_MODEL) . ' then (select ohs_branches.name from ohs_branches where ohs_branches.id = documents.record_id)
      when ' . $this->quote(self::AUDIT_MODEL) . ' then (select concat(date_format(ohs_audits.date_executed, "%d.%m.%Y"), " - ", ohs_branches.name)
        from ohs_audits join ohs_branches on ohs_branches.id = ohs_audits.id_branch where ohs_audits.id = documents.record_id)
    end';
  }

  // A model name as an SQL string (backslashes doubled for MySQL).
  private function quote(string $modelName): string
  {
    return "'" . str_replace('\\', '\\\\', $modelName) . "'";
  }
}
