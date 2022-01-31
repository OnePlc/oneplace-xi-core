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
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'backend.rest.contest',
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
        ],
        'accept_whitelist' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => [
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
        ],
    ],
];
