<?php
namespace Offerwall\V1\Rpc\CpxPB;

class CpxPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new CpxPBController($controllers->get('faucetdev'));
    }
}
