<?php
return [
    'Lottery\\V1\\Rpc\\Round\\Controller' => [
        'GET' => [
            'description' => 'Get current lottery round information',
        ],
        'description' => 'Lottery Round',
    ],
    'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
        'GET' => [
            'description' => 'Get users tickets for current lottery round',
        ],
        'POST' => [
            'description' => 'Buy tickets for user for current lottery round',
            'request' => '{
    "round":3,
    "tickets":10
}',
        ],
        'description' => 'Lottery Tickets',
    ],
];
