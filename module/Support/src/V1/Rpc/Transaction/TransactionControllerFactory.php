<?php
namespace Support\V1\Rpc\Transaction;

class TransactionControllerFactory
{
    public function __invoke($controllers)
    {
        return new TransactionController($controllers->get('faucetdev'));
    }
}
