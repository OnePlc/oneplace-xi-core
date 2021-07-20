<?php
namespace PTC\V1\Rpc\Manage;

class ManageControllerFactory
{
    public function __invoke($controllers)
    {
        return new ManageController($controllers->get('faucetdev'));
    }
}
