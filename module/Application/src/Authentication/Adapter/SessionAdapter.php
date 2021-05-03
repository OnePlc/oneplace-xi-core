<?php

namespace Application\Authentication\Adapter;

use Laminas\ApiTools\MvcAuth\Authentication\AdapterInterface;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Session\Container;
use Application\Identity;

final class SessionAdapter implements AdapterInterface
{
    public function provides()
    {
        return [
            'session',
        ];
    }

    public function matches($type)
    {
        return $type == 'session';
    }

    public function getTypeFromRequest(Request $request)
    {
        return false;
    }

    public function preAuth(Request $request, Response $response)
    {
    }

    public function authenticate(Request $request, Response $response, MvcAuthEvent $mvcAuthEvent)
    {
        $session = new Container('webauth');

        if ($session->auth) {
            $userIdentity = new Identity\UserIdentity($session->auth);
            $userIdentity->setName('user');

            return $userIdentity;
        }

        // Force login for all other routes
        $mvcAuthEvent->stopPropagation();
        $session->redirect = $request->getUriString();
        $response->getHeaders()->addHeaderLine('Location', '/login');
        $response->setStatusCode(302);
        $response->sendHeaders();

        return $response;
    }
}