<?php
namespace Backend\V1\Rest\TokenBuy;

class TokenBuyResourceFactory
{
    public function __invoke($services)
    {
        return new TokenBuyResource($services->get('faucetdev'));
    }
}
