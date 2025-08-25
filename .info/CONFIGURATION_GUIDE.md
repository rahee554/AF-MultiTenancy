# Configuration Guide

## Complete Configuration Reference

### Main Configuration Files

#### 1. `config/artflow-tenancy.php` - Primary Configuration

**Central Domains Configuration**:
```php
'central_domains' => [
    '127.0.0.1',
    'localhost',
    env('APP_DOMAIN', 'localhost'),
    'admin.' . env('APP_DOMAIN', 'localhost'),
    'central.' . env('APP_DOMAIN', 'localhost'),
],
```

**Unknown Domain Handling**:
```php
'unknown_domain_action' => env('UNKNOWN_DOMAIN_ACTION', 'central'),
// Options: 'central', 'redirect', '404'
'unknown_domain_redirect' => env('UNKNOWN_DOMAIN_REDIRECT', '/'),
```

**Middleware Configuration**:
```php
'middleware' => [
    'universal' => ['universal.web'],     // Both central and tenant
    'central_only' => ['central.web'],    // Central domains only
    'tenant_only' => ['tenant.web'],      // Tenant domains only
    'api' => ['universal.api'],           // API routes
    'admin' => ['central.web', 'auth'],   // Admin routes
],
```

**Database Configuration**:
```php
'database' => [
    'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
    'connection' => env('TENANT_DB_CONNECTION', 'mysql'),
    'auto_create' => env('TENANT_AUTO_CREATE_DB', true),
    'auto_migrate' => env('TENANT_AUTO_MIGRATE', false),
    'auto_seed' => env('TENANT_AUTO_SEED', false),
],
```

**Backup Configuration**:
```php
'backup' => [
    'disk' => env('TENANT_BACKUP_DISK', 'tenant-backups'),
    'mysqldump_path' => env('TENANT_BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('TENANT_BACKUP_MYSQL_PATH', 'mysql'),
    'compression' => env('TENANT_BACKUP_COMPRESSION', false),
    'retention_days' => env('TENANT_BACKUP_RETENTION_DAYS', 30),
    'max_file_size' => env('TENANT_BACKUP_MAX_FILE_SIZE', '1GB'),
],
```

**Caching Configuration**:
```php
'cache' => [
    'driver' => env('TENANT_CACHE_DRIVER', 'redis'),
    'prefix' => env('TENANT_CACHE_PREFIX', 'tenant_'),
    'ttl' => env('TENANT_CACHE_TTL', 3600),
    'tags_enabled' => env('TENANT_CACHE_TAGS', true),
],
```

#### 2. `config/tenancy.php` - stancl/tenancy Integration

**Tenant Model Configuration**:
```php
'tenant_model' => \ArtflowStudio\Tenancy\Models\Tenant::class,
'domain_model' => \ArtflowStudio\Tenancy\Models\Domain::class,
```

**Bootstrappers Configuration**:
```php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    // Add custom bootstrappers here
],
```

### Environment Variables

#### Database Configuration
```env
# Central Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tenancy_central
DB_USERNAME=root
DB_PASSWORD=

# Tenant Database Settings
TENANT_DB_CONNECTION=mysql
TENANT_DB_PREFIX=tenant_
TENANT_AUTO_CREATE_DB=true
TENANT_AUTO_MIGRATE=false
TENANT_AUTO_SEED=false
```

#### Domain Configuration
```env
APP_DOMAIN=localhost
UNKNOWN_DOMAIN_ACTION=central
UNKNOWN_DOMAIN_REDIRECT=/
```

#### Backup Configuration
```env
TENANT_BACKUP_DISK=tenant-backups
TENANT_BACKUP_MYSQLDUMP_PATH=mysqldump
TENANT_BACKUP_MYSQL_PATH=mysql
TENANT_BACKUP_COMPRESSION=false
TENANT_BACKUP_RETENTION_DAYS=30
TENANT_BACKUP_MAX_FILE_SIZE=1GB
```

