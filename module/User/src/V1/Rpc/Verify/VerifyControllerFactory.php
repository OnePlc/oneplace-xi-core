<?php
namespace User\V1\Rpc\Verify;

class VerifyControllerFactory
{
    public function __invoke($controllers)
    {
        return new VerifyController($controllers->get('faucetdev'));
    }
}
