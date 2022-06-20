<?php
namespace Offerwall\V1\Rpc\HangPB;

class HangPBControllerFactory
{
    public function __invoke($controllers)
    {
        return new HangPBController();
    }
}
