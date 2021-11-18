<?php
namespace Batch\V1\Rpc\Guildactivity;

class GuildactivityControllerFactory
{
    public function __invoke($controllers)
    {
        return new GuildactivityController($controllers->get('faucetdev'));
    }
}
