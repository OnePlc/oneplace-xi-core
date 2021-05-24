<?php
return [
    'controllers' => [
        'factories' => [
            'Mining\\V1\\Rpc\\History\\Controller' => \Mining\V1\Rpc\History\HistoryControllerFactory::class,
            'Mining\\V1\\Rpc\\Download\\Controller' => \Mining\V1\Rpc\Download\DownloadControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'mining.rpc.history' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/mining/history',
                    'defaults' => [
                        'controller' => 'Mining\\V1\\Rpc\\History\\Controller',
                        'action' => 'history',
                    ],
                ],
            ],
            'mining.rpc.download' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/miner/download',
                    'defaults' => [
                        'controller' => 'Mining\\V1\\Rpc\\Download\\Controller',
                        'action' => 'download',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'mining.rpc.history',
            1 => 'mining.rpc.download',
        ],
    ],
    'api-tools-rpc' => [
        'Mining\\V1\\Rpc\\History\\Controller' => [
            'service_name' => 'History',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'mining.rpc.history',
        ],
        'Mining\\V1\\Rpc\\Download\\Controller' => [
            'service_name' => 'Download',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'mining.rpc.download',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Mining\\V1\\Rpc\\History\\Controller' => 'Json',
            'Mining\\V1\\Rpc\\Download\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Mining\\V1\\Rpc\\History\\Controller' => [
                0 => 'application/vnd.mining.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Mining\\V1\\Rpc\\Download\\Controller' => [
                0 => 'application/vnd.mining.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Mining\\V1\\Rpc\\History\\Controller' => [
                0 => 'application/vnd.mining.v1+json',
                1 => 'application/json',
            ],
            'Mining\\V1\\Rpc\\Download\\Controller' => [
                0 => 'application/vnd.mining.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Mining\\V1\\Rpc\\History\\Controller' => [
                'actions' => [
                    'history' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Mining\\V1\\Rpc\\Download\\Controller' => [
                'actions' => [
                    'download' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
        ],
    ],
];
