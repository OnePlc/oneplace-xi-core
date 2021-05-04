<?php
return [
    'Lottery\\V1\\Rpc\\Tickets\\Controller' => [
        'GET' => [
            'description' => 'Get users tickets for current lottery round',
        ],
        'POST' => [
            'description' => 'Buy tickets for user for current lottery round',
        ],
        'description' => 'Lottery Tickets',
    ],
    'Lottery\\V1\\Rpc\\Round\\Controller' => [
        'GET' => [
            'description' => 'Get current lottery round information',
        ],
        'description' => 'Lottery Round',
    ],
];
