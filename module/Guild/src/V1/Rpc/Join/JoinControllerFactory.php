<?php
namespace Guild\V1\Rpc\Join;

class JoinControllerFactory
{
    public function __invoke($controllers)
    {
        return new JoinController($controllers->get('faucetdev'));
    }
}
