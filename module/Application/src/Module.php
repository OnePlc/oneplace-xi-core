<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-skeleton for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-skeleton/blob/master/LICENSE.md New BSD License
 */

namespace Application;

use Laminas\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Laminas\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\EventManager\EventInterface;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
