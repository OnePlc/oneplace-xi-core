<?php
namespace User\V1\Rpc\Confirm;

class ConfirmControllerFactory
{
    public function __invoke($controllers)
    {
        return new ConfirmController($controllers->get('faucetdev'), $controllers->get('ViewRenderer'));
    }
}
