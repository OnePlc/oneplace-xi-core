<?php
namespace Offerwall\V1\Rpc\Ayetstudios;

class AyetstudiosControllerFactory
{
    public function __invoke($controllers)
    {
        return new AyetstudiosController($controllers->get('faucetdev'));
    }
}
