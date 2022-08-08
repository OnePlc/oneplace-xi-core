<?php
namespace Backend\V1\Rest\TokenStaking;

class TokenStakingResourceFactory
{
    public function __invoke($services)
    {
        return new TokenStakingResource($services->get('faucetdev'));
    }
}
