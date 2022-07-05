<?php
namespace Offerwall\V1\Rpc\AdgatePB;

class AdgatePBControllerFactory
{
    public function __invoke($controllers)
    {
        return new AdgatePBController($controllers->get('faucetdev'));
    }
}
