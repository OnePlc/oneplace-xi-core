<?php
namespace Mailbox\V1\Rest\Inbox;

class InboxResourceFactory
{
    public function __invoke($services)
    {
        return new InboxResource($services->get('faucetdev'));
    }
}
