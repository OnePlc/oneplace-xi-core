<?php
namespace PTC\V1\Rpc\Deposit;

class DepositControllerFactory
{
    public function __invoke($controllers)
    {
        return new DepositController($controllers->get('faucetdev'));
    }
}
