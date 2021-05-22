<?php
return [
    'controllers' => [
        'factories' => [
            'Mining\\V1\\Rpc\\History\\Controller' => \Mining\V1\Rpc\History\HistoryControllerFactory::class,
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
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'mining.rpc.history',
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
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Mining\\V1\\Rpc\\History\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Mining\\V1\\Rpc\\History\\Controller' => [
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
        ],
    ],
];
