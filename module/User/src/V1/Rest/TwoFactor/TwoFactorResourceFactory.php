<?php
namespace User\V1\Rest\TwoFactor;

class TwoFactorResourceFactory
{
    public function __invoke($services)
    {
        return new TwoFactorResource($services->get('faucetdev'));
    }
}
