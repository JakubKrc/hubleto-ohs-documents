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

    // Only administrators and managers change workflows (the record write rules).
    /** @var \Hubleto\App\External\WaiBlue\OhsCore\Services\Roles */
    $roles = $this->getService(\Hubleto\App\External\WaiBlue\OhsCore\Services\Roles::class);
    $this->viewParams['mayEditWorkflows'] = $roles->isActiveUserAdministratorOrManager();

    $this->setView('@Hubleto:App:External:WaiBlue:OhsDocuments/Settings.twig');
  }
}
