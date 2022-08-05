<?php
namespace Backend\V1\Rest\Withdraw;

class WithdrawResourceFactory
{
    public function __invoke($services)
    {
        return new WithdrawResource($services->get('faucetdev'));
    }
}
