<?php
namespace Batch\V1\Rpc\BatchChecker;

class BatchCheckerControllerFactory
{
    public function __invoke($controllers)
    {
        return new BatchCheckerController($controllers->get('faucetdev'), $controllers->get('ViewRenderer'));
    }
}
