<?php
namespace Faucet\V1\Rpc\HallOfFame;

class HallOfFameControllerFactory
{
    public function __invoke($controllers)
    {
        return new HallOfFameController($controllers->get('faucetdev'));
    }
}
