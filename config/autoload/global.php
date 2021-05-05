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
                'Faucet\\V1' => 'oneplace',
                'Shortlink\\V1' => 'oneplace',
                'Lottery\\V1' => 'oneplace',
                'Guild\\V1' => 'oneplace',
                'Offerwall\\V1' => 'oneplace',
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
