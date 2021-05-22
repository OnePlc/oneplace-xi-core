<?php
namespace Mining\V1\Rpc\History;

class HistoryControllerFactory
{
    public function __invoke($controllers)
    {
        return new HistoryController($controllers->get('faucetdev'));
    }
}
