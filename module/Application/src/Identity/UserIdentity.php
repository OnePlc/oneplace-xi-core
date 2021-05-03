<?php

namespace Application\Identity;

use Laminas\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\Permissions\Rbac\AbstractRole as AbstractRbacRole;

final class UserIdentity extends AbstractRbacRole implements IdentityInterface
{
    private $user;
    private $username;

    public function __construct(array $user)
    {
        $this->user = $user;
    }

    public function getAuthenticationIdentity()
    {
        return $this->user;
    }

    public function getId()
    {
        return $this->user['User_ID'];
    }

    public function getUser()
    {
        return $this->getAuthenticationIdentity();
    }

    public function getRoleId()
    {
        return $this->username;
    }

    // Alias for roleId
    public function setName($name)
    {
        $this->username = $name;
    }
}