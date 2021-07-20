<?php
namespace User\V1\Rpc\Forgot;

class ForgotControllerFactory
{
    public function __invoke($controllers)
    {
        return new ForgotController($controllers->get('faucetdev'), $controllers->get('ViewRenderer'));
    }
}
