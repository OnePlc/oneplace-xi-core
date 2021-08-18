<?php
namespace Marketplace\V1\Rpc\Auction;

class AuctionControllerFactory
{
    public function __invoke($controllers)
    {
        return new AuctionController($controllers->get('faucetdev'));
    }
}
