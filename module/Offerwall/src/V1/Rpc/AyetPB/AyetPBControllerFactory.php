<?php
namespace Offerwall\V1\Rpc\AyetPB;

class AyetPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new AyetPBController($controllers->get('faucetdev'));
    }
}
