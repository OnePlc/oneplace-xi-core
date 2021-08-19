<?php
namespace Marketplace\V1\Rest\Itemstore;

class ItemstoreResourceFactory
{
    public function __invoke($services)
    {
        return new ItemstoreResource($services->get('faucetdev'));
    }
}
