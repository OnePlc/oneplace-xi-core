<?php
return [
    'Faucet\\V1\\Rpc\\Claim\\Controller' => [
        'description' => 'Hourly Free Crypto Claim',
        'GET' => [
            'description' => 'Get Timer for next Claim',
        ],
        'POST' => [
            'description' => 'Request Claim',
        ],
    ],
    'Faucet\\V1\\Rest\\Dailytask\\Controller' => [
        'description' => 'Daily Tasks for Users.',
        'collection' => [
            'description' => '',
            'GET' => [
                'description' => 'Get a List of all Dailytasks',
                'response' => '{
    "_links": {
        "self": {
            "href": "http://xi.api.swissfaucet.io/dailytask"
        }
    },
    "_embedded": {
        "dailytask": [
            {
                "Dailytask_ID": "1",
                "label": "Complete 5 Shortlinks",
                "goal": "5",
                "reward": "10",
                "type": "shortlink",
                "sort_id": "0",
                "mode": "website"
            }
    "total_items": 1
}',
            ],
        ],
        'entity' => [
            'PUT' => [
                'description' => 'Claim a complete dailytask for a user',
                'request' => '{
    "platform": "website"
}',
                'response' => '{}',
            ],
        ],
    ],
    'Faucet\\V1\\Rest\\Achievement\\Controller' => [
        'description' => 'Achievements for Users',
        'collection' => [
            'description' => '',
            'GET' => [
                'description' => 'Get a List of all available Achievements',
            ],
        ],
        'entity' => [
            'PUT' => [
                'description' => 'Claim a complete Achievement for a User',
            ],
        ],
    ],
    'Faucet\\V1\\Rpc\\Referral\\Controller' => [
        'description' => 'User Referral',
        'GET' => [
            'description' => 'Get all Referral Information for current User',
        ],
    ],
];
