<?php
return [
    'controllers' => [
        'factories' => [
            'Lottery\\V1\\Rpc\\Tickets\\Controller' => \Lottery\V1\Rpc\Tickets\TicketsControllerFactory::class,
            'Lottery\\V1\\Rpc\\Round\\Controller' => \Lottery\V1\Rpc\Round\RoundControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'lottery.rpc.tickets' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/lottery/tickets',
                    'defaults' => [
                        'controller' => 'Lottery\\V1\\Rpc\\Tickets\\Controller',
                        'action' => 'tickets',
                    ],
                ],
            ],
            'lottery.rpc.round' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/lottery/round',
                    'defaults' => [
                        'controller' => 'Lottery\\V1\\Rpc\\Round\\Controller',
                        'action' => 'round',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'lottery.rpc.tickets',
            1 => 'lottery.rpc.round',
        ],
    ],
    'api-tools-rpc' => [
        'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
            'service_name' => 'Tickets',
            'http_methods' => [
                0 => 'POST',
            ],
            'route_name' => 'lottery.rpc.tickets',
        ],
        'Lottery\\V1\\Rpc\\Round\\Controller' => [
            'service_name' => 'Round',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'lottery.rpc.round',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Lottery\\V1\\Rpc\\Tickets\\Controller' => 'Json',
            'Lottery\\V1\\Rpc\\Round\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
                0 => 'application/vnd.lottery.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
            'Lottery\\V1\\Rpc\\Round\\Controller' => [
                0 => 'application/vnd.lottery.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
                0 => 'application/vnd.lottery.v1+json',
                1 => 'application/json',
            ],
            'Lottery\\V1\\Rpc\\Round\\Controller' => [
                0 => 'application/vnd.lottery.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-content-validation' => [
        'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
            'input_filter' => 'Lottery\\V1\\Rpc\\Tickets\\Validator',
        ],
    ],
    'input_filter_specs' => [
        'Lottery\\V1\\Rpc\\Tickets\\Validator' => [
            0 => [
                'required' => true,
                'validators' => [],
                'filters' => [
                    0 => [
                        'name' => \Laminas\Filter\ToInt::class,
                        'options' => [],
                    ],
                ],
                'name' => 'round',
                'description' => 'Lottery Round ID',
                'error_message' => 'You must specify a valid lottery round',
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
                'name' => 'tickets',
                'error_message' => 'Enter a valid amount of tickets',
                'description' => 'Amount of tickets to buy',
            ],
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
                'actions' => [
                    'tickets' => [
                        'GET' => true,
                        'POST' => true,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
            'Lottery\\V1\\Rpc\\Round\\Controller' => [
                'actions' => [
                    'round' => [
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
];
