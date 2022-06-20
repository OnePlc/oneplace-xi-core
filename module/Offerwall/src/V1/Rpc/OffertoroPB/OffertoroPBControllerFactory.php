<?php
namespace Offerwall\V1\Rpc\OffertoroPB;

class OffertoroPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new OffertoroPBController($controllers->get('faucetdev'));
    }
}
