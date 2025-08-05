<?php

/*
 * Optimized Database Configuration for Multi-Tenant Applications
 * Copy this content to your config/database.php file for best performance
 */

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                
                // ===== MULTI-TENANT PERFORMANCE OPTIMIZATIONS =====
                
                // Enable persistent connections for better performance
                PDO::ATTR_PERSISTENT => (bool) env('DB_PERSISTENT', true),
                
                // Use native prepared statements (faster)
                PDO::ATTR_EMULATE_PREPARES => false,
                
                // Buffer queries for better performance with large result sets
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                
                // Set session-level variables only (not global variables)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='TRADITIONAL'",
                
                // Security: disable local file loading
                PDO::MYSQL_ATTR_LOCAL_INFILE => false,
                
                // Connection timeout settings
                PDO::ATTR_TIMEOUT => (int) env('DB_CONNECTION_TIMEOUT', 5),
                
                // Error handling
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                
                // Default fetch mode
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                
            ]) : [],
            
            // Connection pooling simulation (for documentation)
            'pool' => [
                'min_connections' => env('DB_POOL_MIN', 2),
                'max_connections' => env('DB_POOL_MAX', 20),
                'idle_timeout' => env('DB_POOL_IDLE_TIMEOUT', 30),
                'max_lifetime' => env('DB_POOL_MAX_LIFETIME', 3600),
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                
                // ===== POSTGRESQL MULTI-TENANT OPTIMIZATIONS =====
                
                // Enable persistent connections
                PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true),
                
                // Use native prepared statements
                PDO::ATTR_EMULATE_PREPARES => false,
                
                // Connection timeout
                PDO::ATTR_TIMEOUT => env('DB_CONNECTION_TIMEOUT', 5),
                
                // Error handling
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                
            ]) : [],
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            
            // ===== REDIS OPTIMIZATIONS FOR MULTI-TENANCY =====
            'read_timeout' => env('REDIS_READ_TIMEOUT', 60),
            'context' => [
                // Optimize for tenant context caching
                'persistent' => env('REDIS_PERSISTENT', true),
                'tcp_keepalive' => 1,
            ],
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

        'session' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_SESSION_DB', '2'),
        ],

    ],

];
