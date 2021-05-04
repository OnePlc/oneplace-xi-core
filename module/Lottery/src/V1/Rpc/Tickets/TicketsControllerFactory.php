<?php
namespace Lottery\V1\Rpc\Tickets;

class TicketsControllerFactory
{
    public function __invoke($controllers)
    {
        return new TicketsController($controllers->get('faucetdev'));
    }
}
