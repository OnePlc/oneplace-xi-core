<?php
namespace News\V1\Rpc\Newsletter;

class NewsletterControllerFactory
{
    public function __invoke($controllers)
    {
        return new NewsletterController($controllers->get('faucetdev'), $controllers->get('ViewRenderer'));
    }
}
