<?php
namespace Faucet\V1\Rpc\Claim;

class ClaimControllerFactory
{
    public function __invoke($controllers)
    {
        return new ClaimController($controllers->get('faucetdev'));
    }
}
