<?php
namespace Faucet\V1\Rest\Achievement;

class AchievementResourceFactory
{
    public function __invoke($services)
    {
        return new AchievementResource($services->get('faucetdev'));
    }
}
