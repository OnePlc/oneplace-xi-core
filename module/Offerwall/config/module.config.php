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
            'offerwall.rpc.mediumpath' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/mediumpath',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\Mediumpath\\Controller',
                        'action' => 'mediumpath',
                    ],
                ],
            ],
            'offerwall.rpc.rating' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/owrating',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\Rating\\Controller',
                        'action' => 'rating',
                    ],
                ],
            ],
            'offerwall.rpc.ayet-pb' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/ayet-pb',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\AyetPB\\Controller',
                        'action' => 'ayetPB',
                    ],
                ],
            ],
            'offerwall.rpc.cpx-pb' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/cpx-pb',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\CpxPB\\Controller',
                        'action' => 'cpxPB',
                    ],
                ],
            ],
            'offerwall.rpc.wanna-pb' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/wanna-pb',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\WannaPB\\Controller',
                        'action' => 'wannaPB',
                    ],
                ],
            ],
            'offerwall.rpc.persona-pb' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/persona-pb',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\PersonaPB\\Controller',
                        'action' => 'personaPB',
                    ],
                ],
            ],
            'offerwall.rpc.bitlabs-pb' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/bitlabs-pb',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\BitlabsPB\\Controller',
                        'action' => 'bitlabsPB',
                    ],
                ],
            ],
            'offerwall.rpc.lootably' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/loot-pb',
                    'defaults' => [
                        'controller' => 'Offerwall\\V1\\Rpc\\Lootably\\Controller',
                        'action' => 'lootably',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'offerwall.rest.offerwall',
            1 => 'offerwall.rpc.ayetstudios',
            2 => 'offerwall.rpc.mediumpath',
            3 => 'offerwall.rpc.rating',
            4 => 'offerwall.rpc.ayet-pb',
            5 => 'offerwall.rpc.cpx-pb',
            6 => 'offerwall.rpc.wanna-pb',
            7 => 'offerwall.rpc.persona-pb',
            8 => 'offerwall.rpc.bitlabs-pb',
            9 => 'offerwall.rpc.lootably',
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
            'Offerwall\\V1\\Rpc\\Mediumpath\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\Rating\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\AyetPB\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\CpxPB\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\WannaPB\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\PersonaPB\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\BitlabsPB\\Controller' => 'Json',
            'Offerwall\\V1\\Rpc\\Lootably\\Controller' => 'Json',
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
            'Offerwall\\V1\\Rpc\\Mediumpath\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\Rating\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\AyetPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\CpxPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\WannaPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\PersonaPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\BitlabsPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Offerwall\\V1\\Rpc\\Lootably\\Controller' => [
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
            'Offerwall\\V1\\Rpc\\Mediumpath\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\Rating\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\AyetPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\CpxPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\WannaPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\PersonaPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\BitlabsPB\\Controller' => [
                0 => 'application/vnd.offerwall.v1+json',
                1 => 'application/json',
            ],
            'Offerwall\\V1\\Rpc\\Lootably\\Controller' => [
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
            'Offerwall\\V1\\Rpc\\Mediumpath\\Controller' => [
                'actions' => [
                    'mediumpath' => [
                        'GET' => false,
                        'POST' => true,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Offerwall\\V1\\Rpc\\Rating\\Controller' => [
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
            'Offerwall\\V1\\Rpc\\PersonaPB\\Controller' => [
                'actions' => [
                    'personaPB' => [
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
            'Offerwall\\V1\\Rpc\\Mediumpath\\Controller' => \Offerwall\V1\Rpc\Mediumpath\MediumpathControllerFactory::class,
            'Offerwall\\V1\\Rpc\\Rating\\Controller' => \Offerwall\V1\Rpc\Rating\RatingControllerFactory::class,
            'Offerwall\\V1\\Rpc\\AyetPB\\Controller' => \Offerwall\V1\Rpc\AyetPB\AyetPBControllerFactory::class,
            'Offerwall\\V1\\Rpc\\CpxPB\\Controller' => \Offerwall\V1\Rpc\CpxPB\CpxPBControllerFactory::class,
            'Offerwall\\V1\\Rpc\\WannaPB\\Controller' => \Offerwall\V1\Rpc\WannaPB\WannaPBControllerFactory::class,
            'Offerwall\\V1\\Rpc\\PersonaPB\\Controller' => \Offerwall\V1\Rpc\PersonaPB\PersonaPBControllerFactory::class,
            'Offerwall\\V1\\Rpc\\BitlabsPB\\Controller' => \Offerwall\V1\Rpc\BitlabsPB\BitlabsPBControllerFactory::class,
            'Offerwall\\V1\\Rpc\\Lootably\\Controller' => \Offerwall\V1\Rpc\Lootably\LootablyControllerFactory::class,
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
        'Offerwall\\V1\\Rpc\\Mediumpath\\Controller' => [
            'service_name' => 'Mediumpath',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
            ],
            'route_name' => 'offerwall.rpc.mediumpath',
        ],
        'Offerwall\\V1\\Rpc\\Rating\\Controller' => [
            'service_name' => 'Rating',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
                2 => 'PUT',
            ],
            'route_name' => 'offerwall.rpc.rating',
        ],
        'Offerwall\\V1\\Rpc\\AyetPB\\Controller' => [
            'service_name' => 'AyetPB',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.ayet-pb',
        ],
        'Offerwall\\V1\\Rpc\\CpxPB\\Controller' => [
            'service_name' => 'CpxPB',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.cpx-pb',
        ],
        'Offerwall\\V1\\Rpc\\WannaPB\\Controller' => [
            'service_name' => 'WannaPB',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.wanna-pb',
        ],
        'Offerwall\\V1\\Rpc\\PersonaPB\\Controller' => [
            'service_name' => 'PersonaPB',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.persona-pb',
        ],
        'Offerwall\\V1\\Rpc\\BitlabsPB\\Controller' => [
            'service_name' => 'BitlabsPB',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.bitlabs-pb',
        ],
        'Offerwall\\V1\\Rpc\\Lootably\\Controller' => [
            'service_name' => 'Lootably',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'offerwall.rpc.lootably',
        ],
    ],
];
