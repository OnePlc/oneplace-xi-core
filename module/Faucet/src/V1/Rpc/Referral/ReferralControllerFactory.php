<?php
namespace Faucet\V1\Rpc\Referral;

class ReferralControllerFactory
{
    public function __invoke($controllers)
    {
        return new ReferralController($controllers->get('faucetdev'));
    }
}
