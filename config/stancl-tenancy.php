<?php

declare(strict_types=1);

return [
    'tenant_model' => \ArtflowStudio\Tenancy\Models\Tenant::class,
    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => \ArtflowStudio\Tenancy\Models\Domain::class,

    'central_domains' => [
        '127.0.0.1',
        'localhost',
        env('APP_DOMAIN', 'localhost'),
    ],

    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        // Enable Redis for caching performance (recommended for production)
        // \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),

        'template_tenant_connection' => null,

        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'suffix' => env('TENANT_DB_SUFFIX', ''),

        'managers' => [
            'mysql' => \ArtflowStudio\Tenancy\Database\HighPerformanceMySQLDatabaseManager::class,
            'pgsql' => \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
            'sqlite' => \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
        ],
        
        // ===== CACHED LOOKUP PERFORMANCE OPTIMIZATION =====
        'cached_lookup' => [
            'enabled' => env('TENANCY_CACHED_LOOKUP', true),
            'ttl' => env('TENANCY_CACHE_TTL', 3600), // 1 hour
            'store' => env('TENANCY_CACHE_STORE', 'redis'), // Use Redis for best performance
        ],
        
        'connection_override' => [
            'template_tenant_connection' => [
                'driver' => env('DB_CONNECTION', 'mysql'),
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'username' => env('DB_USERNAME', 'forge'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                    // Performance optimizations for high-load multi-tenant applications
                    PDO::ATTR_PERSISTENT => true, // Enable persistent connections
                    PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Buffer queries for better performance
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='TRADITIONAL', innodb_flush_log_at_trx_commit=2",
                    // Connection pooling for better concurrency
                    PDO::MYSQL_ATTR_LOCAL_INFILE => false, // Security: disable local file loading
                ]) : [],
                // Connection pooling settings for high-load scenarios
                'pool' => [
                    'min_connections' => env('DB_POOL_MIN', 2),
                    'max_connections' => env('DB_POOL_MAX', 20),
                    'idle_timeout' => env('DB_POOL_IDLE_TIMEOUT', 30),
                    'max_lifetime' => env('DB_POOL_MAX_LIFETIME', 3600), // 1 hour
                ],
                // Performance monitoring settings
                'monitoring' => [
                    'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 1000), // ms
                    'connection_timeout' => env('DB_CONNECTION_TIMEOUT', 5), // seconds
                    'read_timeout' => env('DB_READ_TIMEOUT', 30), // seconds
                ],
            ],
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
        // Performance: Enable tenant context caching
        'tenant_cache_ttl' => env('TENANT_CACHE_TTL', 300), // 5 minutes
        'connection_cache_ttl' => env('CONNECTION_CACHE_TTL', 300), // 5 minutes
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
            's3',
        ],
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [
            'default',
            'cache',
            'session',
        ],
    ],

    'features' => [
        // Enable for production if needed
        // \Stancl\Tenancy\Features\UserImpersonation::class,
        // \Stancl\Tenancy\Features\TelescopeIntegration::class,
        // \Stancl\Tenancy\Features\UniversalRoutes::class,
        // \Stancl\Tenancy\Features\TenantConfig::class,
        // \Stancl\Tenancy\Features\CrossDomainRedirect::class,
        // \Stancl\Tenancy\Features\ViteBundler::class,
    ],

    'migration_parameters' => [
        '--force' => true,
        '--path' => ['database/migrations/tenant'],
        '--realpath' => false,
    ],

    'seeder_parameters' => [
        '--force' => true,
        '--class' => 'DatabaseSeeder',
    ],

    // Performance optimization settings
    'performance' => [
        'enable_tenant_caching' => env('TENANCY_ENABLE_CACHING', true),
        'enable_connection_pooling' => env('TENANCY_ENABLE_POOLING', true),
        'preload_tenants' => env('TENANCY_PRELOAD_TENANTS', false),
        'max_cached_tenants' => env('TENANCY_MAX_CACHED_TENANTS', 100),
        'cache_cleanup_interval' => env('TENANCY_CACHE_CLEANUP_INTERVAL', 3600), // 1 hour
    ],
];
