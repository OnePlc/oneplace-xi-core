<?php
namespace Faucet\V1\Rpc\Item;

class ItemControllerFactory
{
    public function __invoke($controllers)
    {
        return new ItemController($controllers->get('faucetdev'));
    }
}
