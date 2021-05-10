<?php
namespace Guild\V1\Rpc\Bank;

class BankControllerFactory
{
    public function __invoke($controllers)
    {
        return new BankController($controllers->get('faucetdev'));
    }
}
