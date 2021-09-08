<?php
namespace Batch\V1\Rpc\Refstats;

class RefstatsControllerFactory
{
    public function __invoke($controllers)
    {
        return new RefstatsController($controllers->get('faucetdev'));
    }
}
