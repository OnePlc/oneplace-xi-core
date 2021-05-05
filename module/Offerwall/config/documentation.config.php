<?php
return [
    'Offerwall\\V1\\Rest\\Offerwall\\Controller' => [
        'description' => 'Offerwall API',
        'collection' => [
            'description' => 'Offerwall Providers',
            'GET' => [
                'description' => 'Get a List of all Offerwall Providers',
            ],
        ],
        'entity' => [
            'description' => 'Offerwall Detail',
            'GET' => [
                'description' => 'Get Offerwall',
            ],
        ],
    ],
];
