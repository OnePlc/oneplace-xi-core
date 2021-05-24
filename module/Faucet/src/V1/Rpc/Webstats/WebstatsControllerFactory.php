<?php
namespace Faucet\V1\Rpc\Webstats;

class WebstatsControllerFactory
{
    public function __invoke($controllers)
    {
        return new WebstatsController($controllers->get('faucetdev'));
    }
}
