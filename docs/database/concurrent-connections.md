# Concurrent Database Connections for Multi-Tenancy

This guide explains how to implement and manage concurrent database connections for optimal multi-tenant performance using the artflow-studio/tenancy package.

## Overview

The `TenantConnectionPoolManager` provides a sophisticated connection pooling system that allows multiple tenant database connections to be maintained concurrently while ensuring proper isolation and preventing resource exhaustion.

## Key Features

- **Connection Pooling**: Reuse database connections across requests
- **Tenant Isolation**: Ensure complete separation between tenant data
- **Resource Management**: Automatic cleanup of idle connections
- **Performance Monitoring**: Built-in statistics and health checks
- **Configurable Limits**: Customizable pool size and timeout settings

## Configuration

### 1. Enable Tenant Template Connection

First, ensure you have the proper tenant template configuration:

```bash
php artisan tenant:check-privileges --interactive
# Choose: "add-tenant-template"
```

This will add the optimized `tenant_template` connection to your `config/database.php`:

```php
'tenant_template' => [
    'driver' => 'mysql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => null, // Set dynamically by tenancy
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    // ... optimized settings for multi-tenancy
    'options' => [
        PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be FALSE
        PDO::ATTR_TIMEOUT => 10,       // Shorter timeout for tenants
        // ... other optimized PDO options
    ],
],
```

### 2. Connection Pool Configuration

Create a connection pool manager with custom settings:

```php
use ArtflowStudio\Tenancy\Services\Database\TenantConnectionPoolManager;

$poolManager = TenantConnectionPoolManager::create([
    'max_pool_size' => 50,      // Maximum concurrent connections
    'max_idle_time' => 300,     // 5 minutes idle timeout
    'connection_timeout' => 10, // Connection timeout in seconds
    'enable_pooling' => true,   // Enable/disable pooling
]);
```

## Usage Examples

### Basic Connection Usage

```php
use ArtflowStudio\Tenancy\Services\Database\TenantConnectionPoolManager;

$poolManager = TenantConnectionPoolManager::create();

// Get a connection for a specific tenant
$connection = $poolManager->getConnection('tenant_123');

// Use the connection
$users = $connection->table('users')->get();

// Release the connection back to the pool
$poolManager->releaseConnection('tenant_123');
```

### Context-Aware Execution

The recommended approach is to use the context-aware execution method:

```php
$result = $poolManager->runInTenantContext('tenant_123', function($connection) {
    // All database operations here are automatically scoped to tenant_123
    return $connection->table('users')->where('active', true)->get();
});

// Connection is automatically released after the callback
```

### Advanced Usage with Error Handling

```php
try {
    $poolManager->runInTenantContext('tenant_456', function($connection) {
        $connection->beginTransaction();
        
        try {
            // Multiple operations in transaction
            $connection->table('orders')->insert($orderData);
            $connection->table('inventory')->decrement('quantity', $quantity);
            
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }
    });
} catch (\Exception $e) {
    Log::error("Tenant operation failed: " . $e->getMessage());
}
```

## Laravel Integration

### Service Provider Registration

Add the connection pool manager to your service providers:

```php
// In a service provider
public function register()
{
    $this->app->singleton('tenancy.connection_pool', function ($app) {
        return TenantConnectionPoolManager::create(config('tenancy.connection_pool', []));
    });
}

public function boot()
{
    // Optional: Register as a facade
    $this->app->alias('tenancy.connection_pool', TenantConnectionPoolManager::class);
}
```

### Configuration File

Add connection pool settings to your `config/tenancy.php`:

```php
'connection_pool' => [
    'max_pool_size' => env('TENANCY_POOL_SIZE', 50),
    'max_idle_time' => env('TENANCY_POOL_IDLE_TIME', 300),
    'connection_timeout' => env('TENANCY_POOL_TIMEOUT', 10),
    'enable_pooling' => env('TENANCY_POOL_ENABLED', true),
],
```

### Environment Variables

```env
# Connection Pool Configuration
TENANCY_POOL_SIZE=50
TENANCY_POOL_IDLE_TIME=300
TENANCY_POOL_TIMEOUT=10
TENANCY_POOL_ENABLED=true
```

## Artisan Commands

### Check Pool Status

```bash
# Basic status
php artisan tenancy:connection-pool status

# Detailed status with connection information
php artisan tenancy:connection-pool status --detailed
```

### Health Check

```bash
# Check pool health
php artisan tenancy:connection-pool health
```

### Test Pool Functionality

```bash
# Test with automatic tenant ID
php artisan tenancy:connection-pool test

# Test with specific tenant
php artisan tenancy:connection-pool test --tenant=tenant_123

# Test with custom pool size
php artisan tenancy:connection-pool test --pool-size=25
```

### Clear Pool

```bash
# Clear all connections from pool
php artisan tenancy:connection-pool clear
```

## Performance Monitoring

### Pool Statistics

