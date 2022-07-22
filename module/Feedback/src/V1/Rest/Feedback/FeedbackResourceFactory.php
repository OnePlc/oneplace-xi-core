<?php
namespace Feedback\V1\Rest\Feedback;

class FeedbackResourceFactory
{
    public function __invoke($services)
    {
        return new FeedbackResource($services->get('faucetdev'));
    }
}
