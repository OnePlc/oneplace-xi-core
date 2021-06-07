<?php
return [
    'controllers' => [
        'factories' => [
            'PTC\\V1\\Rpc\\PTC\\Controller' => 'PTC\\V1\\Rpc\\PTC\\PTCControllerFactory',
            'PTC\\V1\\Rpc\\Deposit\\Controller' => \PTC\V1\Rpc\Deposit\DepositControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'ptc.rest.ptc' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/ptc[/:ptc_id]',
                    'defaults' => [
                        'controller' => 'PTC\\V1\\Rest\\PTC\\Controller',
                    ],
                ],
            ],
            'ptc.rpc.deposit' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/ptcdeposit',
                    'defaults' => [
                        'controller' => 'PTC\\V1\\Rpc\\Deposit\\Controller',
                        'action' => 'deposit',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            1 => 'ptc.rest.ptc',
            0 => 'ptc.rpc.deposit',
        ],
    ],
    'api-tools-rpc' => [
        'PTC\\V1\\Rpc\\Deposit\\Controller' => [
            'service_name' => 'Deposit',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
            ],
            'route_name' => 'ptc.rpc.deposit',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'PTC\\V1\\Rest\\PTC\\Controller' => 'HalJson',
            'PTC\\V1\\Rpc\\Deposit\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'PTC\\V1\\Rest\\PTC\\Controller' => [
                0 => 'application/vnd.ptc.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'PTC\\V1\\Rpc\\Deposit\\Controller' => [
                0 => 'application/vnd.ptc.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'PTC\\V1\\Rest\\PTC\\Controller' => [
                0 => 'application/vnd.ptc.v1+json',
                1 => 'application/json',
            ],
            'PTC\\V1\\Rpc\\Deposit\\Controller' => [
                0 => 'application/vnd.ptc.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'PTC\\V1\\Rpc\\Deposit\\Controller' => [
                'actions' => [
                    'deposit' => [
                        'GET' => true,
                        'POST' => true,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            \PTC\V1\Rest\PTC\PTCResource::class => \PTC\V1\Rest\PTC\PTCResourceFactory::class,
        ],
    ],
    'api-tools-rest' => [
        'PTC\\V1\\Rest\\PTC\\Controller' => [
            'listener' => \PTC\V1\Rest\PTC\PTCResource::class,
            'route_name' => 'ptc.rest.ptc',
            'route_identifier_name' => 'ptc_id',
            'collection_name' => 'ptc',
            'entity_http_methods' => [
                0 => 'GET',
                1 => 'PATCH',
                2 => 'PUT',
                3 => 'DELETE',
            ],
            'collection_http_methods' => [
                0 => 'GET',
                1 => 'POST',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \PTC\V1\Rest\PTC\PTCEntity::class,
            'collection_class' => \PTC\V1\Rest\PTC\PTCCollection::class,
            'service_name' => 'PTC',
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \PTC\V1\Rest\PTC\PTCEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'ptc.rest.ptc',
                'route_identifier_name' => 'ptc_id',
                'hydrator' => \Laminas\Hydrator\ArraySerializable::class,
            ],
            \PTC\V1\Rest\PTC\PTCCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'ptc.rest.ptc',
                'route_identifier_name' => 'ptc_id',
                'is_collection' => true,
            ],
        ],
    ],
];
