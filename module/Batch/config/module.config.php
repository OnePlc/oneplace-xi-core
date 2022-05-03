<?php
return [
    'controllers' => [
        'factories' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => \Batch\V1\Rpc\Refstats\RefstatsControllerFactory::class,
            'Batch\\V1\\Rpc\\Guildactivity\\Controller' => \Batch\V1\Rpc\Guildactivity\GuildactivityControllerFactory::class,
            'Batch\\V1\\Rpc\\MinerPayments\\Controller' => \Batch\V1\Rpc\MinerPayments\MinerPaymentsControllerFactory::class,
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => \Batch\V1\Rpc\BatchChecker\BatchCheckerControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'batch.rpc.refstats' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/batch/refstats',
                    'defaults' => [
                        'controller' => 'Batch\\V1\\Rpc\\Refstats\\Controller',
                        'action' => 'refstats',
                    ],
                ],
            ],
            'batch.rpc.guildactivity' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/batch/guildactivity',
                    'defaults' => [
                        'controller' => 'Batch\\V1\\Rpc\\Guildactivity\\Controller',
                        'action' => 'guildactivity',
                    ],
                ],
            ],
            'batch.rpc.miner-payments' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/miner-paymentrun',
                    'defaults' => [
                        'controller' => 'Batch\\V1\\Rpc\\MinerPayments\\Controller',
                        'action' => 'minerPayments',
                    ],
                ],
            ],
            'batch.rpc.batch-checker' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/batch-checker',
                    'defaults' => [
                        'controller' => 'Batch\\V1\\Rpc\\BatchChecker\\Controller',
                        'action' => 'batchChecker',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'batch.rpc.refstats',
            1 => 'batch.rpc.guildactivity',
            2 => 'batch.rpc.miner-payments',
            3 => 'batch.rpc.batch-checker',
        ],
    ],
    'api-tools-rpc' => [
        'Batch\\V1\\Rpc\\Refstats\\Controller' => [
            'service_name' => 'Refstats',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.refstats',
        ],
        'Batch\\V1\\Rpc\\Guildactivity\\Controller' => [
            'service_name' => 'Guildactivity',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.guildactivity',
        ],
        'Batch\\V1\\Rpc\\MinerPayments\\Controller' => [
            'service_name' => 'MinerPayments',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.miner-payments',
        ],
        'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
            'service_name' => 'BatchChecker',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.batch-checker',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\Guildactivity\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\MinerPayments\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Batch\\V1\\Rpc\\Guildactivity\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Batch\\V1\\Rpc\\MinerPayments\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
            'Batch\\V1\\Rpc\\Guildactivity\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
            'Batch\\V1\\Rpc\\MinerPayments\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
];