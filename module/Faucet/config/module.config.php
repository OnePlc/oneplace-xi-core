<?php
return [
    'controllers' => [
        'factories' => [
            'Faucet\\V1\\Rpc\\Claim\\Controller' => \Faucet\V1\Rpc\Claim\ClaimControllerFactory::class,
            'Faucet\\V1\\Rpc\\Referral\\Controller' => \Faucet\V1\Rpc\Referral\ReferralControllerFactory::class,
            'Faucet\\V1\\Rpc\\HallOfFame\\Controller' => \Faucet\V1\Rpc\HallOfFame\HallOfFameControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'faucet.rpc.claim' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/claim',
                    'defaults' => [
                        'controller' => 'Faucet\\V1\\Rpc\\Claim\\Controller',
                        'action' => 'claim',
                    ],
                ],
            ],
            'faucet.rest.dailytask' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/dailytask[/:dailytask_id]',
                    'defaults' => [
                        'controller' => 'Faucet\\V1\\Rest\\Dailytask\\Controller',
                    ],
                ],
            ],
            'faucet.rest.achievement' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/achievement[/:achievement_id]',
                    'defaults' => [
                        'controller' => 'Faucet\\V1\\Rest\\Achievement\\Controller',
                    ],
                ],
            ],
            'faucet.rpc.referral' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/referral',
                    'defaults' => [
                        'controller' => 'Faucet\\V1\\Rpc\\Referral\\Controller',
                        'action' => 'referral',
                    ],
                ],
            ],
            'faucet.rpc.hall-of-fame' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/statistics/halloffame',
                    'defaults' => [
                        'controller' => 'Faucet\\V1\\Rpc\\HallOfFame\\Controller',
                        'action' => 'hallOfFame',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'faucet.rpc.claim',
            1 => 'faucet.rest.dailytask',
            2 => 'faucet.rest.achievement',
            3 => 'faucet.rpc.referral',
            4 => 'faucet.rpc.hall-of-fame',
        ],
    ],
    'api-tools-rpc' => [
        'Faucet\\V1\\Rpc\\Claim\\Controller' => [
            'service_name' => 'Claim',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
            ],
            'route_name' => 'faucet.rpc.claim',
        ],
        'Faucet\\V1\\Rpc\\Referral\\Controller' => [
            'service_name' => 'Referral',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'faucet.rpc.referral',
        ],
        'Faucet\\V1\\Rpc\\HallOfFame\\Controller' => [
            'service_name' => 'HallOfFame',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'faucet.rpc.hall-of-fame',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Faucet\\V1\\Rpc\\Claim\\Controller' => 'Json',
            'Faucet\\V1\\Rest\\Dailytask\\Controller' => 'HalJson',
            'Faucet\\V1\\Rest\\Achievement\\Controller' => 'HalJson',
            'Faucet\\V1\\Rpc\\Referral\\Controller' => 'Json',
            'Faucet\\V1\\Rpc\\HallOfFame\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Faucet\\V1\\Rpc\\Claim\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Faucet\\V1\\Rest\\Dailytask\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Faucet\\V1\\Rest\\Achievement\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Faucet\\V1\\Rpc\\Referral\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Faucet\\V1\\Rpc\\HallOfFame\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Faucet\\V1\\Rpc\\Claim\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
            ],
            'Faucet\\V1\\Rest\\Dailytask\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
            ],
            'Faucet\\V1\\Rest\\Achievement\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
            ],
            'Faucet\\V1\\Rpc\\Referral\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
            ],
            'Faucet\\V1\\Rpc\\HallOfFame\\Controller' => [
                0 => 'application/vnd.faucet.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Faucet\\V1\\Rpc\\Claim\\Controller' => [
                'actions' => [
                    'claim' => [
                        'GET' => true,
                        'POST' => true,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Faucet\\V1\\Rest\\Dailytask\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => true,
                    'PUT' => false,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
                'entity' => [
                    'GET' => true,
                    'POST' => true,
                    'PUT' => false,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
            ],
            'Faucet\\V1\\Rest\\Achievement\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => false,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
                'entity' => [
                    'GET' => false,
                    'POST' => false,
                    'PUT' => true,
                    'PATCH' => false,
                    'DELETE' => false,
                ],
            ],
            'Faucet\\V1\\Rpc\\Referral\\Controller' => [
                'actions' => [
                    'referral' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Faucet\\V1\\Rpc\\HallOfFame\\Controller' => [
                'actions' => [
                    'hallOfFame' => [
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
    'service_manager' => [
        'factories' => [
            \Faucet\V1\Rest\Dailytask\DailytaskResource::class => \Faucet\V1\Rest\Dailytask\DailytaskResourceFactory::class,
            \Faucet\V1\Rest\Achievement\AchievementResource::class => \Faucet\V1\Rest\Achievement\AchievementResourceFactory::class,
        ],
    ],
    'api-tools-rest' => [
        'Faucet\\V1\\Rest\\Dailytask\\Controller' => [
            'listener' => \Faucet\V1\Rest\Dailytask\DailytaskResource::class,
            'route_name' => 'faucet.rest.dailytask',
            'route_identifier_name' => 'dailytask_id',
            'collection_name' => 'dailytask',
            'entity_http_methods' => [
                0 => 'PUT',
            ],
            'collection_http_methods' => [
                0 => 'GET',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Faucet\V1\Rest\Dailytask\DailytaskEntity::class,
            'collection_class' => \Faucet\V1\Rest\Dailytask\DailytaskCollection::class,
            'service_name' => 'Dailytask',
        ],
        'Faucet\\V1\\Rest\\Achievement\\Controller' => [
            'listener' => \Faucet\V1\Rest\Achievement\AchievementResource::class,
            'route_name' => 'faucet.rest.achievement',
            'route_identifier_name' => 'achievement_id',
            'collection_name' => 'achievement',
            'entity_http_methods' => [
                0 => 'PUT',
            ],
            'collection_http_methods' => [
                0 => 'GET',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Faucet\V1\Rest\Achievement\AchievementEntity::class,
            'collection_class' => \Faucet\V1\Rest\Achievement\AchievementCollection::class,
            'service_name' => 'Achievement',
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Faucet\V1\Rest\Dailytask\DailytaskEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'faucet.rest.dailytask',
                'route_identifier_name' => 'dailytask_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Faucet\V1\Rest\Dailytask\DailytaskCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'faucet.rest.dailytask',
                'route_identifier_name' => 'dailytask_id',
                'is_collection' => true,
            ],
            \Faucet\V1\Rest\Achievement\AchievementEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'faucet.rest.achievement',
                'route_identifier_name' => 'achievement_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Faucet\V1\Rest\Achievement\AchievementCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'faucet.rest.achievement',
                'route_identifier_name' => 'achievement_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-content-validation' => [
        'Faucet\\V1\\Rest\\Dailytask\\Controller' => [
            'input_filter' => 'Faucet\\V1\\Rest\\Dailytask\\Validator',
        ],
        'Faucet\\V1\\Rest\\Achievement\\Controller' => [
            'input_filter' => 'Faucet\\V1\\Rest\\Achievement\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'Faucet\\V1\\Rest\\Dailytask\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [
                    0 => [
                        'name' => \Laminas\Validator\Regex::class,
                        'options' => [
                            'pattern' => '/^(website|app)$/',
                        ],
                    ],
                ],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'platform',
                'description' => 'The Platform for the Dailytasks',
                'error_message' => 'You must provide a valid platform',
            ],
        ],
        'Faucet\\V1\\Rest\\Achievement\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [
                    0 => [
                        'name' => \Laminas\Validator\Regex::class,
                        'options' => [
                            'pattern' => '/^(website|app)$/',
                        ],
                    ],
                ],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'platform',
                'description' => 'The Platform for the Dailytasks',
                'error_message' => 'You must provide a valid platform',
            ],
        ],
    ],
];
