<?php
namespace Application\Controller;

class IndexControllerFactory
{
    public function __invoke($controllers)
    {
        return new IndexController($controllers->get('faucetdev'));
    }
}
