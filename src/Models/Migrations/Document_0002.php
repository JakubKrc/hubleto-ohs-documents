<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments\Models\Migrations;

use Hubleto\Framework\Migration;

// A client or branch with documents cannot be deleted; the documents of an audit are still deleted with it.
class Document_0002 extends Migration
{
  public function upgradeSchema(): void
  {
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `ohs_documents` DROP FOREIGN KEY `fk__ohs_documents__id_customer`");
    $this->db->execute("ALTER TABLE `ohs_documents` ADD CONSTRAINT `fk__ohs_documents__id_customer` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `ohs_documents` DROP FOREIGN KEY `fk__ohs_documents__id_branch`");
    $this->db->execute("ALTER TABLE `ohs_documents` ADD CONSTRAINT `fk__ohs_documents__id_branch` FOREIGN KEY (`id_branch`) REFERENCES `ohs_branches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `ohs_documents` DROP FOREIGN KEY `fk__ohs_documents__id_customer`");
    $this->db->execute("ALTER TABLE `ohs_documents` ADD CONSTRAINT `fk__ohs_documents__id_customer` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `ohs_documents` DROP FOREIGN KEY `fk__ohs_documents__id_branch`");
    $this->db->execute("ALTER TABLE `ohs_documents` ADD CONSTRAINT `fk__ohs_documents__id_branch` FOREIGN KEY (`id_branch`) REFERENCES `ohs_branches` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
  }
}
