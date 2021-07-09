<?php
return [
    'controllers' => [
        'factories' => [
            'News\\V1\\Rpc\\News\\Controller' => \News\V1\Rpc\News\NewsControllerFactory::class,
            'News\\V1\\Rpc\\Newsletter\\Controller' => \News\V1\Rpc\Newsletter\NewsletterControllerFactory::class,
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
            'news.rpc.newsletter' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/newsletterbatch',
                    'defaults' => [
                        'controller' => 'News\\V1\\Rpc\\Newsletter\\Controller',
                        'action' => 'newsletter',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'news.rpc.news',
            1 => 'news.rpc.newsletter',
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
        'News\\V1\\Rpc\\Newsletter\\Controller' => [
            'service_name' => 'Newsletter',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'news.rpc.newsletter',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'News\\V1\\Rpc\\News\\Controller' => 'Json',
            'News\\V1\\Rpc\\Newsletter\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'News\\V1\\Rpc\\News\\Controller' => [
                0 => 'application/vnd.news.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'News\\V1\\Rpc\\Newsletter\\Controller' => [
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
            'News\\V1\\Rpc\\Newsletter\\Controller' => [
                0 => 'application/vnd.news.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'News\\V1\\Rpc\\Newsletter\\Controller' => [
                'actions' => [
                    'newsletter' => [
                        'GET' => false,
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
