<?php
return [
    'controllers' => [
        'factories' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => \Batch\V1\Rpc\Refstats\RefstatsControllerFactory::class,
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
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'batch.rpc.refstats',
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
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Batch\\V1\\Rpc\\Refstats\\Controller' => [
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
        ],
    ],
];
