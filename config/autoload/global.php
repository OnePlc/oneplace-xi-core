<?php
return [
    'api-tools-content-negotiation' => [
        'selectors' => [],
    ],
    'db' => [
        'adapters' => [
            'dummy' => [],
            'faucetdev' => [],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authentication' => [
            'map' => [
                'Status\\V1' => 'oneplace',
                'User\\V1' => 'oneplace',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'oauth' => [
                'options' => [
                    'spec' => '%oauth%',
                    'regex' => '(?P<oauth>(/oauth))',
                ],
                'type' => 'regex',
            ],
        ],
    ],
];
