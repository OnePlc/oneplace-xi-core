<?php
namespace Marketplace\V1\Rest\Marketplace;

class MarketplaceResourceFactory
{
    public function __invoke($services)
    {
        return new MarketplaceResource($services->get('faucetdev'));
    }
}
