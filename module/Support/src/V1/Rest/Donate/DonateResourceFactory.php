<?php
namespace Support\V1\Rest\Donate;

class DonateResourceFactory
{
    public function __invoke($services)
    {
        return new DonateResource($services->get('faucetdev'));
    }
}
