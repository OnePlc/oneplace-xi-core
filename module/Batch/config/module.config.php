<?php
return [
    'controllers' => [
        'factories' => [
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => \Batch\V1\Rpc\BatchChecker\BatchCheckerControllerFactory::class,
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
            3 => 'batch.rpc.batch-checker',
        ],
    ],
    'api-tools-rpc' => [
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
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Batch\\V1\\Rpc\\BatchChecker\\Controller' => [
                0 => 'application/vnd.batch.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
];
