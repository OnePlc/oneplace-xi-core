<?php
namespace Feedback\V1\Rest\Suggestion;

class SuggestionResourceFactory
{
    public function __invoke($services)
    {
        return new SuggestionResource($services->get('faucetdev'));
    }
}
