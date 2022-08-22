<?php
namespace Backend\V1\Rest\Banhammer;

class BanhammerResourceFactory
{
    public function __invoke($services)
    {
        return new BanhammerResource($services->get('faucetdev'));
    }
}
