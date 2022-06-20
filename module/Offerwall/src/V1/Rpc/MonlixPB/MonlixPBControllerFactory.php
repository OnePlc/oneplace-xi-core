<?php
namespace Offerwall\V1\Rpc\MonlixPB;

class MonlixPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new MonlixPBController($controllers->get('faucetdev'));
    }
}
