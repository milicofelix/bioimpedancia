<?php

use App\Models\User;

return [
    'assistant' => [
        'provider' => env('BIOIMPEDANCE_ASSISTANT_PROVIDER', 'hybrid'),
    ],

    'demo_users' => [
        [
            'name' => env('RICOSTY_DEMO_ADMIN_NAME', 'Milico Félix'),
            'email' => env('RICOSTY_DEMO_ADMIN_EMAIL', 'milicofelix@gmail.com'),
            'role' => User::ROLE_ADMIN,
        ],
        [
            'name' => env('RICOSTY_DEMO_PROFESSIONAL_NAME', 'Profissional Ricosty'),
            'email' => env('RICOSTY_DEMO_PROFESSIONAL_EMAIL', 'profissional@ricosty.local'),
            'role' => User::ROLE_PROFESSIONAL,
        ],
        [
            'name' => env('RICOSTY_DEMO_RECEPTION_NAME', 'Recepção Ricosty'),
            'email' => env('RICOSTY_DEMO_RECEPTION_EMAIL', 'recepcao@ricosty.local'),
            'role' => User::ROLE_RECEPTION,
        ],
    ],

    'demo_password' => env('RICOSTY_DEMO_PASSWORD'),
];
