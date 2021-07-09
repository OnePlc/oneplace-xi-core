<?php
namespace Faucet\V1\Rpc\Wallet;

class WalletControllerFactory
{
    public function __invoke($controllers)
    {
        return new WalletController($controllers->get('faucetdev'));
    }
}
