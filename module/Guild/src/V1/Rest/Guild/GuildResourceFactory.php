<?php
namespace Guild\V1\Rest\Guild;

class GuildResourceFactory
{
    public function __invoke($services)
    {
        return new GuildResource($services->get('faucetdev'));
    }
}
