# AF-MultiTenancy: Complete Features Guide

## Overview

AF-MultiTenancy is a comprehensive Laravel multi-tenancy package built on top of `stancl/tenancy` with enhanced features for production-ready applications.

## Core Features

### 1. Cached Tenant Lookup

Fast tenant resolution with Redis caching to minimize database queries.

**Configuration:**
```php
// config/tenancy.php
'features' => [
    'cached_lookup' => true,
],

'cache' => [
    'enabled' => true,
    'ttl' => 3600, // 1 hour
    'prefix' => 'tenant_',
    'driver' => 'redis',
],
```

**Usage:**
```bash
# Test cached lookup performance
php artisan tenancy:test-cached-lookup --tenant=example

# Clear cache for specific domain
php artisan cache:forget tenant_domain:example.com

# Warm up cache
php artisan tenancy:warm-cache
```

**Service Usage:**
```php
use ArtflowStudio\Tenancy\Services\CachedTenantResolver;

$resolver = app(CachedTenantResolver::class);
$tenant = $resolver->resolve('example.com');

// Get cache statistics
$stats = $resolver->getCacheStats();
```

### 2. Tenant Maintenance Mode

Individual tenant maintenance mode with IP whitelisting and bypass keys.

**Configuration:**
```php
// config/tenancy.php
'features' => [
    'maintenance_mode' => true,
],

'maintenance' => [
    'enabled' => true,
    'view' => 'tenancy::maintenance',
    'retry_after' => 3600,
],
```

**Commands:**
```bash
# Enable maintenance for specific tenant
php artisan tenants:maintenance enable --tenant=example --message="Under maintenance" --allowed-ips=127.0.0.1,192.168.1.1

# Enable with bypass key
php artisan tenants:maintenance enable --tenant=example --bypass-key=secret123

# Disable maintenance
php artisan tenants:maintenance disable --tenant=example

# Check status
php artisan tenants:maintenance status --tenant=example

# List all tenants in maintenance
php artisan tenants:maintenance list
```

**Service Usage:**
```php
use ArtflowStudio\Tenancy\Services\TenantMaintenanceMode;

$service = app(TenantMaintenanceMode::class);

// Enable maintenance
$service->enableForTenant('tenant-id', [
    'message' => 'Scheduled maintenance',
    'allowed_ips' => ['127.0.0.1'],
    'bypass_key' => 'secret123',
    'retry_after' => 3600,
]);

// Check if tenant is in maintenance
$inMaintenance = $service->isInMaintenanceMode('tenant-id');

// Check if request should bypass maintenance
$shouldBypass = $service->shouldBypassMaintenance('tenant-id', $ip, $bypassKey);
```

### 3. Early Tenant Identification

Fast tenant identification with multiple resolution strategies and caching.

**Configuration:**
```php
// config/tenancy.php
'features' => [
    'early_identification' => true,
],

'identification' => [
    'cache_enabled' => true,
    'cache_ttl' => 3600,
    'strategies' => ['domain', 'path', 'subdomain'],
],
```

**Middleware:**
```php
// Register in routes
Route::middleware(['early-identification'])->group(function () {
    // Your tenant routes
});

// Or in Kernel.php
protected $middleware = [
    // ...
    \ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware::class,
];
```

**Service Usage:**
```php
use ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware;

$middleware = app(EarlyIdentificationMiddleware::class);

// Get identification metrics
$metrics = $middleware->getIdentificationMetrics('tenant-id');

// Warm up identification cache
$warmedCount = $middleware->warmUpIdentificationCache();

// Clear cache for domain
$middleware->clearIdentificationCache('example.com');
```

### 4. Laravel Sanctum Integration

Tenant-aware API authentication with Laravel Sanctum.

**Configuration:**
```php
// config/tenancy.php
'features' => [
    'sanctum_integration' => true,
],

'sanctum' => [
    'tenant_tokens' => true,
    'separate_token_tables' => true,
    'tenant_token_expiration' => null, // per tenant config
],
```

**Service Usage:**
```php
use ArtflowStudio\Tenancy\Services\TenantSanctumService;

$sanctumService = app(TenantSanctumService::class);

// Configure Sanctum for current tenant
$sanctumService->configureSanctumForTenant($tenant);

// Create tenant-specific API token
$token = $sanctumService->createTenantToken($user, 'API Token', ['read', 'write']);

// Get token statistics
$stats = $sanctumService->getTenantTokenStats($tenant);

// Cleanup expired tokens
$deletedCount = $sanctumService->cleanupExpiredTokens($tenant);

// Revoke all tokens for tenant
$revokedCount = $sanctumService->revokeAllTenantTokens($tenant);
```

**Testing:**
```bash
# Test Sanctum integration
php artisan tenancy:test-sanctum --tenant=example --verbose
```

## Testing Commands

### Comprehensive Testing

