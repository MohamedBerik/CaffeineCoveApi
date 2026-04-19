<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Tables for Admin CRUD
    |--------------------------------------------------------------------------
    */
    'allowed_tables' => [
        'users',
        'categories',
        'products',
        'customers',
        'orders',
        'employees',
        'sales',
        'reservations',
        'invoices',
        'suppliers',
        'purchase_orders',
        'companies',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Permissions (for Company Admin)
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'users' => ['view', 'create', 'update', 'delete'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete'],
        'customers' => ['view', 'create', 'update', 'delete'],
        'orders' => ['view', 'create', 'update'],
        'employees' => ['view', 'create', 'update', 'delete'],
        'sales' => ['view', 'create', 'update'],
        'reservations' => ['view', 'update'],
        'invoices' => ['view'],
        'suppliers' => ['view', 'create', 'update', 'delete'],
        'purchase_orders' => ['view', 'create', 'update'],
        'companies' => [], // Company Admin ممنوع تمامًا
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Columns (hidden from all responses)
    |--------------------------------------------------------------------------
    */
    'sensitive_columns' => [
        'password',
        'remember_token',
        'api_token',
        'secret',
        'private_key',
        'access_token',
        'refresh_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'admin_crud_',
    ],
];
