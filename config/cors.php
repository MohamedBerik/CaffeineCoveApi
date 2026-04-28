<?php

return [

    'paths' => ['api/*', 'admin/*', 'sanctum/csrf-cookie', 'broadcasting/auth', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://caffeine-cove-cafe.vercel.app',
        'https://caffeinecoveapi-production-a107.up.railway.app',
        'http://localhost:3000',
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
