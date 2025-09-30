<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Route prefix and middleware configuration for the admin interface.
    |
    */
    'route' => [
        'prefix' => env('AF_TENANCY_PREFIX', 'af-tenancy'),
        'api_prefix' => env('AF_TENANCY_API_PREFIX', 'af-tenancy-api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Central Domains Configuration
    |--------------------------------------------------------------------------
    |
    | Define which domains should be treated as central domains.
    | These domains will NOT initialize tenant context.
    |
    */
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        env('APP_DOMAIN', 'localhost'),
        'admin.'.env('APP_DOMAIN', 'localhost'),
        'central.'.env('APP_DOMAIN', 'localhost'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unknown Domain Handling
    |--------------------------------------------------------------------------
    |
    | How to handle requests to domains not found in tenant or central config:
    | - 'central': Treat as central domain (recommended for development)
    | - 'redirect': Redirect to a specific URL
    | - '404': Return 404 error (recommended for production)
    |
    */
    'unknown_domain_action' => env('UNKNOWN_DOMAIN_ACTION', 'central'),
    'unknown_domain_redirect' => env('UNKNOWN_DOMAIN_REDIRECT', '/'),

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Define middleware for different route types.
    | Use 'universal.web' for routes that should work for both central and tenant.
    |
    */
    'middleware' => [
        'universal' => ['universal.web'], // Routes that work for both central and tenant
        'central_only' => ['central.web'], // Routes that only work on central domains
        'tenant_only' => ['tenant.web'], // Routes that only work on tenant domains
        'ui' => ['web'], // UI routes (fallback for tenant-specific UI)
        'api' => ['api'], // API routes
        'admin' => ['central.web', 'auth'], // Admin routes
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
        'tenant_seeders_path' => 'database/seeders/tenant',
        'shared_seeders_path' => 'database/seeders',
        'skip_seeders' => [
            'DatabaseSeeder',
            'CreateTenantsSeeder',
            'CreateDomainsSeeder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database settings for tenant management.
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
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant-specific caching.
    |
    */
    'cache' => [
        'driver' => env('TENANT_CACHE_DRIVER', 'database'),
        'prefix' => env('TENANT_CACHE_PREFIX', 'tenant_'),
        'default_ttl' => env('TENANT_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis database and prefix configuration for tenant isolation.
    |
    */
    'redis' => [
        'per_tenant_database' => env('TENANT_REDIS_PER_DATABASE', false),
        'database_offset' => env('TENANT_REDIS_DATABASE_OFFSET', 10),
        'prefix_pattern' => env('TENANT_REDIS_PREFIX_PATTERN', 'tenant_{tenant_id}_'),
        'central_prefix' => env('TENANT_REDIS_CENTRAL_PREFIX', 'central_'),
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
    | Maintenance Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant maintenance mode.
    |
    */
    'maintenance' => [
        'cache_ttl' => env('TENANCY_MAINTENANCE_CACHE_TTL', 3600),
        'default_message' => env('TENANCY_MAINTENANCE_DEFAULT_MESSAGE', 'We\'re performing maintenance. Please check back soon!'),
        'refresh_interval' => env('TENANCY_MAINTENANCE_REFRESH_INTERVAL', 30),
        'allowed_ips' => [],
        'bypass_header' => 'X-Maintenance-Bypass',
        'bypass_cookie' => 'maintenance_bypass',
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
    | FastPanel Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for FastPanel hosting environment.
    |
    */
    'fastpanel' => [
        'enabled' => env('FASTPANEL_ENABLED', false),
        'cli_path' => env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel'),
        'auto_create_database' => env('FASTPANEL_AUTO_CREATE_DATABASE', true),
        'auto_create_user' => env('FASTPANEL_AUTO_CREATE_USER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API endpoints.
    |
    */
    'api' => [
        'api_key' => env('TENANT_API_KEY', ''),
        'no_auth' => env('TENANT_API_NO_AUTH', false),
        'allow_localhost' => env('TENANT_API_ALLOW_LOCALHOST', true),
        'rate_limit' => [
            'enabled' => env('TENANT_API_RATE_LIMIT', true),
            'max_attempts' => env('TENANT_API_RATE_LIMIT_ATTEMPTS', 60),
            'decay_minutes' => env('TENANT_API_RATE_LIMIT_DECAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring and health checks.
    |
    */
    'monitoring' => [
        'enabled' => env('TENANT_MONITORING_ENABLED', true),
        'performance_tracking' => env('TENANT_MONITORING_PERFORMANCE', true),
        'slow_query_threshold' => env('TENANT_SLOW_QUERY_THRESHOLD', 1000),
        'memory_limit_warning' => env('TENANT_MEMORY_LIMIT_WARNING', 128),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant health monitoring.
    |
    */
    'health_check' => [
        'enabled' => env('TENANT_HEALTH_CHECK_ENABLED', true),
        'endpoints' => [
            'database' => true,
            'cache' => true,
            'storage' => true,
            'queue' => false,
        ],
        'timeout' => env('TENANT_HEALTH_CHECK_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant database backup and restore.
    |
    */
    'backup' => [
        'enabled' => env('TENANT_BACKUP_ENABLED', true),
        'disk' => env('TENANT_BACKUP_DISK', 'tenant-backups'),
        'retention_days' => env('TENANT_BACKUP_RETENTION_DAYS', 30),
        'compress_by_default' => env('TENANT_BACKUP_COMPRESS', true),
        'max_backup_size' => env('TENANT_BACKUP_MAX_SIZE', '2G'), // 2GB limit

        // MySQL binary paths (auto-detected if not specified)
        'mysqldump_path' => env('TENANT_BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql_path' => env('TENANT_BACKUP_MYSQL_PATH', 'mysql'),

        // Backup options
        'include_routines' => env('TENANT_BACKUP_INCLUDE_ROUTINES', true),
        'include_triggers' => env('TENANT_BACKUP_INCLUDE_TRIGGERS', true),
        'include_events' => env('TENANT_BACKUP_INCLUDE_EVENTS', true),
        'single_transaction' => env('TENANT_BACKUP_SINGLE_TRANSACTION', true),

        // Storage configuration
        'path_pattern' => env('TENANT_BACKUP_PATH_PATTERN', 'tenants/{tenant_id}/backups'),
        'filename_pattern' => env('TENANT_BACKUP_FILENAME_PATTERN', 'tenant_{tenant_id}_{timestamp}_{type}'),

        // Automatic backups
        'auto_backup' => [
            'enabled' => env('TENANT_AUTO_BACKUP_ENABLED', false),
            'schedule' => env('TENANT_AUTO_BACKUP_SCHEDULE', 'daily'),
            'time' => env('TENANT_AUTO_BACKUP_TIME', '02:00'),
            'compress' => env('TENANT_AUTO_BACKUP_COMPRESS', true),
        ],
    ],
];
