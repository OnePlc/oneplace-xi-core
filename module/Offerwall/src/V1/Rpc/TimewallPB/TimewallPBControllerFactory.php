<?php
namespace Offerwall\V1\Rpc\TimewallPB;

class TimewallPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new TimewallPBController($controllers->get('faucetdev'));
    }
}
