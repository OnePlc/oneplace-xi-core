<?php
namespace Faucet\V1\Rest\Dailytask;

class DailytaskResourceFactory
{
    public function __invoke($services)
    {
        return new DailytaskResource($services->get('faucetdev'));
    }
}
