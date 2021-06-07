<?php
namespace PTC\V1\Rest\PTC;

class PTCResourceFactory
{
    public function __invoke($services)
    {
        return new PTCResource($services->get('faucetdev'));
    }
}