```bash
# Run all tests
php artisan tenancy:test-comprehensive --tenant=example --verbose --performance

# Skip specific test suites
php artisan tenancy:test-comprehensive --skip-cache --skip-maintenance

# Test specific features
php artisan tenancy:test-cached-lookup --tenant=example
php artisan tenancy:test-sanctum --tenant=example
```

### Performance Testing

```bash
# Run performance benchmarks
php artisan tenancy:test-comprehensive --performance --tenant=example

# Test cached lookup performance
php artisan tenancy:test-cached-lookup --tenant=example --iterations=1000
```

## Configuration Examples

### Basic Setup

```php
// config/tenancy.php
return [
    'features' => [
        'cached_lookup' => true,
        'maintenance_mode' => true,
        'early_identification' => true,
        'sanctum_integration' => true,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'tenant_',
        'driver' => 'redis',
    ],
    
    'maintenance' => [
        'enabled' => true,
        'view' => 'tenancy::maintenance',
        'retry_after' => 3600,
    ],
    
    'identification' => [
        'cache_enabled' => true,
        'cache_ttl' => 3600,
        'strategies' => ['domain', 'path', 'subdomain'],
    ],
    
    'sanctum' => [
        'tenant_tokens' => true,
        'separate_token_tables' => true,
    ],
];
```

### Advanced Configuration

```php
// Per-tenant configuration in tenant data
$tenant->update([
    'data' => [
        'sanctum_expiration' => 7200, // 2 hours
        'sanctum_guards' => ['web', 'api'],
        'sanctum_middleware' => ['throttle:api'],
        'maintenance_allowed_ips' => ['192.168.1.0/24'],
        'cache_ttl_override' => 1800, // 30 minutes
    ]
]);
```

## Middleware Stack

### Recommended Middleware Order

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... standard Laravel middleware
        \ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware::class,
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        // ... other middleware
    ],
    
    'api' => [
        // ... standard Laravel middleware
        \ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware::class,
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        'auth:sanctum',
        // ... other middleware
    ],
];
```

## Performance Optimization

### 1. Cache Configuration

```php
// Optimize Redis for tenancy
'redis' => [
    'client' => 'predis',
    'clusters' => [
        'default' => [
            [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'password' => env('REDIS_PASSWORD', null),
                'port' => env('REDIS_PORT', 6379),
                'database' => 0,
                'options' => [
                    'prefix' => env('REDIS_PREFIX', 'laravel_'),
                ],
            ],
        ],
    ],
],
```

### 2. Database Optimization

```php
// Use connection pooling
'mysql' => [
    'driver' => 'mysql',
    // ... other config
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ],
],
```

### 3. Monitoring

```bash
# Monitor cache performance
php artisan tenancy:test-cached-lookup --tenant=example --iterations=1000

# Check identification metrics
php artisan tenancy:test-comprehensive --performance
```

## Troubleshooting

### Common Issues

1. **Cached Lookup Not Working**
   - Verify Redis connection
   - Check cache configuration
   - Clear cache: `php artisan cache:clear`

2. **Maintenance Mode Not Showing**
   - Verify tenant exists
   - Check maintenance view exists
   - Verify middleware order

3. **Sanctum Tokens Not Working**
   - Check tenant database connection
   - Verify Sanctum configuration
   - Test with: `php artisan tenancy:test-sanctum`

4. **Early Identification Slow**
   - Check identification cache
   - Warm up cache: `php artisan tenancy:warm-cache`
   - Monitor with: `--verbose` flag

### Debug Commands

```bash
# Debug specific tenant
php artisan tenancy:test-comprehensive --tenant=example --verbose

# Check configuration
php artisan config:show tenancy

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Migration from Other Packages

### From Basic stancl/tenancy

1. Install AF-MultiTenancy
2. Publish configuration: `php artisan vendor:publish --tag=tenancy-config`
3. Update middleware stack
4. Run tests: `php artisan tenancy:test-comprehensive`

### From Custom Solutions

1. Map existing tenant identification logic
2. Configure cached lookup
3. Migrate maintenance mode logic
4. Test thoroughly with provided commands

## API Reference

### Services

- `CachedTenantResolver`: Fast tenant resolution with caching
- `TenantMaintenanceMode`: Maintenance mode management
- `TenantSanctumService`: Sanctum integration
- `EarlyIdentificationMiddleware`: Early tenant identification

### Commands

- `tenancy:test-comprehensive`: Complete feature testing
- `tenancy:test-cached-lookup`: Cache performance testing
- `tenancy:test-sanctum`: Sanctum integration testing
- `tenants:maintenance`: Maintenance mode management

### Middleware

- `early-identification`: Early tenant identification
- `universal-tenancy`: Universal tenancy middleware (legacy)
- `af-tenant`: AF tenant middleware
- `central`: Central domain middleware

## Support

For issues and questions:

1. Check the troubleshooting section
2. Run diagnostic commands
3. Review Laravel and stancl/tenancy documentation
4. Check package issues on GitHub

---

**Version:** 1.0.0
**Compatible with:** Laravel 10+, stancl/tenancy v3+
**PHP Requirements:** 8.1+
