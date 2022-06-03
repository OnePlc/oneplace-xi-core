<?php
namespace Batch\V1\Rpc\OfferwallStats;

class OfferwallStatsControllerFactory
{
    public function __invoke($controllers)
    {
        return new OfferwallStatsController($controllers->get('faucetdev'));
    }
}
