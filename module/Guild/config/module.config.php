<?php
return [
    'service_manager' => [
        'factories' => [
            \Guild\V1\Rest\Guild\GuildResource::class => \Guild\V1\Rest\Guild\GuildResourceFactory::class,
            \Guild\V1\Rest\Rank\RankResource::class => \Guild\V1\Rest\Rank\RankResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'guild.rest.guild' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/guild[/:guild_id]',
                    'defaults' => [
                        'controller' => 'Guild\\V1\\Rest\\Guild\\Controller',
                    ],
                ],
            ],
            'guild.rpc.bank' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/guildbank',
                    'defaults' => [
                        'controller' => 'Guild\\V1\\Rpc\\Bank\\Controller',
                        'action' => 'bank',
                    ],
                ],
            ],
            'guild.rpc.join' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/guild-joins',
                    'defaults' => [
                        'controller' => 'Guild\\V1\\Rpc\\Join\\Controller',
                        'action' => 'join',
                    ],
                ],
            ],
            'guild.rpc.chat' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/chat',
                    'defaults' => [
                        'controller' => 'Guild\\V1\\Rpc\\Chat\\Controller',
                        'action' => 'chat',
                    ],
                ],
            ],
            'guild.rest.rank' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/guildrank[/:rank_id]',
                    'defaults' => [
                        'controller' => 'Guild\\V1\\Rest\\Rank\\Controller',
                    ],
                ],
            ],
            'guild.rpc.statistics' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/guild-stats',
                    'defaults' => [
                        'controller' => 'Guild\\V1\\Rpc\\Statistics\\Controller',
                        'action' => 'statistics',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'guild.rest.guild',
            1 => 'guild.rpc.bank',
            2 => 'guild.rpc.join',
            3 => 'guild.rpc.chat',
            4 => 'guild.rest.rank',
            5 => 'guild.rpc.statistics',
        ],
    ],
    'api-tools-rest' => [
        'Guild\\V1\\Rest\\Guild\\Controller' => [
            'listener' => \Guild\V1\Rest\Guild\GuildResource::class,
            'route_name' => 'guild.rest.guild',
            'route_identifier_name' => 'guild_id',
            'collection_name' => 'guild',
            'entity_http_methods' => [
                0 => 'GET',
                1 => 'DELETE',
                2 => 'POST',
                3 => 'PUT',
                4 => 'PATCH',
            ],
            'collection_http_methods' => [
                0 => 'GET',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Guild\V1\Rest\Guild\GuildEntity::class,
            'collection_class' => \Guild\V1\Rest\Guild\GuildCollection::class,
            'service_name' => 'Guild',
        ],
        'Guild\\V1\\Rest\\Rank\\Controller' => [
            'listener' => \Guild\V1\Rest\Rank\RankResource::class,
            'route_name' => 'guild.rest.rank',
            'route_identifier_name' => 'rank_id',
            'collection_name' => 'rank',
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
            'entity_class' => \Guild\V1\Rest\Rank\RankEntity::class,
            'collection_class' => \Guild\V1\Rest\Rank\RankCollection::class,
            'service_name' => 'Rank',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => 'HalJson',
            'Guild\\V1\\Rpc\\Bank\\Controller' => 'Json',
            'Guild\\V1\\Rpc\\Join\\Controller' => 'Json',
            'Guild\\V1\\Rpc\\Chat\\Controller' => 'Json',
            'Guild\\V1\\Rest\\Rank\\Controller' => 'HalJson',
            'Guild\\V1\\Rpc\\Statistics\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Guild\\V1\\Rpc\\Bank\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Guild\\V1\\Rpc\\Join\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Guild\\V1\\Rpc\\Chat\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Guild\\V1\\Rest\\Rank\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Guild\\V1\\Rpc\\Statistics\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
            ],
            'Guild\\V1\\Rpc\\Bank\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
            ],
            'Guild\\V1\\Rpc\\Join\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
            ],
            'Guild\\V1\\Rpc\\Chat\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
            ],
            'Guild\\V1\\Rest\\Rank\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
            ],
            'Guild\\V1\\Rpc\\Statistics\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Guild\V1\Rest\Guild\GuildEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'guild.rest.guild',
                'route_identifier_name' => 'guild_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Guild\V1\Rest\Guild\GuildCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'guild.rest.guild',
                'route_identifier_name' => 'guild_id',
                'is_collection' => true,
            ],
            \Guild\V1\Rest\Rank\RankEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'guild.rest.rank',
                'route_identifier_name' => 'rank_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Guild\V1\Rest\Rank\RankCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'guild.rest.rank',
                'route_identifier_name' => 'rank_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-content-validation' => [
        'Guild\\V1\\Rest\\Guild\\Controller' => [
            'input_filter' => 'Guild\\V1\\Rest\\Guild\\Validator',
        ],
        'Guild\\V1\\Rpc\\Bank\\Controller' => [
            'input_filter' => 'Guild\\V1\\Rpc\\Bank\\Validator',
        ],
        'Guild\\V1\\Rpc\\Join\\Controller' => [
            'input_filter' => 'Guild\\V1\\Rpc\\Join\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'Guild\\V1\\Rest\\Guild\\Validator' => [
            0 => [
                'required' => false,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'guild',
                'description' => 'Guild ID',
                'error_message' => 'You must provide a valid Guild ID',
            ],
            1 => [
                'required' => false,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'name',
                'description' => 'Name for a Guild',
                'error_message' => 'You must provide a valid guild name',
            ],
            2 => [
                'required' => false,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'icon',
                'description' => 'Guild Icon',
                'error_message' => 'You must provide a valid guild icon',
            ],
        ],
        'Guild\\V1\\Rpc\\Bank\\Validator' => [
            0 => [
                'required' => false,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'amount',
                'description' => 'Amount to Withdraw or Deposit',
                'error_message' => 'You must provide a valid amount',
            ],
        ],
        'Guild\\V1\\Rpc\\Join\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'user_id',
                'description' => 'ID of User you want to process join',
                'error_message' => 'You must provide a valid User ID',
            ],
            1 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'accept',
                'description' => 'Accept or decline the join request',
                'error_message' => 'You must provide a valid accept command',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => [
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
                    'PUT' => true,
                    'PATCH' => true,
                    'DELETE' => true,
                ],
            ],
            'Guild\\V1\\Rpc\\Bank\\Controller' => [
                'actions' => [
                    'bank' => [
                        'GET' => true,
                        'POST' => true,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Guild\\V1\\Rpc\\Join\\Controller' => [
                'actions' => [
                    'join' => [
                        'GET' => true,
                        'POST' => true,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => true,
                    ],
                ],
            ],
            'Guild\\V1\\Rpc\\Chat\\Controller' => [
                'actions' => [
                    'chat' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Guild\\V1\\Rest\\Rank\\Controller' => [
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
            'Guild\\V1\\Rpc\\Statistics\\Controller' => [
                'actions' => [
                    'statistics' => [
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
    'controllers' => [
        'factories' => [
            'Guild\\V1\\Rpc\\Bank\\Controller' => \Guild\V1\Rpc\Bank\BankControllerFactory::class,
            'Guild\\V1\\Rpc\\Join\\Controller' => \Guild\V1\Rpc\Join\JoinControllerFactory::class,
            'Guild\\V1\\Rpc\\Chat\\Controller' => \Guild\V1\Rpc\Chat\ChatControllerFactory::class,
            'Guild\\V1\\Rpc\\Statistics\\Controller' => \Guild\V1\Rpc\Statistics\StatisticsControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'Guild\\V1\\Rpc\\Bank\\Controller' => [
            'service_name' => 'Bank',
            'http_methods' => [
                0 => 'POST',
                1 => 'PUT',
                2 => 'GET',
            ],
            'route_name' => 'guild.rpc.bank',
        ],
        'Guild\\V1\\Rpc\\Join\\Controller' => [
            'service_name' => 'Join',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
                2 => 'PUT',
                3 => 'DELETE',
            ],
            'route_name' => 'guild.rpc.join',
        ],
        'Guild\\V1\\Rpc\\Chat\\Controller' => [
            'service_name' => 'Chat',
            'http_methods' => [
                0 => 'PUT',
                1 => 'GET',
            ],
            'route_name' => 'guild.rpc.chat',
        ],
        'Guild\\V1\\Rpc\\Statistics\\Controller' => [
            'service_name' => 'Statistics',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'guild.rpc.statistics',
        ],
    ],
];