```php
$stats = $poolManager->getPoolStatistics();

/*
Returns:
[
    'pool_size' => 5,
    'max_pool_size' => 50,
    'active_connections' => ['tenant_123', 'tenant_456'],
    'usage_counts' => ['tenant_123' => 15, 'tenant_456' => 8],
    'last_accessed' => ['tenant_123' => '2025-08-25 12:30:45'],
    'pooling_enabled' => true,
]
*/
```

### Health Monitoring

```php
$health = $poolManager->healthCheck();

/*
Returns:
[
    'status' => 'healthy', // 'healthy', 'warning', 'error'
    'issues' => [],
    'statistics' => [...],
]
*/
```

### Laravel Telescope Integration

```php
// In a service provider or middleware
\Laravel\Telescope\Telescope::tag(function () use ($poolManager) {
    $stats = $poolManager->getPoolStatistics();
    return ['pool_size:' . $stats['pool_size']];
});
```

## Best Practices

### 1. Connection Lifecycle Management

```php
// ❌ Don't do this - connection not released
$connection = $poolManager->getConnection('tenant_123');
$users = $connection->table('users')->get();
// Missing: $poolManager->releaseConnection('tenant_123');

// ✅ Do this - automatic cleanup
$users = $poolManager->runInTenantContext('tenant_123', function($connection) {
    return $connection->table('users')->get();
});
```

### 2. Long-Running Operations

```php
// For long-running operations, consider using fresh connections
if ($isLongOperation) {
    $connection = $poolManager->createTenantConnection($tenantId);
    // Perform long operation
    $poolManager->removeConnection($tenantId); // Cleanup
} else {
    // Use pooled connection for normal operations
    $poolManager->runInTenantContext($tenantId, $callback);
}
```

### 3. Error Handling

```php
try {
    $poolManager->runInTenantContext($tenantId, function($connection) {
        // Database operations
    });
} catch (\Illuminate\Database\QueryException $e) {
    // Handle database-specific errors
    Log::error("Database error for tenant {$tenantId}: " . $e->getMessage());
    
    // Optionally remove potentially corrupted connection
    $poolManager->removeConnection($tenantId);
    
    throw $e;
}
```

### 4. Resource Monitoring

```php
// Monitor pool usage in middleware or scheduled tasks
$stats = $poolManager->getPoolStatistics();

if ($stats['pool_size'] > ($stats['max_pool_size'] * 0.8)) {
    Log::warning('Connection pool nearing capacity', $stats);
}

// Perform health checks periodically
$health = $poolManager->healthCheck();
if ($health['status'] !== 'healthy') {
    Log::error('Connection pool health issues detected', $health);
}
```

## Troubleshooting

### Common Issues

#### 1. "Too many connections" Error

**Symptoms**: MySQL error 1040
**Solutions**:
- Increase `max_pool_size` in connection pool config
- Increase MySQL `max_connections` setting
- Implement connection cleanup in your application

#### 2. Memory Usage Growing

**Symptoms**: Increasing memory consumption over time
**Solutions**:
- Reduce `max_idle_time` to clean up connections sooner
- Implement periodic pool clearing
- Monitor for connection leaks

#### 3. Slow Tenant Switching

**Symptoms**: High latency when switching between tenants
**Solutions**:
- Use pooled connections instead of creating new ones
- Optimize tenant template connection settings
- Increase pool size for frequently accessed tenants

#### 4. Connection Pool Health Issues

**Symptoms**: Health checks showing warnings or errors
**Solutions**:
```bash
# Check detailed pool status
php artisan tenancy:connection-pool status --detailed

# Clear and rebuild pool
php artisan tenancy:connection-pool clear

# Test pool functionality
php artisan tenancy:connection-pool test
```

### Debugging Commands

```bash
# Check configuration
php artisan tenancy:diagnose-performance --check-mysql --detailed

# Test stress performance
php artisan tenancy:stress-test --users=10 --operations=100

# Monitor pool during operation
watch 'php artisan tenancy:connection-pool status'
```

### Logging

Enable detailed logging for connection pool operations:

```php
// In config/logging.php
'channels' => [
    'tenancy_pool' => [
        'driver' => 'daily',
        'path' => storage_path('logs/tenancy-pool.log'),
        'level' => 'debug',
    ],
],
```

Use in the connection pool manager:

```php
Log::channel('tenancy_pool')->info('Connection pool operation', [
    'tenant_id' => $tenantId,
    'pool_size' => count($this->connectionPool),
    'action' => 'get_connection'
]);
```

## Security Considerations

1. **Connection Isolation**: Each tenant connection is completely isolated
2. **Authentication**: Uses individual database credentials per connection
3. **Resource Limits**: Prevents any single tenant from exhausting connections
4. **Audit Logging**: All connection operations can be logged and monitored

## Related Documentation

- [Database Template Configuration](./database-template.md)
- [PDO Configuration Guide](./pdo-configuration.md)
- [MySQL Configuration Guide](./mysql-configuration.md)
- [Performance Optimization](../performance/optimization.md)
