<?php

namespace Hubleto\App\External\WaiBlue\HlthcrDocuments\Services;

use Hubleto\App\Community\Documents\Models\Document;

/**
 * Extra columns on the community "documents" table: what both legacy apps keep and Hubleto's document does not have.
 * Uses Hubleto custom columns: the DB columns are added on install, the definitions are set on every request.
 */
class DocumentColumns extends \Hubleto\Erp\Core
{
  // Priority as in both legacy apps: 1 is the most urgent (red), 3 the least (green).
  const PRIORITY_HIGH = 1;
  const PRIORITY_MEDIUM = 2;
  const PRIORITY_LOW = 3;

  public string $translationContext = 'hubleto-app-external-waiblue-hlthcrdocuments-loader';
  public string $translationContextInner = 'Services\\DocumentColumns';

  /**
   * Column name => SQL type used when adding the column to the "documents" table.
   */
  public function getSqlTypes(): array
  {
    return [
      'hlthcr_priority' => 'int(8) NULL DEFAULT NULL',
      'hlthcr_file' => 'varchar(255) NULL DEFAULT NULL',
    ];
  }

  /**
   * Column name => Hubleto column definition (format of Column::fromArray()).
   * The file is one file per document, as in both legacy apps; the Hlthcr document model serves it through a checked download.
   */
  public function getColumnDefinitions(): array
  {
    return [
      'hlthcr_priority' => [
        'class' => 'Integer',
        'title' => $this->translate('Priority'),
        'defaultValue' => self::PRIORITY_LOW,
        'enumValues' => [
          self::PRIORITY_HIGH => '1',
          self::PRIORITY_MEDIUM => '2',
          self::PRIORITY_LOW => '3',
        ],
        'enumCssClasses' => [
          self::PRIORITY_HIGH => 'bg-red-100 text-red-800',
          self::PRIORITY_MEDIUM => 'bg-yellow-100 text-yellow-800',
          self::PRIORITY_LOW => 'bg-green-100 text-green-800',
        ],
      ],
      'hlthcr_file' => [
        'class' => 'File',
        'title' => $this->translate('Document'),
      ],
    ];
  }

  /**
   * Called from Loader::init(). Document::describeColumns() reads this config key.
   */
  public function registerInDocumentModel(): void
  {
    $configPath = 'models/' . Document::class . '/customColumns';
    $this->config()->set($configPath, json_encode($this->getColumnDefinitions()));
  }

  /**
   * Adds missing columns to the "documents" table. Safe to run repeatedly.
   */
  public function installMissingColumns(): void
  {
    $existingColumns = [];
    $columnRows = $this->db()->fetchAll(
      "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents'"
    );
    foreach ($columnRows as $columnRow) {
      $existingColumns[] = $columnRow['COLUMN_NAME'];
    }

    foreach ($this->getSqlTypes() as $columnName => $sqlType) {
      if (in_array($columnName, $existingColumns)) continue;
      $this->db()->execute("ALTER TABLE `documents` ADD COLUMN `{$columnName}` {$sqlType}");
    }
  }
}
