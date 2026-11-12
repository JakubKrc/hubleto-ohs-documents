<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments\Controllers;

class Settings extends \Hubleto\Erp\Controller
{
  public function getBreadcrumbs(): array
  {
    return array_merge(parent::getBreadcrumbs(), [
      ['url' => 'ohs/documents', 'content' => $this->translate('Work documents')],
      ['url' => 'settings', 'content' => $this->translate('Settings')],
    ]);
  }

  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:OhsDocuments/Settings.twig');
  }
}
