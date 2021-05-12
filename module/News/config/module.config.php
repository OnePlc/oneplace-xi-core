<?php
return [
    'controllers' => [
        'factories' => [
            'News\\V1\\Rpc\\News\\Controller' => \News\V1\Rpc\News\NewsControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'news.rpc.news' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/news',
                    'defaults' => [
                        'controller' => 'News\\V1\\Rpc\\News\\Controller',
                        'action' => 'news',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'news.rpc.news',
        ],
    ],
    'api-tools-rpc' => [
        'News\\V1\\Rpc\\News\\Controller' => [
            'service_name' => 'News',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'news.rpc.news',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'News\\V1\\Rpc\\News\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'News\\V1\\Rpc\\News\\Controller' => [
                0 => 'application/vnd.news.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'News\\V1\\Rpc\\News\\Controller' => [
                0 => 'application/vnd.news.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
];
