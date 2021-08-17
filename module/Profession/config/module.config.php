<?php
return [
    'service_manager' => [
        'factories' => [
            \Profession\V1\Rest\Professions\ProfessionsResource::class => \Profession\V1\Rest\Professions\ProfessionsResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'profession.rest.professions' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/professions[/:professions_id]',
                    'defaults' => [
                        'controller' => 'Profession\\V1\\Rest\\Professions\\Controller',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'profession.rest.professions',
        ],
    ],
    'api-tools-rest' => [
        'Profession\\V1\\Rest\\Professions\\Controller' => [
            'listener' => \Profession\V1\Rest\Professions\ProfessionsResource::class,
            'route_name' => 'profession.rest.professions',
            'route_identifier_name' => 'professions_id',
            'collection_name' => 'professions',
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
            'entity_class' => \Profession\V1\Rest\Professions\ProfessionsEntity::class,
            'collection_class' => \Profession\V1\Rest\Professions\ProfessionsCollection::class,
            'service_name' => 'Professions',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Profession\\V1\\Rest\\Professions\\Controller' => 'HalJson',
        ],
        'accept_whitelist' => [
            'Profession\\V1\\Rest\\Professions\\Controller' => [
                0 => 'application/vnd.profession.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'Profession\\V1\\Rest\\Professions\\Controller' => [
                0 => 'application/vnd.profession.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Profession\V1\Rest\Professions\ProfessionsEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'profession.rest.professions',
                'route_identifier_name' => 'professions_id',
                'hydrator' => \Laminas\Hydrator\ArraySerializable::class,
            ],
            \Profession\V1\Rest\Professions\ProfessionsCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'profession.rest.professions',
                'route_identifier_name' => 'professions_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Profession\\V1\\Rest\\Professions\\Controller' => [
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
        ],
    ],
];
