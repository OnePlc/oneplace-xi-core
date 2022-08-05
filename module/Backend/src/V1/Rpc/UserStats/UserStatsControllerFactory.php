<?php
namespace Backend\V1\Rpc\UserStats;

class UserStatsControllerFactory
{
    public function __invoke($controllers)
    {
        return new UserStatsController($controllers->get('faucetdev'));
    }
}
