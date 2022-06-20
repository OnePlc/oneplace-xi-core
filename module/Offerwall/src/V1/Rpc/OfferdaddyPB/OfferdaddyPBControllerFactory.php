<?php
namespace Offerwall\V1\Rpc\OfferdaddyPB;

class OfferdaddyPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new OfferdaddyPBController($controllers->get('faucetdev'));
    }
}
