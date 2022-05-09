<?php
namespace Status\V1\Rpc\BitlabsPB;

class BitlabsPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new BitlabsPBController();
    }
}
