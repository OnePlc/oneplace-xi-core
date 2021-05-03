<?php
namespace Shortlink\V1\Rest\Shortlink;

class ShortlinkResourceFactory
{
    public function __invoke($services)
    {
        return new ShortlinkResource($services->get('faucetdev'));
    }
}
