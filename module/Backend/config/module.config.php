<?php
return [
    'service_manager' => [
        'factories' => [
            \Backend\V1\Rest\Contest\ContestResource::class => \Backend\V1\Rest\Contest\ContestResourceFactory::class,
            \Backend\V1\Rest\Withdraw\WithdrawResource::class => \Backend\V1\Rest\Withdraw\WithdrawResourceFactory::class,
            \Backend\V1\Rest\TokenBuy\TokenBuyResource::class => \Backend\V1\Rest\TokenBuy\TokenBuyResourceFactory::class,
            \Backend\V1\Rest\TokenStaking\TokenStakingResource::class => \Backend\V1\Rest\TokenStaking\TokenStakingResourceFactory::class,
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
            'backend.rpc.user-stats' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/user-stats',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rpc\\UserStats\\Controller',
                        'action' => 'userStats',
                    ],
                ],
            ],
            'backend.rest.withdraw' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/withdraw[/:withdraw_id]',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rest\\Withdraw\\Controller',
                    ],
                ],
            ],
            'backend.rpc.user-info' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/userinfo',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rpc\\UserInfo\\Controller',
                        'action' => 'userInfo',
                    ],
                ],
            ],
            'backend.rest.token-buy' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/token-buy[/:token_buy_id]',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rest\\TokenBuy\\Controller',
                    ],
                ],
            ],
            'backend.rest.token-staking' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/backend/token-staking[/:token_staking_id]',
                    'defaults' => [
                        'controller' => 'Backend\\V1\\Rest\\TokenStaking\\Controller',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'backend.rest.contest',
            1 => 'backend.rpc.short-earnings',
            2 => 'backend.rpc.user-stats',
            3 => 'backend.rest.withdraw',
            4 => 'backend.rpc.user-info',
            5 => 'backend.rest.token-buy',
            6 => 'backend.rest.token-staking',
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
        'Backend\\V1\\Rest\\Withdraw\\Controller' => [
            'listener' => \Backend\V1\Rest\Withdraw\WithdrawResource::class,
            'route_name' => 'backend.rest.withdraw',
            'route_identifier_name' => 'withdraw_id',
            'collection_name' => 'withdraw',
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
            'entity_class' => \Backend\V1\Rest\Withdraw\WithdrawEntity::class,
            'collection_class' => \Backend\V1\Rest\Withdraw\WithdrawCollection::class,
            'service_name' => 'Withdraw',
        ],
        'Backend\\V1\\Rest\\TokenBuy\\Controller' => [
            'listener' => \Backend\V1\Rest\TokenBuy\TokenBuyResource::class,
            'route_name' => 'backend.rest.token-buy',
            'route_identifier_name' => 'token_buy_id',
            'collection_name' => 'token_buy',
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
            'entity_class' => \Backend\V1\Rest\TokenBuy\TokenBuyEntity::class,
            'collection_class' => \Backend\V1\Rest\TokenBuy\TokenBuyCollection::class,
            'service_name' => 'TokenBuy',
        ],
        'Backend\\V1\\Rest\\TokenStaking\\Controller' => [
            'listener' => \Backend\V1\Rest\TokenStaking\TokenStakingResource::class,
            'route_name' => 'backend.rest.token-staking',
            'route_identifier_name' => 'token_staking_id',
            'collection_name' => 'token_staking',
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
            'entity_class' => \Backend\V1\Rest\TokenStaking\TokenStakingEntity::class,
            'collection_class' => \Backend\V1\Rest\TokenStaking\TokenStakingCollection::class,
            'service_name' => 'TokenStaking',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Backend\\V1\\Rest\\Contest\\Controller' => 'HalJson',
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => 'Json',
            'Backend\\V1\\Rpc\\UserStats\\Controller' => 'Json',
            'Backend\\V1\\Rest\\Withdraw\\Controller' => 'HalJson',
            'Backend\\V1\\Rpc\\UserInfo\\Controller' => 'Json',
            'Backend\\V1\\Rest\\TokenBuy\\Controller' => 'HalJson',
            'Backend\\V1\\Rest\\TokenStaking\\Controller' => 'HalJson',
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
            'Backend\\V1\\Rpc\\UserStats\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Backend\\V1\\Rest\\Withdraw\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Backend\\V1\\Rpc\\UserInfo\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Backend\\V1\\Rest\\TokenBuy\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Backend\\V1\\Rest\\TokenStaking\\Controller' => [
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
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
            'Backend\\V1\\Rpc\\UserStats\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
            'Backend\\V1\\Rest\\Withdraw\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
            'Backend\\V1\\Rpc\\UserInfo\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
            'Backend\\V1\\Rest\\TokenBuy\\Controller' => [
                0 => 'application/vnd.backend.v1+json',
                1 => 'application/json',
            ],
            'Backend\\V1\\Rest\\TokenStaking\\Controller' => [
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
            \Backend\V1\Rest\Withdraw\WithdrawEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.withdraw',
                'route_identifier_name' => 'withdraw_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Backend\V1\Rest\Withdraw\WithdrawCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.withdraw',
                'route_identifier_name' => 'withdraw_id',
                'is_collection' => true,
            ],
            \Backend\V1\Rest\TokenBuy\TokenBuyEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.token-buy',
                'route_identifier_name' => 'token_buy_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Backend\V1\Rest\TokenBuy\TokenBuyCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.token-buy',
                'route_identifier_name' => 'token_buy_id',
                'is_collection' => true,
            ],
            \Backend\V1\Rest\TokenStaking\TokenStakingEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.token-staking',
                'route_identifier_name' => 'token_staking_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Backend\V1\Rest\TokenStaking\TokenStakingCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'backend.rest.token-staking',
                'route_identifier_name' => 'token_staking_id',
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
            'Backend\\V1\\Rpc\\UserStats\\Controller' => [
                'actions' => [
                    'userStats' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Backend\\V1\\Rest\\Withdraw\\Controller' => [
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
            'Backend\\V1\\Rpc\\UserInfo\\Controller' => [
                'actions' => [
                    'userInfo' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Backend\\V1\\Rest\\TokenBuy\\Controller' => [
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
            'Backend\\V1\\Rest\\TokenStaking\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => true,
                    'PUT' => false,
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
    'controllers' => [
        'factories' => [
            'Backend\\V1\\Rpc\\ShortEarnings\\Controller' => \Backend\V1\Rpc\ShortEarnings\ShortEarningsControllerFactory::class,
            'Backend\\V1\\Rpc\\UserStats\\Controller' => \Backend\V1\Rpc\UserStats\UserStatsControllerFactory::class,
            'Backend\\V1\\Rpc\\UserInfo\\Controller' => \Backend\V1\Rpc\UserInfo\UserInfoControllerFactory::class,
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
        'Backend\\V1\\Rpc\\UserStats\\Controller' => [
            'service_name' => 'UserStats',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'backend.rpc.user-stats',
        ],
        'Backend\\V1\\Rpc\\UserInfo\\Controller' => [
            'service_name' => 'UserInfo',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'backend.rpc.user-info',
        ],
    ],
];
