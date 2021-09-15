<?php
namespace Guild\V1\Rest\Rank;

class RankResourceFactory
{
    public function __invoke($services)
    {
        return new RankResource($services->get('faucetdev'));
    }
}
