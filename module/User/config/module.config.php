<?php
return [
    'service_manager' => [
        'factories' => [
            \User\V1\Rest\User\UserResource::class => \User\V1\Rest\User\UserResourceFactory::class,
        ],
    ],
    'view_manager' => [
        'display_exceptions' => false,
        'template_path_stack' => [
            'user' => 'C:\\Users\\Praesidiarius\\PhpstormProjects\\oneplace-xi-core\\module\\User\\config/../view',
        ],
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
            'user.rpc.logout' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/logout',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Logout\\Controller',
                        'action' => 'logout',
                    ],
                ],
            ],
            'user.rpc.dashboard' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/dashboard',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Dashboard\\Controller',
                        'action' => 'dashboard',
                    ],
                ],
            ],
            'user.rpc.confirm' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/confirm/user',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Confirm\\Controller',
                        'action' => 'confirm',
                    ],
                ],
            ],
            'user.rpc.verify' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/verify-email[/:token]',
                    'constraints' => [
                        'token' => '[a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Verify\\Controller',
                        'action' => 'verify',
                    ],
                ],
            ],
            'user.rpc.forgot' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/forgotpw',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Forgot\\Controller',
                        'action' => 'forgot',
                    ],
                ],
            ],
            'user.rpc.friends' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/friends',
                    'defaults' => [
                        'controller' => 'User\\V1\\Rpc\\Friends\\Controller',
                        'action' => 'friends',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'user.rest.user',
            1 => 'user.rpc.login',
            2 => 'user.rpc.logout',
            3 => 'user.rpc.dashboard',
            4 => 'user.rpc.confirm',
            5 => 'user.rpc.verify',
            6 => 'user.rpc.forgot',
            7 => 'user.rpc.friends',
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
                1 => 'DELETE',
            ],
            'collection_http_methods' => [
                0 => 'POST',
                1 => 'GET',
                2 => 'PUT',
                3 => 'PATCH',
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
            'User\\V1\\Rpc\\Logout\\Controller' => 'Json',
            'User\\V1\\Rpc\\Dashboard\\Controller' => 'Json',
            'User\\V1\\Rpc\\Confirm\\Controller' => 'Json',
            'User\\V1\\Rpc\\Verify\\Controller' => 'Json',
            'User\\V1\\Rpc\\Forgot\\Controller' => 'Json',
            'User\\V1\\Rpc\\Friends\\Controller' => 'Json',
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
            'User\\V1\\Rpc\\Logout\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'User\\V1\\Rpc\\Dashboard\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'User\\V1\\Rpc\\Confirm\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'User\\V1\\Rpc\\Verify\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'User\\V1\\Rpc\\Forgot\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'User\\V1\\Rpc\\Friends\\Controller' => [
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
            'User\\V1\\Rpc\\Logout\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
            'User\\V1\\Rpc\\Dashboard\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
            'User\\V1\\Rpc\\Confirm\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
            'User\\V1\\Rpc\\Verify\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
            'User\\V1\\Rpc\\Forgot\\Controller' => [
                0 => 'application/vnd.user.v1+json',
                1 => 'application/json',
            ],
            'User\\V1\\Rpc\\Friends\\Controller' => [
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
            'User\\V1\\Rpc\\Logout\\Controller' => \User\V1\Rpc\Logout\LogoutControllerFactory::class,
            'User\\V1\\Rpc\\Dashboard\\Controller' => \User\V1\Rpc\Dashboard\DashboardControllerFactory::class,
            'User\\V1\\Rpc\\Confirm\\Controller' => \User\V1\Rpc\Confirm\ConfirmControllerFactory::class,
            'User\\V1\\Rpc\\Verify\\Controller' => \User\V1\Rpc\Verify\VerifyControllerFactory::class,
            'User\\V1\\Rpc\\Forgot\\Controller' => \User\V1\Rpc\Forgot\ForgotControllerFactory::class,
            'User\\V1\\Rpc\\Friends\\Controller' => \User\V1\Rpc\Friends\FriendsControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'User\\V1\\Rpc\\Login\\Controller' => [
            'service_name' => 'Login',
            'http_methods' => [
                0 => 'POST',
                1 => 'GET',
            ],
            'route_name' => 'user.rpc.login',
        ],
        'User\\V1\\Rpc\\Logout\\Controller' => [
            'service_name' => 'Logout',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'user.rpc.logout',
        ],
        'User\\V1\\Rpc\\Dashboard\\Controller' => [
            'service_name' => 'Dashboard',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'user.rpc.dashboard',
        ],
        'User\\V1\\Rpc\\Confirm\\Controller' => [
            'service_name' => 'Confirm',
            'http_methods' => [
                0 => 'POST',
            ],
            'route_name' => 'user.rpc.confirm',
        ],
        'User\\V1\\Rpc\\Verify\\Controller' => [
            'service_name' => 'Verify',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'user.rpc.verify',
        ],
        'User\\V1\\Rpc\\Forgot\\Controller' => [
            'service_name' => 'Forgot',
            'http_methods' => [
                0 => 'POST',
                1 => 'GET',
                2 => 'PUT',
            ],
            'route_name' => 'user.rpc.forgot',
        ],
        'User\\V1\\Rpc\\Friends\\Controller' => [
            'service_name' => 'Friends',
            'http_methods' => [
                0 => 'GET',
                1 => 'POST',
                2 => 'PUT',
            ],
            'route_name' => 'user.rpc.friends',
        ],
    ],
    'api-tools-content-validation' => [
        'User\\V1\\Rest\\User\\Controller' => [
            'input_filter' => 'User\\V1\\Rest\\User\\Validator',
        ],
        'User\\V1\\Rpc\\Login\\Controller' => [
            'input_filter' => 'User\\V1\\Rpc\\Login\\Validator',
        ],
        'User\\V1\\Rpc\\Confirm\\Controller' => [
            'input_filter' => 'User\\V1\\Rpc\\Confirm\\Validator',
        ],
        'User\\V1\\Rpc\\Forgot\\Controller' => [
            'input_filter' => 'User\\V1\\Rpc\\Forgot\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'User\\V1\\Rpc\\Login\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'username',
                'description' => 'Name or E-Mail for User',
                'error_message' => 'You must provide a valid email or username',
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
                'description' => 'Password for user',
                'error_message' => 'You must provide a valid password',
            ],
        ],
        'User\\V1\\Rest\\User\\Validator' => [
            0 => [
                'required' => false,
                'validators' => [
                    0 => [
                        'name' => \Laminas\Validator\Regex::class,
                        'options' => [
                            'pattern' => '/^[A-Za-z][A-Za-z0-9]$/',
                        ],
                    ],
                ],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'user',
                'description' => 'The user submitting the status message.',
                'error_message' => 'You must provide a valid user',
            ],
        ],
        'User\\V1\\Rpc\\Confirm\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'options' => [],
                    ],
                ],
                'name' => 'action',
                'description' => 'What you want to confirm',
                'error_message' => 'You must provide a valid action',
            ],
            1 => [
                'required' => true,
                'validators' => [],
                'filters' => [],
                'name' => 'token',
                'description' => 'Token for Confirmation',
                'error_message' => 'You must provide a valid token',
            ],
        ],
        'User\\V1\\Rpc\\Forgot\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [],
                'filters' => [],
                'name' => 'email',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'User\\V1\\Rest\\User\\Controller' => [
                'collection' => [
                    'GET' => true,
                    'POST' => false,
                    'PUT' => true,
                    'PATCH' => true,
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
            'User\\V1\\Rpc\\Logout\\Controller' => [
                'actions' => [
                    'logout' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'User\\V1\\Rpc\\Dashboard\\Controller' => [
                'actions' => [
                    'dashboard' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'User\\V1\\Rpc\\Confirm\\Controller' => [
                'actions' => [
                    'confirm' => [
                        'GET' => false,
                        'POST' => true,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'User\\V1\\Rpc\\Forgot\\Controller' => [
                'actions' => [
                    'forgot' => [
                        'GET' => false,
                        'POST' => true,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'User\\V1\\Rpc\\Friends\\Controller' => [
                'actions' => [
                    'friends' => [
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
];
