<?php
return [
    'Support\\V1\\Rpc\\Support\\Controller' => [
        'GET' => [
            'description' => 'Get support ticket history for user',
        ],
        'PUT' => [
            'description' => 'Create a new support ticket for user',
            'request' => '{"message":"Your support message"}',
        ],
    ],
    'Support\\V1\\Rpc\\Dashboard\\Controller' => [
        'GET' => [
            'description' => 'Get Support Ticket Dashboard Data',
        ],
    ],
    'Support\\V1\\Rpc\\Ticket\\Controller' => [
        'GET' => [
            'description' => 'Get Ticket Information',
        ],
        'POST' => [
            'description' => 'Get Ticket Info',
        ],
        'PUT' => [
            'description' => 'Send Ticket Reply',
        ],
    ],
    'Support\\V1\\Rpc\\Transaction\\Controller' => [
        'GET' => [
            'description' => 'Get User Transaction History',
        ],
        'POST' => [
            'description' => 'Get User Transaction Log',
            'request' => '{"user_id": 1}',
        ],
    ],
];
