<?php
namespace Backend\V1\Rpc\UserInfo;

class UserInfoControllerFactory
{
    public function __invoke($controllers)
    {
        return new UserInfoController($controllers->get('faucetdev'));
    }
}
