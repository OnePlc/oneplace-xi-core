<?php
namespace Lottery\V1\Rpc\Round;

class RoundControllerFactory
{
    public function __invoke($controllers)
    {
        return new RoundController($controllers->get('faucetdev'));
    }
}
