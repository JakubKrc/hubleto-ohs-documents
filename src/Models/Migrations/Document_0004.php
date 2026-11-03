<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Models\Migrations;

use Hubleto\App\External\WaiBlue\HlthcrDocuments\HubletoOverrides\Document as HlthcrDocument;
use Hubleto\App\External\WaiBlue\HlthcrDocuments\Services\DocumentColumns;
use Hubleto\App\External\WaiBlue\HlthcrDocuments\Services\DocumentLinks;
use Hubleto\Framework\Migration;

/**
 * Moves the work documents into Hubleto's "documents" table (linked by model + record_id) and drops "hlthcr_documents".
 * It runs with the foreign keys, because the earlier migrations still add foreign keys to the old table on a new install.
 */
class Document_0004 extends Migration
{
  const OLD_DOCUMENT_MODEL = 'Hubleto\\App\\External\\WaiBlue\\HlthcrDocuments\\Models\\Document';

  public function upgradeSchema(): void
  {
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
    /** @var DocumentColumns */
    $documentColumns = $this->model->getService(DocumentColumns::class);
    $documentColumns->installMissingColumns();

    $oldTableExists = count($this->db->fetchAll("SHOW TABLES LIKE 'hlthcr_documents'")) > 0;
    if (!$oldTableExists) return;

    $oldDocuments = $this->db->fetchAll('SELECT * FROM `hlthcr_documents` ORDER BY `id`');
    foreach ($oldDocuments as $oldDocument) {
      $this->moveDocument($oldDocument);
    }

    $this->db->execute('DROP TABLE `hlthcr_documents`');
  }

  public function downgradeForeignKeys(): void
  {
    // Not reversible: the documents stay in Hubleto's table.
  }

  // The most specific link wins: an audit document belongs to the audit, a branch document to the branch.
  private function moveDocument(array $oldDocument): void
  {
    $linkedModel = DocumentLinks::CUSTOMER_MODEL;
    $linkedRecordId = (int) $oldDocument['id_customer'];
    if ((int) $oldDocument['id_branch'] > 0) {
      $linkedModel = DocumentLinks::BRANCH_MODEL;
      $linkedRecordId = (int) $oldDocument['id_branch'];
    }
    if ((int) $oldDocument['id_audit'] > 0) {
      $linkedModel = DocumentLinks::AUDIT_MODEL;
      $linkedRecordId = (int) $oldDocument['id_audit'];
    }

    $this->db->execute(
      'INSERT INTO `documents` (`uid`, `name`, `model`, `record_id`, `created_on`, `id_created_by`, `id_workflow`, `id_workflow_step`, `hlthcr_priority`, `hlthcr_file`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
        $oldDocument['name'],
        $linkedModel,
        $linkedRecordId,
        $oldDocument['date_created'] === null ? null : $oldDocument['date_created'] . ' 00:00:00',
        $oldDocument['id_author'],
        $oldDocument['id_workflow'],
        $oldDocument['id_workflow_step'],
        $oldDocument['priority'],
        $oldDocument['file'],
      ]
    );
    $idNewDocument = (int) $this->db->fetchAll('SELECT LAST_INSERT_ID() AS id')[0]['id'];

    // The state history goes with the document.
    $this->db->execute(
      'UPDATE `workflow_history` SET `model` = ?, `record_id` = ? WHERE `model` = ? AND `record_id` = ?',
      [HlthcrDocument::class, $idNewDocument, self::OLD_DOCUMENT_MODEL, (int) $oldDocument['id']]
    );
  }
}
