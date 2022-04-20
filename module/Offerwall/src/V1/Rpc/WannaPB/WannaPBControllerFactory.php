<?php
namespace Offerwall\V1\Rpc\WannaPB;

class WannaPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new WannaPBController($controllers->get('faucetdev'));
    }
}
