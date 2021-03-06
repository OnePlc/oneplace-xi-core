<?php
return [
    'User\\V1\\Rpc\\Login\\Controller' => [
        'description' => 'Login with Username and Password',
        'POST' => [
            'description' => 'Perform Login Action',
            'request' => '{
   "username": "Username for User you want to login",
   "password": "Password for the user"
}',
            'response' => '{
    "user_id": "1"
}',
        ],
    ],
    'User\\V1\\Rpc\\Dashboard\\Controller' => [
        'GET' => [
            'description' => 'Get User Dashboard',
        ],
    ],
];
