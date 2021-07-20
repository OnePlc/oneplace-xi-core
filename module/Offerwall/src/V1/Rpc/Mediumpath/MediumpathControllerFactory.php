<?php
namespace Offerwall\V1\Rpc\Mediumpath;

class MediumpathControllerFactory
{
    public function __invoke($controllers)
    {
        return new MediumpathController($controllers->get('faucetdev'));
    }
}
