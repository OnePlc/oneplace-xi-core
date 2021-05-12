<?php
namespace News\V1\Rpc\News;

class NewsControllerFactory
{
    public function __invoke($controllers)
    {
        return new NewsController($controllers->get('faucetdev'));
    }
}
