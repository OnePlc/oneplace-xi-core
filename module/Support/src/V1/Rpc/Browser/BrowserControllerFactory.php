<?php
namespace Support\V1\Rpc\Browser;

class BrowserControllerFactory
{
    public function __invoke($controllers)
    {
        return new BrowserController($controllers->get('faucetdev'));
    }
}
