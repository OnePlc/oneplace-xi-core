<?php
namespace Offerwall\V1\Rpc\KiwiPB;

class KiwiPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new KiwiPBController($controllers->get('faucetdev'));
    }
}
