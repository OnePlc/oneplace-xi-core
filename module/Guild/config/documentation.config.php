<?php
return [
    'Guild\\V1\\Rest\\Guild\\Controller' => [
        'description' => 'Guild API',
        'collection' => [
            'description' => '',
            'GET' => [
                'description' => 'Get List of all Guilds',
            ],
        ],
        'entity' => [
            'GET' => [
                'description' => 'Get Guild Information',
            ],
            'PATCH' => [
                'description' => 'Update Guild Settings',
            ],
            'PUT' => [
                'description' => 'Join a guild',
                'response' => '',
                'request' => '{
    "guild":1
}',
            ],
            'DELETE' => [
                'description' => 'Remove Guild',
            ],
            'POST' => [
                'description' => 'Create a new Guild',
                'request' => '{
    "name":"Praesis Castle",
    "icon": "fas fa-crown"
}',
                'response' => '{
    "label": "Praesis Castle",
    "owner_idfs": "335874987",
    "created_date": "2021-05-04 23:09:49",
    "xp_level": 1,
    "xp_current": 0,
    "xp_total": 0,
    "icon": "some-icon",
    "is_vip": 0,
    "token_balance": 0,
    "_links": {
        "self": {
            "href": "http://xi.api.swissfaucet.io/guild/create"
        }
    }
}',
            ],
        ],
    ],
];
