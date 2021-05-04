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
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'guild.rest.guild',
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
                1 => 'PATCH',
                2 => 'PUT',
                3 => 'DELETE',
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
        ],
        'accept_whitelist' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => [
                0 => 'application/vnd.guild.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'Guild\\V1\\Rest\\Guild\\Controller' => [
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
        ],
    ],
];
