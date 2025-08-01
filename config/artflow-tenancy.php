<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Artflow Studio Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is separate from stancl/tenancy to avoid conflicts
    | and provides Artflow-specific settings and features.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Authentication
    |--------------------------------------------------------------------------
    |
    | API key for accessing tenant management endpoints.
    | Set TENANT_API_KEY in your .env file.
    |
    */
    'api_key' => env('TENANT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the admin dashboard interface.
    |
    */
    'dashboard' => [
        'enabled' => true,
        'route_prefix' => 'admin',
        'middleware' => ['web'],
        'per_page' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Status Management
    |--------------------------------------------------------------------------
    |
    | Available tenant statuses and their behavior.
    |
    */
    'statuses' => [
        'active' => 'Tenant is active and accessible',
        'inactive' => 'Tenant is temporarily disabled',
        'suspended' => 'Tenant is suspended due to policy violation',
        'blocked' => 'Tenant is permanently blocked',
        'maintenance' => 'Tenant is under maintenance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for tenant database management.
    |
    */
    'database' => [
        'default_connection' => 'mysql',
        'template_connection' => 'mysql',
        'prefix' => 'tenant',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Optimization settings for better performance.
    |
    */
    'performance' => [
        'cache_tenant_lookups' => true,
        'cache_ttl' => 3600, // 1 hour
        'enable_stats_caching' => true,
        'stats_cache_ttl' => 1800, // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Views
    |--------------------------------------------------------------------------
    |
    | Views to display for different tenant error states.
    |
    */
    'error_views' => [
        'tenant-blocked' => 'tenancy::errors.tenant-blocked',
        'tenant-suspended' => 'tenancy::errors.tenant-suspended',
        'tenant-inactive' => 'tenancy::errors.tenant-inactive',
        'tenant-maintenance' => 'tenancy::errors.tenant-maintenance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific package features.
    |
    */
    'features' => [
        'tenant_stats' => true,
        'automatic_database_creation' => true,
        'domain_validation' => true,
        'tenant_impersonation' => false,
        'audit_logging' => true,
    ],
];
