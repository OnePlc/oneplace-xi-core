<?php
return [
    'controllers' => [
        'factories' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => \Batch\V1\Rpc\Refstats\RefstatsControllerFactory::class,
            'Batch\\V1\\Rpc\\Guildactivity\\Controller' => \Batch\V1\Rpc\Guildactivity\GuildactivityControllerFactory::class,
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => \Batch\V1\Rpc\BatchChecker\BatchCheckerControllerFactory::class,
            'Batch\\V1\\Rpc\\OfferwallStats\\Controller' => \Batch\V1\Rpc\OfferwallStats\OfferwallStatsControllerFactory::class,
            'Batch\\V1\\Rpc\\Migrate\\Controller' => \Batch\V1\Rpc\Migrate\MigrateControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'display_exceptions' => false,
        'template_path_stack' => [
            'batch' => 'C:\\Users\\Praesidiarius\\PhpstormProjects\\oneplace-xi-core\\module\\Batch\\config/../view',
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
            'batch.rpc.offerwall-stats' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/batch/offerwall-stats',
                    'defaults' => [
                        'controller' => 'Batch\\V1\\Rpc\\OfferwallStats\\Controller',
                        'action' => 'offerwallStats',
                    ],
                ],
            ],
            'batch.rpc.migrate' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/batch/migrate',
                    'defaults' => [
                        'controller' => 'Batch\\V1\\Rpc\\Migrate\\Controller',
                        'action' => 'migrate',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'batch.rpc.refstats',
            1 => 'batch.rpc.guildactivity',
            3 => 'batch.rpc.batch-checker',
            5 => 'batch.rpc.offerwall-stats',
            6 => 'batch.rpc.migrate',
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
        'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
            'service_name' => 'BatchChecker',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.batch-checker',
        ],
        'Batch\\V1\\Rpc\\OfferwallStats\\Controller' => [
            'service_name' => 'OfferwallStats',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.offerwall-stats',
        ],
        'Batch\\V1\\Rpc\\Migrate\\Controller' => [
            'service_name' => 'Migrate',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'batch.rpc.migrate',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\Guildactivity\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\OfferwallStats\\Controller' => 'Json',
            'Batch\\V1\\Rpc\\Migrate\\Controller' => 'Json',
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
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Batch\\V1\\Rpc\\OfferwallStats\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Batch\\V1\\Rpc\\Migrate\\Controller' => [
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
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
            'Batch\\V1\\Rpc\\OfferwallStats\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
            'Batch\\V1\\Rpc\\Migrate\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
];
