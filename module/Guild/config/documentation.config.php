<?php
return [
    'Guild\\V1\\Rest\\Guild\\Controller' => [
        'description' => 'Guild AP',
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
                'description' => 'Create new Guild',
            ],
            'DELETE' => [
                'description' => 'Remove Guild',
            ],
        ],
    ],
];
