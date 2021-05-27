<?php
namespace Faucet\V1\Rpc\Token;

class TokenControllerFactory
{
    public function __invoke($controllers)
    {
        return new TokenController($controllers->get('faucetdev'));
    }
}
