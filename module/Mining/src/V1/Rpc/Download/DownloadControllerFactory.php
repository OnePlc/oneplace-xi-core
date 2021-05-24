<?php
namespace Mining\V1\Rpc\Download;

class DownloadControllerFactory
{
    public function __invoke($controllers)
    {
        return new DownloadController($controllers->get('faucetdev'));
    }
}
