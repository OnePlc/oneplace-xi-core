<?php
return [
    'service_manager' => [
        'factories' => [
            \Backend\V1\Rest\Contest\ContestResource::class => \Backend\V1\Rest\Contest\ContestResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'backend.rest.contest' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/contest[/:contest_id]',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rest\\Contest\\Controller',
                    ],
                ],
            ],
            'backend.rpc.short-earnings' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/shearn',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rpc\\ShortEarnings\\Controller',
                        'action' => 'shortEarnings',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'backend.rest.contest',
            1 => 'backend.rpc.short-earnings',
        ],
    ],
    'api-tools-rest' => [
        'Backend\\V1\\Rest\\Contest\\Controller' => [
            'listener' => \Backend\V1\Rest\Contest\ContestResource::class,
            'route_name' => 'backend.rest.contest',
            'route_identifier_name' => 'contest_id',
            'collection_name' => 'contest',
            'entity_http_methods' => [
                0 => 'GET',
                1 => 'PATCH',
                2 => 'PUT',
                3 => 'DELETE',
            ],
            'collection_http_methods' => [
                0 => 'GET',
                1 => 'POST',
                2 => 'PUT',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Backend\V1\Rest\Contest\ContestEntity::class,
            'collection_class' => \Backend\V1\Rest\Contest\ContestCollection::class,
            'service_name' => 'Contest',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => 'HalJson',
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Backend\V1\Rest\Contest\ContestEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.contest',
                'route_identifier_name' => 'contest_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Backend\V1\Rest\Contest\ContestCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.contest',
                'route_identifier_name' => 'contest_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => true,
                    'PUT' => true,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
                'entity' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => true,
                    'PATCH' => true,
                    'DELETE' => true,
                ],
            ],
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => [
                'actions' => [
                    'shortEarnings' => [
                        'GET' => false,
                        'POST' => true,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => \Backend\V1\Rpc\ShortEarnings\ShortEarningsControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => [
            'service_name' => 'ShortEarnings',
            'http_methods' => [
                0 => 'POST',
                1 => 'PUT',
            ],
            'route_name' => 'backend.rpc.short-earnings',
        ],
    ],
];
