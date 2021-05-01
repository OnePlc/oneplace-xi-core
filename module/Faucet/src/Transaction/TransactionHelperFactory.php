<?php
namespace Faucet\Transaction;

class TransactionHelperFactory
{
    public function __invoke($controllers)
    {
        return new TransactionHelper($controllers->get('faucetdev'));
    }
}
