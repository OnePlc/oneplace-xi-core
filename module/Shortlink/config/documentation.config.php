<?php
return [
    'Shortlink\\V1\\Rest\\Shortlink\\Controller' => [
        'description' => 'Shortlink API',
        'collection' => [
            'GET' => [
                'description' => 'Get a List of all Shortlink Providers',
            ],
        ],
        'entity' => [
            'PUT' => [
                'description' => 'Claim a complete Shortlink',
            ],
            'GET' => [
                'description' => 'Get the basic information and links for a shortlink provider',
            ],
        ],
    ],
];
