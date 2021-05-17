<?php

namespace Application\Identity;

use Laminas\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\Permissions\Rbac\Role as AbstractRbacRole;
use Laminas\Permissions\Rbac\RoleInterface;
use phpDocumentor\Reflection\Types\Object_;

final class UserIdentity extends AbstractRbacRole implements IdentityInterface
{
    private $user;
    protected $name;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function getAuthenticationIdentity()
    {
        return $this->user;
    }

    public function getId()
    {
        return $this->user->User_ID;
    }

    public function getUser()
    {
        return $this->getAuthenticationIdentity();
    }

    public function getRoleId()
    {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
    }
}