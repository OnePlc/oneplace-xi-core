<?php
namespace Offerwall\V1\Rpc\AdgemPB;

class AdgemPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new AdgemPBController($controllers->get('faucetdev'));
    }
}
