<?php
namespace Shortlink\V1\Rpc\Rating;

class RatingControllerFactory
{
    public function __invoke($controllers)
    {
        return new RatingController($controllers->get('faucetdev'));
    }
}