#### Cache Configuration
```env
TENANT_CACHE_DRIVER=redis
TENANT_CACHE_PREFIX=tenant_
TENANT_CACHE_TTL=3600
TENANT_CACHE_TAGS=true

# Redis Settings
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Performance Testing
```env
TENANT_PERFORMANCE_MAX_CONCURRENT_USERS=50
TENANT_PERFORMANCE_DEFAULT_OPERATIONS=30
TENANT_PERFORMANCE_TIMEOUT=300
```

### Filesystem Configuration

#### Storage Disk Setup
Add to `config/filesystems.php`:
```php
'disks' => [
    // ... existing disks
    
    'tenant-backups' => [
        'driver' => 'local',
        'root' => storage_path('app/tenant-backups'),
        'visibility' => 'private',
        'permissions' => [
            'file' => [
                'public' => 0644,
                'private' => 0600,
            ],
            'dir' => [
                'public' => 0755,
                'private' => 0700,
            ],
        ],
    ],
],
```

### Middleware Registration

#### Laravel 11+ (bootstrap/app.php)
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware
        $middleware->append(\ArtflowStudio\Tenancy\Http\Middleware\UniversalWebMiddleware::class);
        
        // Middleware groups
        $middleware->group('tenant', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware::class,
        ]);
        
        $middleware->group('central', [
            \ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware::class,
        ]);
        
        // Middleware aliases
        $middleware->alias([
            'tenant.auth' => \ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware::class,
            'tenant.homepage' => \ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware::class,
            'smart.tenant' => \ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware::class,
            'tenancy.api' => \ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware::class,
            'central.tenant' => \ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware::class,
            'smart.domain' => \ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

### Database Schema Configuration

#### Central Database Tables
The package uses these central database tables:
- `tenants` - Enhanced tenant information
- `domains` - Domain to tenant mapping
- `migrations` - Central migration tracking
- `cache` - Application cache (if using database cache)
- `jobs` - Background job queue
- `failed_jobs` - Failed job tracking

#### Tenant Database Schema
Each tenant database automatically receives:
- Standard Laravel tables (users, migrations, etc.)
- Custom application tables
- Tenant-specific data isolation

### Service Provider Registration

#### Automatic Discovery (Recommended)
Laravel auto-discovers the service provider. Ensure `composer.json` has:
```json
{
    "extra": {
        "laravel": {
            "providers": [
                "ArtflowStudio\\Tenancy\\TenancyServiceProvider"
            ]
        }
    }
}
```

#### Manual Registration
If needed, add to `config/app.php`:
```php
'providers' => [
    // ... other providers
    ArtflowStudio\Tenancy\TenancyServiceProvider::class,
],
```

### Queue Configuration

#### For Background Jobs
```env
QUEUE_CONNECTION=redis
```

#### Queue Worker Configuration
```bash
# Supervisor configuration for queue workers
[program:tenancy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
```

### Performance Optimization Configuration

#### Database Optimization
```env
# Database connection pooling
DB_CONNECTION_POOLING=true
DB_MAX_CONNECTIONS=100

# Query optimization
DB_SLOW_QUERY_LOG=true
DB_SLOW_QUERY_TIME=2
```

#### Cache Optimization
```env
# Redis optimization
REDIS_MAXMEMORY=1gb
REDIS_MAXMEMORY_POLICY=allkeys-lru

# Cache optimization
CACHE_SERIALIZATION=igbinary
CACHE_COMPRESSION=true
```

### Security Configuration

#### Tenant Isolation
```php
// Additional security headers
'security' => [
    'tenant_isolation' => true,
    'cross_tenant_access_prevention' => true,
    'secure_headers' => [
        'X-Tenant-ID' => false, // Don't expose tenant IDs
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
    ],
],
```

#### Backup Security
```env
# Backup encryption
TENANT_BACKUP_ENCRYPTION=true
TENANT_BACKUP_ENCRYPTION_KEY=your-encryption-key

# Access control
TENANT_BACKUP_ACCESS_CONTROL=strict
TENANT_BACKUP_ADMIN_ONLY=true
```

### Development vs Production Settings

#### Development Configuration
```env
APP_ENV=local
APP_DEBUG=true
TENANT_AUTO_CREATE_DB=true
TENANT_AUTO_MIGRATE=true
TENANT_AUTO_SEED=true
UNKNOWN_DOMAIN_ACTION=central
LOG_LEVEL=debug
```

#### Production Configuration
```env
APP_ENV=production
APP_DEBUG=false
TENANT_AUTO_CREATE_DB=false
TENANT_AUTO_MIGRATE=false
TENANT_AUTO_SEED=false
UNKNOWN_DOMAIN_ACTION=404
LOG_LEVEL=warning
TENANT_BACKUP_ENCRYPTION=true
```

### Monitoring Configuration

#### Logging
```env
LOG_CHANNEL=stack
LOG_LEVEL=info

# Tenant-specific logging
TENANT_LOG_CHANNEL=tenant
TENANT_LOG_SEPARATE_FILES=true
```

#### Health Checks
```php
'health_checks' => [
    'database_connectivity' => true,
    'redis_connectivity' => true,
    'disk_space' => true,
    'tenant_isolation' => true,
],
```

### Migration and Seeding Configuration

#### Migration Paths
```php
'migrations' => [
    'skip_migrations' => [
        '9999_create_tenants_and_domains_tables',
        'create_tenants_table',
        'create_domains_table',
    ],
    'tenant_migrations_path' => 'database/migrations/tenant',
    'shared_migrations_path' => 'database/migrations',
],
```

#### Seeder Configuration
```php
'seeders' => [
    'tenant_seeders_path' => 'database/seeders/tenant',
    'shared_seeders_path' => 'database/seeders',
    'skip_seeders' => [
        'DatabaseSeeder',
        'CreateTenantsSeeder',
        'CreateDomainsSeeder',
    ],
],
```
