<?php
namespace Offerwall\V1\Rpc\Lootably;

class LootablyControllerFactory
{
    public function __invoke($controllers)
    {
        return new LootablyController($controllers->get('faucetdev'));
    }
}
