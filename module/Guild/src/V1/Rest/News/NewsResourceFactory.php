<?php
namespace Guild\V1\Rest\News;

class NewsResourceFactory
{
    public function __invoke($services)
    {
        return new NewsResource($services->get('faucetdev'));
    }
}
