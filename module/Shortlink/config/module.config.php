<?php
return [
    'service_manager' => [
        'factories' => [
            \Shortlink\V1\Rest\Shortlink\ShortlinkResource::class => \Shortlink\V1\Rest\Shortlink\ShortlinkResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'shortlink.rest.shortlink' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/shortlink[/:shortlink_id]',
                    'defaults' => [
                        'controller' => 'Shortlink\\V1\\Rest\\Shortlink\\Controller',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'shortlink.rest.shortlink',
        ],
    ],
    'api-tools-rest' => [
        'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
            'listener' => \Shortlink\V1\Rest\Shortlink\ShortlinkResource::class,
            'route_name' => 'shortlink.rest.shortlink',
            'route_identifier_name' => 'shortlink_id',
            'collection_name' => 'shortlink',
            'entity_http_methods' => [
                0 => 'GET',
                1 => 'PUT',
            ],
            'collection_http_methods' => [
                0 => 'GET',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Shortlink\V1\Rest\Shortlink\ShortlinkEntity::class,
            'collection_class' => \Shortlink\V1\Rest\Shortlink\ShortlinkCollection::class,
            'service_name' => 'Shortlink',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Shortlink\\V1\\Rest\\Shortlink\\Controller' => 'HalJson',
        ],
        'accept_whitelist' => [
            'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Shortlink\V1\Rest\Shortlink\ShortlinkEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'shortlink.rest.shortlink',
                'route_identifier_name' => 'shortlink_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Shortlink\V1\Rest\Shortlink\ShortlinkCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'shortlink.rest.shortlink',
                'route_identifier_name' => 'shortlink_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => true,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
                'entity' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => true,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
            ],
        ],
    ],
];
