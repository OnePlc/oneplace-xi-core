<?php
namespace Batch\V1\Rpc\ContestBatch;

class ContestBatchControllerFactory
{
    public function __invoke($controllers)
    {
        return new ContestBatchController($controllers->get('faucetdev'));
    }
}
