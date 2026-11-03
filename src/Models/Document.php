<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Models;

/**
 * The former table of work documents. Work documents are now Hubleto's documents (HubletoOverrides/Document);
 * this model stays only for its migrations: the last one moves the documents into Hubleto's table and drops this one.
 */
class Document extends \Hubleto\Erp\Model
{
  public string $table = 'hlthcr_documents';
}
