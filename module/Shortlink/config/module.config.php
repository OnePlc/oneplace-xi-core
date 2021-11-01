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
            'shortlink.rpc.complete' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/task/complete[/:token]',
                    'constraints' => [
                        'token' => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Shortlink\\V1\\Rpc\\Complete\\Controller',
                        'action' => 'complete',
                    ],
                ],
            ],
            'shortlink.rpc.history' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/slhistory',
                    'defaults' => [
                        'controller' => 'Shortlink\\V1\\Rpc\\History\\Controller',
                        'action' => 'history',
                    ],
                ],
            ],
            'shortlink.rpc.rating' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/slrating',
                    'defaults' => [
                        'controller' => 'Shortlink\\V1\\Rpc\\Rating\\Controller',
                        'action' => 'rating',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'shortlink.rest.shortlink',
            1 => 'shortlink.rpc.complete',
            2 => 'shortlink.rpc.history',
            3 => 'shortlink.rpc.rating',
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
                2 => 'DELETE',
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
            'Shortlink\\V1\\Rpc\\Complete\\Controller' => 'Json',
            'Shortlink\\V1\\Rpc\\History\\Controller' => 'Json',
            'Shortlink\\V1\\Rpc\\Rating\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Shortlink\\V1\\Rpc\\Complete\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Shortlink\\V1\\Rpc\\History\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Shortlink\\V1\\Rpc\\Rating\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
            ],
            'Shortlink\\V1\\Rpc\\Complete\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
            ],
            'Shortlink\\V1\\Rpc\\History\\Controller' => [
                0 => 'application/vnd.shortlink.v1+json',
                1 => 'application/json',
            ],
            'Shortlink\\V1\\Rpc\\Rating\\Controller' => [
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
                    'DELETE' => true,
                ],
            ],
            'Shortlink\\V1\\Rpc\\History\\Controller' => [
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
            'Shortlink\\V1\\Rpc\\Rating\\Controller' => [
                'actions' => [
                    'rating' => [
                        'GET' => true,
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
            'Shortlink\\V1\\Rpc\\Complete\\Controller' => \Shortlink\V1\Rpc\Complete\CompleteControllerFactory::class,
            'Shortlink\\V1\\Rpc\\History\\Controller' => \Shortlink\V1\Rpc\History\HistoryControllerFactory::class,
            'Shortlink\\V1\\Rpc\\Rating\\Controller' => \Shortlink\V1\Rpc\Rating\RatingControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'Shortlink\\V1\\Rpc\\Complete\\Controller' => [
            'service_name' => 'Complete',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
            ],
            'route_name' => 'shortlink.rpc.complete',
        ],
        'Shortlink\\V1\\Rpc\\History\\Controller' => [
            'service_name' => 'History',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'shortlink.rpc.history',
        ],
        'Shortlink\\V1\\Rpc\\Rating\\Controller' => [
            'service_name' => 'Rating',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
                2 => 'PUT',
            ],
            'route_name' => 'shortlink.rpc.rating',
        ],
    ],
    'api-tools-content-validation' => [
        'Shortlink\\V1\\Rpc\\History\\Controller' => [
            'input_filter' => 'Shortlink\\V1\\Rpc\\History\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'Shortlink\\V1\\Rpc\\History\\Validator' => [],
    ],
];
