<?php
namespace Batch\V1\Rpc\Migrate;

class MigrateControllerFactory
{
    public function __invoke($controllers)
    {
        return new MigrateController($controllers->get('faucetdev'));
    }
}
