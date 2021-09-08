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
                'Game\\V1' => 'oneplace',
                'Support\\V1' => 'oneplace',
                'Laminas\\ApiTools\\OAuth2' => 'session',
                'Mining\\V1' => 'oneplace',
                'PTC\\V1' => 'oneplace',
                'Mailbox\\V1' => 'oneplace',
                'Profession\\V1' => 'oneplace',
                'Marketplace\\V1' => 'oneplace',
                'Batch\\V1' => 'oneplace',
            ],
        ],
        'adapters' => [
            'session' => [
                'adapter' => \Application\Authentication\Adapter\SessionAdapter::class,
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
