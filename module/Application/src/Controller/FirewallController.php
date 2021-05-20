<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

class FirewallController extends AbstractActionController
{
    /**
     * The entry point of the Firewall Panel.
     */
    public function panelAction()
    {
        $panel = new \Shieldon\Firewall\Panel();
        $panel->entry();
    }
}