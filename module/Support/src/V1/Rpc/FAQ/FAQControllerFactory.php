<?php
namespace Support\V1\Rpc\FAQ;

class FAQControllerFactory
{
    public function __invoke($controllers)
    {
        return new FAQController($controllers->get('faucetdev'));
    }
}
