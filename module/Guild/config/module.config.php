<?php
return [
    'service_manager' => [
        'factories' => [
            \Guild\V1\Rest\Guild\GuildResource::class => \Guild\V1\Rest\Guild\GuildResourceFactory::class,
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
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'guild.rest.guild',
            1 => 'guild.rpc.bank',
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
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => 'HalJson',
            'Guild\\V1\\Rpc\\Bank\\Controller' => 'Json',
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
        ],
    ],
    'api-tools-content-validation' => [
        'Guild\\V1\\Rest\\Guild\\Controller' => [
            'input_filter' => 'Guild\\V1\\Rest\\Guild\\Validator',
        ],
        'Guild\\V1\\Rpc\\Bank\\Controller' => [
            'input_filter' => 'Guild\\V1\\Rpc\\Bank\\Validator',
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
        ],
    ],
    'controllers' => [
        'factories' => [
            'Guild\\V1\\Rpc\\Bank\\Controller' => \Guild\V1\Rpc\Bank\BankControllerFactory::class,
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
    ],
];
