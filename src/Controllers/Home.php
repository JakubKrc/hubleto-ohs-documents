<?php

namespace Hubleto\App\External\WaiBlue\OhsDocuments\Controllers;

class Home extends \Hubleto\Erp\Controller
{
  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:OhsDocuments/Home.twig');
  }
}
