<?php
namespace Backend\V1\Rpc\ShortEarnings;

class ShortEarningsControllerFactory
{
    public function __invoke($controllers)
    {
        return new ShortEarningsController($controllers->get('faucetdev'));
    }
}
