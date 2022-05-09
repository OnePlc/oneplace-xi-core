<?php
namespace Guild\V1\Rpc\Statistics;

class StatisticsControllerFactory
{
    public function __invoke($controllers)
    {
        return new StatisticsController($controllers->get('faucetdev'));
    }
}
