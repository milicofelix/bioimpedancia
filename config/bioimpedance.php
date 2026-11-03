<?php

use App\Models\User;

return [
    'assistant' => [
        'provider' => env('BIOIMPEDANCE_ASSISTANT_PROVIDER', 'hybrid'),
    ],

    'demo_users' => [
        [
            'name' => env('VITA_SCAN_DEMO_ADMIN_NAME', env('RICOSTY_DEMO_ADMIN_NAME', 'Milico Félix')),
            'email' => env('VITA_SCAN_DEMO_ADMIN_EMAIL', env('RICOSTY_DEMO_ADMIN_EMAIL', 'milicofelix@gmail.com')),
            'role' => User::ROLE_ADMIN,
        ],
        [
            'name' => env('VITA_SCAN_DEMO_PROFESSIONAL_NAME', env('RICOSTY_DEMO_PROFESSIONAL_NAME', 'Profissional Vita Scan')),
            'email' => env('VITA_SCAN_DEMO_PROFESSIONAL_EMAIL', env('RICOSTY_DEMO_PROFESSIONAL_EMAIL', 'profissional@vita-scan.local')),
            'role' => User::ROLE_PROFESSIONAL,
        ],
        [
            'name' => env('VITA_SCAN_DEMO_RECEPTION_NAME', env('RICOSTY_DEMO_RECEPTION_NAME', 'Recepção Vita Scan')),
            'email' => env('VITA_SCAN_DEMO_RECEPTION_EMAIL', env('RICOSTY_DEMO_RECEPTION_EMAIL', 'recepcao@vita-scan.local')),
            'role' => User::ROLE_RECEPTION,
        ],
    ],

    'demo_password' => env('VITA_SCAN_DEMO_PASSWORD', env('RICOSTY_DEMO_PASSWORD')),
];
