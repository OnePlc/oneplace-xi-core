<?php
return [
    'controllers' => [
        'factories' => [
            'Support\\V1\\Rpc\\Support\\Controller' => \Support\V1\Rpc\Support\SupportControllerFactory::class,
            'Support\\V1\\Rpc\\Dashboard\\Controller' => \Support\V1\Rpc\Dashboard\DashboardControllerFactory::class,
            'Support\\V1\\Rpc\\Ticket\\Controller' => \Support\V1\Rpc\Ticket\TicketControllerFactory::class,
            'Support\\V1\\Rpc\\Transaction\\Controller' => \Support\V1\Rpc\Transaction\TransactionControllerFactory::class,
            'Support\\V1\\Rpc\\FAQ\\Controller' => \Support\V1\Rpc\FAQ\FAQControllerFactory::class,
            'Support\\V1\\Rpc\\Browser\\Controller' => \Support\V1\Rpc\Browser\BrowserControllerFactory::class,
            'Support\\V1\\Rpc\\MailClaim\\Controller' => \Support\V1\Rpc\MailClaim\MailClaimControllerFactory::class,
            'Support\\V1\\Rpc\\MailUnsub\\Controller' => \Support\V1\Rpc\MailUnsub\MailUnsubControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'support' => 'C:\\Users\\Praesidiarius\\PhpstormProjects\\oneplace-xi-core\\module\\Support\\config/../view',
        ],
    ],
    'router' => [
        'routes' => [
            'support.rpc.support' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/support',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\Support\\Controller',
                        'action' => 'support',
                    ],
                ],
            ],
            'support.rpc.dashboard' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/support/dashboard',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\Dashboard\\Controller',
                        'action' => 'dashboard',
                    ],
                ],
            ],
            'support.rpc.ticket' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/support/ticket',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\Ticket\\Controller',
                        'action' => 'ticket',
                    ],
                ],
            ],
            'support.rpc.transaction' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/transactions',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\Transaction\\Controller',
                        'action' => 'transaction',
                    ],
                ],
            ],
            'support.rpc.faq' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/faq',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\FAQ\\Controller',
                        'action' => 'fAQ',
                    ],
                ],
            ],
            'support.rpc.browser' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/browserdl',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\Browser\\Controller',
                        'action' => 'browser',
                    ],
                ],
            ],
            'support.rpc.mail-claim' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/mailclaim',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\MailClaim\\Controller',
                        'action' => 'mailClaim',
                    ],
                ],
            ],
            'support.rpc.mail-unsub' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/unsub-email',
                    'defaults' => [
                        'controller' => 'Support\\V1\\Rpc\\MailUnsub\\Controller',
                        'action' => 'mailUnsub',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'support.rpc.support',
            1 => 'support.rpc.dashboard',
            2 => 'support.rpc.ticket',
            3 => 'support.rpc.transaction',
            4 => 'support.rpc.faq',
            5 => 'support.rpc.browser',
            6 => 'support.rpc.mail-claim',
            7 => 'support.rpc.mail-unsub',
        ],
        'default_version' => 1,
    ],
    'api-tools-rpc' => [
        'Support\\V1\\Rpc\\Support\\Controller' => [
            'service_name' => 'Support',
            'http_methods' => [
                0 => 'GET',
                1 => 'PUT',
            ],
            'route_name' => 'support.rpc.support',
        ],
        'Support\\V1\\Rpc\\Dashboard\\Controller' => [
            'service_name' => 'Dashboard',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'support.rpc.dashboard',
        ],
        'Support\\V1\\Rpc\\Ticket\\Controller' => [
            'service_name' => 'Ticket',
            'http_methods' => [
                0 => 'POST',
                1 => 'PUT',
            ],
            'route_name' => 'support.rpc.ticket',
        ],
        'Support\\V1\\Rpc\\Transaction\\Controller' => [
            'service_name' => 'Transaction',
            'http_methods' => [
                0 => 'POST',
            ],
            'route_name' => 'support.rpc.transaction',
        ],
        'Support\\V1\\Rpc\\FAQ\\Controller' => [
            'service_name' => 'FAQ',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'support.rpc.faq',
        ],
        'Support\\V1\\Rpc\\Browser\\Controller' => [
            'service_name' => 'Browser',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'support.rpc.browser',
        ],
        'Support\\V1\\Rpc\\MailClaim\\Controller' => [
            'service_name' => 'MailClaim',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'support.rpc.mail-claim',
        ],
        'Support\\V1\\Rpc\\MailUnsub\\Controller' => [
            'service_name' => 'MailUnsub',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'support.rpc.mail-unsub',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Support\\V1\\Rpc\\Support\\Controller' => 'Json',
            'Support\\V1\\Rpc\\Dashboard\\Controller' => 'Json',
            'Support\\V1\\Rpc\\Ticket\\Controller' => 'Json',
            'Support\\V1\\Rpc\\Transaction\\Controller' => 'Json',
            'Support\\V1\\Rpc\\FAQ\\Controller' => 'Json',
            'Support\\V1\\Rpc\\Browser\\Controller' => 'Json',
            'Support\\V1\\Rpc\\MailClaim\\Controller' => 'Json',
            'Support\\V1\\Rpc\\MailUnsub\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Support\\V1\\Rpc\\Support\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\Dashboard\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\Ticket\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\Transaction\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\FAQ\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\Browser\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\MailClaim\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Support\\V1\\Rpc\\MailUnsub\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Support\\V1\\Rpc\\Support\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\Dashboard\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\Ticket\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\Transaction\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\FAQ\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\Browser\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\MailClaim\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
            'Support\\V1\\Rpc\\MailUnsub\\Controller' => [
                0 => 'application/vnd.support.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Support\\V1\\Rpc\\Support\\Controller' => [
                'actions' => [
                    'support' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Support\\V1\\Rpc\\Dashboard\\Controller' => [
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
            'Support\\V1\\Rpc\\Ticket\\Controller' => [
                'actions' => [
                    'ticket' => [
                        'GET' => false,
                        'POST' => true,
                        'PUT' => true,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Support\\V1\\Rpc\\Transaction\\Controller' => [
                'actions' => [
                    'transaction' => [
                        'GET' => false,
                        'POST' => true,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Support\\V1\\Rpc\\FAQ\\Controller' => [
                'actions' => [
                    'fAQ' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Support\\V1\\Rpc\\Browser\\Controller' => [
                'actions' => [
                    'browser' => [
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
    'api-tools-content-validation' => [
        'Support\\V1\\Rpc\\Ticket\\Controller' => [
            'input_filter' => 'Support\\V1\\Rpc\\Ticket\\Validator',
        ],
        'Support\\V1\\Rpc\\Transaction\\Controller' => [
            'input_filter' => 'Support\\V1\\Rpc\\Transaction\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'Support\\V1\\Rpc\\Ticket\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'ticket_id',
                'description' => 'ID for TIcket you want to see / reply',
                'error_message' => 'You must provide a valid Ticket ID',
            ],
        ],
        'Support\\V1\\Rpc\\Transaction\\Validator' => [
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
                'description' => 'ID of User for Transaction Log',
                'error_message' => 'You must provide a valid User ID',
            ],
        ],
    ],
];
