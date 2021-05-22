<?php
namespace Support\V1\Rpc\Ticket;

class TicketControllerFactory
{
    public function __invoke($controllers)
    {
        return new TicketController($controllers->get('faucetdev'));
    }
}
