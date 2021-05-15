<?php
namespace Support\V1\Rpc\Support;

class SupportControllerFactory
{
    public function __invoke($controllers)
    {
        return new SupportController($controllers->get('faucetdev'));
    }
}
