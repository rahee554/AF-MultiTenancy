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
        'admin.' . env('APP_DOMAIN', 'localhost'),
        'central.' . env('APP_DOMAIN', 'localhost'),
    ],

    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        // \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class, // Enable if using Redis
    ],

    // Early identification - resolve tenants before app boots
    'early_identification' => env('TENANCY_EARLY_IDENTIFICATION', true),
    
    // Cached lookup configuration
    'cached_lookup' => [
        'enabled' => env('TENANCY_CACHED_LOOKUP', true),
        'cache_store' => env('TENANCY_CACHE_STORE', 'redis'), // redis, database, file
        'cache_ttl' => env('TENANCY_CACHE_TTL', 3600), // 1 hour
        'cache_key_prefix' => env('TENANCY_CACHE_PREFIX', 'tenancy_lookup'),
    ],

    // Tenant maintenance mode
    'maintenance_mode' => [
        'enabled' => env('TENANCY_MAINTENANCE_MODE_ENABLED', true),
        'redirect_url' => env('TENANCY_MAINTENANCE_REDIRECT_URL', '/maintenance'),
        'allowed_ips' => explode(',', env('TENANCY_MAINTENANCE_ALLOWED_IPS', '127.0.0.1,::1')),
        'bypass_key' => env('TENANCY_MAINTENANCE_BYPASS_KEY', 'secret'),
        'view' => env('TENANCY_MAINTENANCE_VIEW', 'tenancy::maintenance'),
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),

        'template_tenant_connection' => null,

        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'suffix' => env('TENANT_DB_SUFFIX', ''),

        'managers' => [
            'mysql' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
            'sqlite' => \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
            // 's3',
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
        // Enable as needed
        // \Stancl\Tenancy\Features\UserImpersonation::class,
        \Stancl\Tenancy\Features\TelescopeTags::class,
        \ArtflowStudio\Tenancy\Features\EnhancedTelescopeTags::class,
        \ArtflowStudio\Tenancy\Features\HorizonTags::class,
        \ArtflowStudio\Tenancy\Features\OctaneIntegration::class,
        // \Stancl\Tenancy\Features\UniversalRoutes::class,
        // \Stancl\Tenancy\Features\TenantConfig::class,
        // \Stancl\Tenancy\Features\CrossDomainRedirect::class,
        // \Stancl\Tenancy\Features\ViteBundler::class,
    ],

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--force' => true,
        '--class' => 'DatabaseSeeder', // root seeder class
    ],
];
