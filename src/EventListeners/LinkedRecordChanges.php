<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\EventListeners;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\ProtectedFile;
use Hubleto\App\External\WaiBlue\HlthcrDocuments\Services\DocumentLinks;
use Hubleto\Framework\Exceptions\RecordDeleteException;
use Hubleto\Framework\Interfaces\ModelInterface;

/**
 * Documents are linked by model + record_id, so the database does not guard them (both legacy apps do the same in code):
 * a client or a branch with documents cannot be deleted; deleting an audit deletes its documents and their files.
 */
class LinkedRecordChanges extends \Hubleto\Framework\EventListener implements \Hubleto\Framework\Interfaces\EventListenerInterface
{
  public string $translationContext = 'hubleto-app-external-waiblue-hlthcrdocuments-loader';
  public string $translationContextInner = 'EventListeners\\LinkedRecordChanges';

  /** @var array<int, array> Documents of audits being deleted, by audit id */
  private array $documentsByAudit = [];

  public function onModelBeforeDelete(ModelInterface $model, int $id): void
  {
    /** @var DocumentLinks */
    $documentLinks = $this->getService(DocumentLinks::class);
    $modelName = $model->fullName;

    $isClientOrBranch = $modelName == DocumentLinks::CUSTOMER_MODEL || $modelName == DocumentLinks::BRANCH_MODEL;
    if ($isClientOrBranch && count($documentLinks->getDocumentsOfRecord($modelName, $id)) > 0) {
      throw new RecordDeleteException($this->translate('It has work documents, so it cannot be deleted.'));
    }

    if ($modelName == DocumentLinks::AUDIT_MODEL) {
      $this->documentsByAudit[$id] = $documentLinks->getDocumentsOfRecord($modelName, $id);
    }
  }

  public function onModelAfterDelete(ModelInterface $model, int $id): void
  {
    if ($model->fullName != DocumentLinks::AUDIT_MODEL) return;

    /** @var ProtectedFile */
    $protectedFile = $this->getService(ProtectedFile::class);
    foreach ($this->documentsByAudit[$id] ?? [] as $document) {
      $this->db()->execute('DELETE FROM `documents` WHERE `id` = ?', [(int) $document['id']]);
      $file = (string) $document['hlthcr_file'];
      if ($file != '') $protectedFile->delete($file);
    }
    unset($this->documentsByAudit[$id]);
  }
}
