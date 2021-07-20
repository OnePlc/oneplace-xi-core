<?php
namespace Guild\V1\Rpc\Chat;

class ChatControllerFactory
{
    public function __invoke($controllers)
    {
        return new ChatController($controllers->get('faucetdev'));
    }
}
