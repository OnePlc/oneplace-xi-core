<?php
namespace Faucet\V1\Rpc\Adwatch;

class AdwatchControllerFactory
{
    public function __invoke($controllers)
    {
        return new AdwatchController($controllers->get('faucetdev'));
    }
}
