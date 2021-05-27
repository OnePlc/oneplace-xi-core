<?php
namespace Shortlink\V1\Rpc\Complete;

class CompleteControllerFactory
{
    public function __invoke($controllers)
    {
        return new CompleteController($controllers->get('faucetdev'));
    }
}
