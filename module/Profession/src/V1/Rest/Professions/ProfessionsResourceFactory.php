<?php
namespace Profession\V1\Rest\Professions;

class ProfessionsResourceFactory
{
    public function __invoke($services)
    {
        return new ProfessionsResource($services->get('faucetdev'));
    }
}
