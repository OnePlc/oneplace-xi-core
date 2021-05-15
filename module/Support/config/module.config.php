<?php
return [
    'controllers' => [
        'factories' => [
            'Support\\V1\\Rpc\\Support\\Controller' => \Support\V1\Rpc\Support\SupportControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'support.rpc.support' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/support',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\Support\\Controller',
                        'action' => 'support',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'support.rpc.support',
        ],
        'default_version' => 1,
    ],
    'api-tools-rpc' => [
        'Support\\V1\\Rpc\\Support\\Controller' => [
            'service_name' => 'Support',
            'http_methods' => [
                0 => 'GET',
                1 => 'PUT',
            ],
            'route_name' => 'support.rpc.support',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Support\\V1\\Rpc\\Support\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Support\\V1\\Rpc\\Support\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Support\\V1\\Rpc\\Support\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Support\\V1\\Rpc\\Support\\Controller' => [
                'actions' => [
                    'support' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
        ],
    ],
];
