<?php
namespace Offerwall\V1\Rpc\PersonaPB;

class PersonaPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new PersonaPBController($controllers->get('faucetdev'));
    }
}
