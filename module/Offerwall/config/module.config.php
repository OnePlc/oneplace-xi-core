<?php
return [
    'service_manager' => [
        'factories' => [
            \Offerwall\V1\Rest\Offerwall\OfferwallResource::class => \Offerwall\V1\Rest\Offerwall\OfferwallResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'offerwall.rest.offerwall' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/offerwall[/:offerwall_id]',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rest\\Offerwall\\Controller',
                    ],
                ],
            ],
            'offerwall.rpc.ayetstudios' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/ayetstudios',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller',
                        'action' => 'ayetstudios',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'offerwall.rest.offerwall',
            1 => 'offerwall.rpc.ayetstudios',
        ],
    ],
    'api-tools-rest' => [
        'Offerwall\\V1\\Rest\\Offerwall\\Controller' => [
            'listener' => \Offerwall\V1\Rest\Offerwall\OfferwallResource::class,
            'route_name' => 'offerwall.rest.offerwall',
            'route_identifier_name' => 'offerwall_id',
            'collection_name' => 'offerwall',
            'entity_http_methods' => [
                0 => 'GET',
            ],
            'collection_http_methods' => [
                0 => 'GET',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Offerwall\V1\Rest\Offerwall\OfferwallEntity::class,
            'collection_class' => \Offerwall\V1\Rest\Offerwall\OfferwallCollection::class,
            'service_name' => 'Offerwall',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Offerwall\\V1\\Rest\\Offerwall\\Controller' => 'HalJson',
            'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Offerwall\\V1\\Rest\\Offerwall\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Offerwall\\V1\\Rest\\Offerwall\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Offerwall\V1\Rest\Offerwall\OfferwallEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'offerwall.rest.offerwall',
                'route_identifier_name' => 'offerwall_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Offerwall\V1\Rest\Offerwall\OfferwallCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'offerwall.rest.offerwall',
                'route_identifier_name' => 'offerwall_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Offerwall\\V1\\Rest\\Offerwall\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => false,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
                'entity' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => false,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
            ],
            'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller' => [
                'actions' => [
                    'ayetstudios' => [
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
    'api-tools-content-validation' => [
        'Offerwall\\V1\\Rest\\Offerwall\\Controller' => [
            'input_filter' => 'Offerwall\\V1\\Rest\\Offerwall\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'Offerwall\\V1\\Rest\\Offerwall\\Validator' => [
            0 => [
                'required' => false,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'offerwall',
                'description' => 'Offerwall ID',
                'error_message' => 'You must provide a valid offerwall ID',
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller' => \Offerwall\V1\Rpc\Ayetstudios\AyetstudiosControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'Offerwall\\V1\\Rpc\\Ayetstudios\\Controller' => [
            'service_name' => 'Ayetstudios',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.ayetstudios',
        ],
    ],
];
