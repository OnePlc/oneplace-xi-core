<?php
return [
    'service_manager' => [
        'aliases'   => [
            'Laminas\ApiTools\ApiProblem\ApiProblemListener'  => 'Laminas\ApiTools\ApiProblem\Listener\ApiProblemListener',
            'Laminas\ApiTools\ApiProblem\RenderErrorListener' => 'Laminas\ApiTools\ApiProblem\Listener\RenderErrorListener',
            'Laminas\ApiTools\ApiProblem\ApiProblemRenderer'  => 'Laminas\ApiTools\ApiProblem\View\ApiProblemRenderer',
            'Laminas\ApiTools\ApiProblem\ApiProblemStrategy'  => 'Laminas\ApiTools\ApiProblem\View\ApiProblemStrategy',
        ],
        'factories' => [
            \User\V1\Rest\User\UserResource::class => \User\V1\Rest\User\UserResourceFactory::class,
            'Laminas\ApiTools\ApiProblem\Listener\ApiProblemListener'             => 'Laminas\ApiTools\ApiProblem\Factory\ApiProblemListenerFactory',
            'Laminas\ApiTools\ApiProblem\Listener\RenderErrorListener'            => 'Laminas\ApiTools\ApiProblem\Factory\RenderErrorListenerFactory',
            'Laminas\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener' => 'Laminas\ApiTools\ApiProblem\Factory\SendApiProblemResponseListenerFactory',
            'Laminas\ApiTools\ApiProblem\View\ApiProblemRenderer'                 => 'Laminas\ApiTools\ApiProblem\Factory\ApiProblemRendererFactory',
            'Laminas\ApiTools\ApiProblem\View\ApiProblemStrategy'                 => 'Laminas\ApiTools\ApiProblem\Factory\ApiProblemStrategyFactory',
        ],
    ],
    'view_manager' => [
        // Enable this in your application configuration in order to get full
        // exception stack traces in your API-Problem responses.
        'display_exceptions' => false,
    ],
    'router' => [
        'routes' => [
            'user.rest.user' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/user[/:user_id]',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rest\\User\\Controller',
                    ],
                ],
            ],
            'user.rpc.login' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/login',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Login\\Controller',
                        'action' => 'login',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'user.rest.user',
            1 => 'user.rpc.login',
        ],
    ],
    'api-tools-rest' => [
        'User\\V1\\Rest\\User\\Controller' => [
            'listener' => \User\V1\Rest\User\UserResource::class,
            'route_name' => 'user.rest.user',
            'route_identifier_name' => 'user_id',
            'collection_name' => 'user',
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
            'entity_class' => \User\V1\Rest\User\UserEntity::class,
            'collection_class' => \User\V1\Rest\User\UserCollection::class,
            'service_name' => 'User',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'User\\V1\\Rest\\User\\Controller' => 'HalJson',
            'User\\V1\\Rpc\\Login\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'User\\V1\\Rest\\User\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'User\\V1\\Rpc\\Login\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'User\\V1\\Rest\\User\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
            'User\\V1\\Rpc\\Login\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \User\V1\Rest\User\UserEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'user.rest.user',
                'route_identifier_name' => 'user_id',
                'hydrator' => \Laminas\Hydrator\ObjectPropertyHydrator::class,
            ],
            \User\V1\Rest\User\UserCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'user.rest.user',
                'route_identifier_name' => 'user_id',
                'is_collection' => true,
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'User\\V1\\Rpc\\Login\\Controller' => \User\V1\Rpc\Login\LoginControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'User\\V1\\Rpc\\Login\\Controller' => [
            'service_name' => 'Login',
            'http_methods' => [
                0 => 'POST',
            ],
            'route_name' => 'user.rpc.login',
        ],
    ],
    'api-tools-content-validation' => [
        'User\\V1\\Rpc\\Login\\Controller' => [
            'input_filter' => 'User\\V1\\Rpc\\Login\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'User\\V1\\Rpc\\Login\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [
                    0 => [
                        'name' => \Laminas\Validator\StringLength::class,
                        'options' => [
                            'min' => '3',
                            'max' => '100',
                        ],
                    ],
                ],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'username',
                'description' => 'Username for User you want to login',
                'error_message' => 'A valid username must only contain letters, numbers and _',
            ],
            1 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'password',
                'description' => 'Password for the user',
                'error_message' => 'A valid password in cleartext.',
            ],
        ],
    ],
];
