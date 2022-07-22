<?php
return [
    'service_manager' => [
        'factories' => [
            \Feedback\V1\Rest\Feedback\FeedbackResource::class => \Feedback\V1\Rest\Feedback\FeedbackResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'feedback.rest.feedback' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/feedback[/:feedback_id]',
                    'defaults' => [
                        'controller' => 'Feedback\\V1\\Rest\\Feedback\\Controller',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'feedback.rest.feedback',
        ],
    ],
    'api-tools-rest' => [
        'Feedback\\V1\\Rest\\Feedback\\Controller' => [
            'listener' => \Feedback\V1\Rest\Feedback\FeedbackResource::class,
            'route_name' => 'feedback.rest.feedback',
            'route_identifier_name' => 'feedback_id',
            'collection_name' => 'feedback',
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
            'entity_class' => \Feedback\V1\Rest\Feedback\FeedbackEntity::class,
            'collection_class' => \Feedback\V1\Rest\Feedback\FeedbackCollection::class,
            'service_name' => 'Feedback',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Feedback\\V1\\Rest\\Feedback\\Controller' => 'HalJson',
        ],
        'accept_whitelist' => [
            'Feedback\\V1\\Rest\\Feedback\\Controller' => [
                0 => 'application/vnd.feedback.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
        ],
        'content_type_whitelist' => [
            'Feedback\\V1\\Rest\\Feedback\\Controller' => [
                0 => 'application/vnd.feedback.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Feedback\V1\Rest\Feedback\FeedbackEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'feedback.rest.feedback',
                'route_identifier_name' => 'feedback_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \Feedback\V1\Rest\Feedback\FeedbackCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'feedback.rest.feedback',
                'route_identifier_name' => 'feedback_id',
                'is_collection' => true,
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Feedback\\V1\\Rest\\Feedback\\Controller' => [
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
