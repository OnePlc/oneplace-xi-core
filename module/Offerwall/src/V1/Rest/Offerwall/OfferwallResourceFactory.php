<?php
namespace Offerwall\V1\Rest\Offerwall;

class OfferwallResourceFactory
{
    public function __invoke($services)
    {
        return new OfferwallResource($services->get('faucetdev'));
    }
}
