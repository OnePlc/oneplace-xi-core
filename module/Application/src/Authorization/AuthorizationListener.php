<?php

namespace Application\Authorization;

use Application\Controller\IndexController;
use Laminas\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\ApiTools\OAuth2\Controller\Auth;
use User\V1\Rpc\Login\LoginController;

final class AuthorizationListener
{
    public function __invoke(MvcAuthEvent $mvcAuthEvent)
    {
        $authorization = $mvcAuthEvent->getAuthorizationService();

        // Deny from all
        //$authorization->deny();

        $authorization->addRole('user');


        $authorization->addResource(IndexController::class . '::index');
        $authorization->allow('guest', IndexController::class . '::index');

        $authorization->addResource('User\V1\Rpc\Login\LoginController::loginAction');
        $authorization->allow('guest', 'User\V1\Rpc\Login\LoginController::loginAction');

        $authorization->addResource(LoginController::class . '::login');
        $authorization->allow('guest', LoginController::class . '::login');
        $authorization->allow('user', LoginController::class . '::login');

        $authorization->addResource(Auth::class . '::authorize');
        $authorization->allow('user', Auth::class . '::authorize');
    }
}