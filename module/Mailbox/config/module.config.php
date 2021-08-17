<?php
return [
    'service_manager' => [
        'factories' => [
            \Mailbox\V1\Rest\Inbox\InboxResource::class => \Mailbox\V1\Rest\Inbox\InboxResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'mailbox.rest.inbox' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/inbox[/:inbox_id]',
                    'defaults' => [
                        'controller' => 'Mailbox\\V1\\Rest\\Inbox\\Controller',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'mailbox.rest.inbox',
        ],
    ],
    'api-tools-rest' => [
        'Mailbox\\V1\\Rest\\Inbox\\Controller' => [
            'listener' => \Mailbox\V1\Rest\Inbox\InboxResource::class,
            'route_name' => 'mailbox.rest.inbox',
            'route_identifier_name' => 'inbox_id',
            'collection_name' => 'inbox',
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
            'entity_class' => \Mailbox\V1\Rest\Inbox\InboxEntity::class,
            'collection_class' => \Mailbox\V1\Rest\Inbox\InboxCollection::class,
            'service_name' => 'Inbox',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Mailbox\\V1\\Rest\\Inbox\\Controller' => 'HalJson',
        ],
        'accept_whitelist' => [
            'Mailbox\\V1\\Rest\\Inbox\\Controller' => [
                0 => 'application/vnd.mailbox.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'Mailbox\\V1\\Rest\\Inbox\\Controller' => [
                0 => 'application/vnd.mailbox.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Mailbox\V1\Rest\Inbox\InboxEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'mailbox.rest.inbox',
                'route_identifier_name' => 'inbox_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Mailbox\V1\Rest\Inbox\InboxCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'mailbox.rest.inbox',
                'route_identifier_name' => 'inbox_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Mailbox\\V1\\Rest\\Inbox\\Controller' => [
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
];
