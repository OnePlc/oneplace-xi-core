<?php
namespace User\V1\Rest\Poll;

class PollResourceFactory
{
    public function __invoke($services)
    {
        return new PollResource($services->get('faucetdev'));
    }
}
