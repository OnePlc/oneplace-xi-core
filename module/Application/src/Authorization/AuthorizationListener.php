<?php

namespace Application\Authorization;

use Application\Controller\IndexController;
use Laminas\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\ApiTools\OAuth2\Controller\Auth;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use User\V1\Rpc\Login\LoginController;

final class AuthorizationListener
{
    public function __invoke(MvcAuthEvent $mvcAuthEvent)
    {
        $authorization = $mvcAuthEvent->getAuthorizationService();

        // Deny from all
        //$authorization->deny();

        $authorization->addRole(new Role('user'))
            ->addRole(new Role('admin'));

        $authorization->addResource(IndexController::class . '::index');
        $authorization->allow('guest', IndexController::class . '::index');

        $authorization->addResource(LoginController::class . '::login');
        $authorization->allow('guest', LoginController::class . '::login');

        $authorization->addResource(Auth::class . '::authorize');
        $authorization->allow('user', Auth::class . '::authorize');

        $authorization->deny();
        echo json_encode($authorization->isAllowed('guest', LoginController::class . '::login') ? 'allowed' : 'denied');
    }
}