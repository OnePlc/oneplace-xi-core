<?php
namespace Batch\V1\Rpc\MinerPayments;

class MinerPaymentsControllerFactory
{
    public function __invoke($controllers)
    {
        return new MinerPaymentsController($controllers->get('faucetdev'));
    }
}
