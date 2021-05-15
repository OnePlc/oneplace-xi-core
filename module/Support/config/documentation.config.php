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
];
