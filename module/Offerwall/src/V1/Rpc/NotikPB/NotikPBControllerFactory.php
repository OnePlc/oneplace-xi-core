<?php
namespace Offerwall\V1\Rpc\NotikPB;

class NotikPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new NotikPBController($controllers->get('faucetdev'));
    }
}
