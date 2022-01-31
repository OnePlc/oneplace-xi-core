<?php
namespace Backend\V1\Rest\Contest;

class ContestResourceFactory
{
    public function __invoke($services)
    {
        return new ContestResource($services->get('faucetdev'));
    }
}
