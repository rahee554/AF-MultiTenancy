<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Database Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for tenant database management.
    |
    */

    'database' => [
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'connection' => env('TENANT_DB_CONNECTION', 'mysql'),
        'charset' => env('TENANT_DB_CHARSET', 'utf8mb4'),
        'collation' => env('TENANT_DB_COLLATION', 'utf8mb4_unicode_ci'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant domains and routing.
    |
    */

    'domains' => [
        'central_domains' => [
            '127.0.0.1',
            'localhost',
            env('APP_DOMAIN', 'localhost'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant-specific caching.
    |
    */

    'cache' => [
        'prefix' => 'tenant_',
        'default_ttl' => 3600, // 1 hour
        'stats_ttl' => 300,     // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the admin dashboard functionality.
    |
    */

    'admin' => [
        'enabled' => true,
        'route_prefix' => 'admin',
        'middleware' => ['web', 'auth'],
        'layout' => 'layouts.admin.app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant migrations.
    |
    */

    'migrations' => [
        'auto_migrate' => env('TENANT_AUTO_MIGRATE', false),
        'tenant_migrations_path' => 'database/migrations/tenant',
        'shared_migrations_path' => 'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant seeders.
    |
    */

    'seeders' => [
        'auto_seed' => env('TENANT_AUTO_SEED', false),
        'default_seeders' => [
            // List of default seeders to run for new tenants
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Configuration
    |--------------------------------------------------------------------------
    |
    | Available tenant status values.
    |
    */

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'suspended' => 'Suspended',
        'blocked' => 'Blocked',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Pages Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant status error pages.
    |
    */

    'error_pages' => [
        'blocked' => 'tenancy::errors.tenant-blocked',
        'suspended' => 'tenancy::errors.tenant-suspended',
        'inactive' => 'tenancy::errors.tenant-inactive',
    ],
];
