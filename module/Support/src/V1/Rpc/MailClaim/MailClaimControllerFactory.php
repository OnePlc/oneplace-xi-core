<?php
namespace Support\V1\Rpc\MailClaim;

class MailClaimControllerFactory
{
    public function __invoke($controllers)
    {
        return new MailClaimController($controllers->get('faucetdev'));
    }
}
