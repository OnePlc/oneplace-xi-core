<?php
namespace User\V1\Rpc\Friends;

class FriendsControllerFactory
{
    public function __invoke($controllers)
    {
        return new FriendsController($controllers->get('faucetdev'), $controllers->get('ViewRenderer'));
    }
}
