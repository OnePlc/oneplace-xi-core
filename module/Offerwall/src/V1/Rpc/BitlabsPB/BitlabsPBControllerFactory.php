<?php
namespace Offerwall\V1\Rpc\BitlabsPB;

class BitlabsPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new BitlabsPBController($controllers->get('faucetdev'));
    }
}
