<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Define middleware for UI and API routes.
    |
    */

    'middleware' => [
        'ui' => ['web'], // Remove 'auth' for now, add as needed
        'api' => ['tenancy.api'],
        'admin' => ['web'], // Admin routes middleware
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which migrations to sync to tenant databases.
    |
    */

    'migrations' => [
        'skip_migrations' => [
            '9999_create_tenants_and_domains_tables',
            'create_tenants_table',
            'create_domains_table',
        ],
        'sync_path' => 'database/migrations/tenant',
        'auto_migrate' => env('TENANT_AUTO_MIGRATE', false),
        'tenant_migrations_path' => 'database/migrations/tenant',
        'shared_migrations_path' => 'database/migrations',
        'auto_sync' => env('TENANT_AUTO_SYNC_MIGRATIONS', false),
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
        'tenant_seeders_path' => 'database/seeders/tenant',
        'shared_seeders_path' => 'database/seeders',
        'skip_seeders' => [
            'DatabaseSeeder',
            'CreateTenantsSeeder',
            'CreateDomainsSeeder',
        ],
        'auto_sync' => env('TENANT_AUTO_SYNC_SEEDERS', false),
    ],

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
        'persistent' => env('TENANT_DB_PERSISTENT', true),
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
        'driver' => env('TENANT_CACHE_DRIVER', 'database'), // Default to database cache
        'prefix' => env('TENANT_CACHE_PREFIX', 'tenant_'),
        'default_ttl' => env('TENANT_CACHE_TTL', 3600), // 1 hour
        'stats_ttl' => env('TENANT_CACHE_STATS_TTL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Homepage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant homepage management.
    |
    */

    'homepage' => [
        'enabled' => env('TENANT_HOMEPAGE_ENABLED', true),
        'view_path' => env('TENANT_HOMEPAGE_VIEW_PATH', 'tenants'),
        'auto_create_directory' => env('TENANT_HOMEPAGE_AUTO_CREATE_DIR', true),
        'fallback_redirect' => env('TENANT_HOMEPAGE_FALLBACK_REDIRECT', '/login'),
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

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Tenancy API endpoints.
    |
    */

    'api' => [
        // API Key for authentication (query parameter: api_key)
        'api_key' => env('TENANT_API_KEY', ''),

        // Disable all API authentication (for development/internal use)
        'no_auth' => env('TENANT_API_NO_AUTH', false),
        
        // Allow localhost requests without authentication
        'allow_localhost' => env('TENANT_API_ALLOW_LOCALHOST', true),
        
        // Rate limiting
        'rate_limit' => [
            'enabled' => env('TENANT_API_RATE_LIMIT', true),
            'max_attempts' => env('TENANT_API_RATE_LIMIT_ATTEMPTS', 60),
            'decay_minutes' => env('TENANT_API_RATE_LIMIT_DECAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for system monitoring and metrics.
    |
    */

    'monitoring' => [
        'enabled' => env('TENANT_MONITORING_ENABLED', true),
        'metrics_retention_days' => env('TENANT_MONITORING_RETENTION_DAYS', 30),
        'performance_tracking' => env('TENANT_MONITORING_PERFORMANCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant backup functionality.
    |
    */

    'backup' => [
        'enabled' => env('TENANT_BACKUP_ENABLED', false),
        'storage_disk' => env('TENANT_BACKUP_DISK', 'local'),
        'retention_days' => env('TENANT_BACKUP_RETENTION_DAYS', 7),
    ],
];
