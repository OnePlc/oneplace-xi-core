<?php
namespace Faucet\V1\Rpc\Withdraw;

class WithdrawControllerFactory
{
    public function __invoke($controllers)
    {
        return new WithdrawController($controllers->get('faucetdev'), $controllers->get('gachaminer'));
    }
}
