<?php
namespace Support\V1\Rpc\MailUnsub;

class MailUnsubControllerFactory
{
    public function __invoke($controllers)
    {
        return new MailUnsubController($controllers->get('faucetdev'));
    }
}
