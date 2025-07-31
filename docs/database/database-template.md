# Database Template Configuration for Multi-Tenancy

This guide explains how to properly configure database connection templates for optimal multi-tenant performance with the artflow-studio/tenancy package.

## Overview

The database template configuration is crucial for multi-tenant applications as it defines how tenant database connections are created and managed. Proper configuration prevents connection pool exhaustion, tenant data leakage, and performance bottlenecks.

## Configuration Structure

### 1. Central Database Connection (config/database.php)

```php
'connections' => [
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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be FALSE for multi-tenancy
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            PDO::ATTR_TIMEOUT => 30,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", SESSION wait_timeout=300, SESSION interactive_timeout=300',
        ]) : [],
    ],
]
```

### 2. Tenant Template Connection

Add this dedicated connection template for tenant databases:

```php
// Optimized connection template for multi-tenant databases
'tenant_template' => [
    'driver' => 'mysql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => null, // Will be set dynamically by tenancy
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
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be FALSE for multi-tenancy
        PDO::ATTR_EMULATE_PREPARES => false, // Better performance
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Reduce memory usage
        PDO::ATTR_TIMEOUT => 10, // Shorter timeout for tenants
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", SESSION wait_timeout=120, SESSION interactive_timeout=120',
    ]) : [],
],
```

### 3. Tenancy Configuration (config/tenancy.php)

```php
'database' => [
    'central_connection' => env('DB_CONNECTION', 'mysql'),
    
    /**
     * Connection used as a "template" for the dynamically created tenant database connection.
     * Note: don't name your template connection tenant. That name is reserved by package.
     */
    'template_tenant_connection' => 'tenant_template',
    
    /**
     * Tenant database names are created like this:
     * prefix + tenant_id + suffix.
     */
    'prefix' => 'tenant',
    'suffix' => '',
    
    // ... other database settings
],
```

## Critical PDO Options Explained

### 1. PDO::ATTR_PERSISTENT = false
**CRITICAL FOR MULTI-TENANCY**
```php
PDO::ATTR_PERSISTENT => false, // NEVER set to true for multi-tenancy
```
- **Why**: Persistent connections are reused across requests
- **Problem**: Can cause tenant data to leak between different tenants
- **Solution**: Always set to `false` for tenant connections

### 2. PDO::ATTR_EMULATE_PREPARES = false
```php
PDO::ATTR_EMULATE_PREPARES => false, // Better performance
```
- **Why**: Uses native prepared statements for better performance
- **Benefit**: Reduces CPU usage and improves query execution time

### 3. PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false
```php
PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Reduce memory usage
```
- **Why**: Prevents loading entire result sets into memory
- **Benefit**: Lower memory usage, especially for large result sets

### 4. Connection Timeouts
```php
PDO::ATTR_TIMEOUT => 10, // Shorter timeout for tenants
```
- **Central Connection**: 30 seconds (longer operations)
- **Tenant Connections**: 10 seconds (faster switching)

### 5. Session Variables
```php
PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="...", SESSION wait_timeout=120, SESSION interactive_timeout=120'
```
- **Strict SQL Mode**: Prevents data integrity issues
- **Timeouts**: Shorter for tenant connections to free up resources quickly

## Connection Pool Management

### Concurrent Connections Strategy

For true concurrent connections in multi-tenant environments:

```php
// In your TenantService or similar
class TenantConnectionManager
{
    private array $connectionPool = [];
    private int $maxConnections = 50;
    
    public function getConnection(string $tenantId): Connection
    {
        // Reuse existing connection if available
        if (isset($this->connectionPool[$tenantId])) {
            return $this->connectionPool[$tenantId];
        }
        
        // Create new connection if pool not full
        if (count($this->connectionPool) < $this->maxConnections) {
            $connection = $this->createTenantConnection($tenantId);
            $this->connectionPool[$tenantId] = $connection;
            return $connection;
        }
        
        // Pool is full - create temporary connection
        return $this->createTenantConnection($tenantId);
    }
    
    public function releaseConnection(string $tenantId): void
    {
        unset($this->connectionPool[$tenantId]);
    }
}
```

## Environment Variables

Set these in your `.env` file:

```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tenancy_central
DB_USERNAME=root
DB_PASSWORD=your_password

# SSL Configuration (optional)
MYSQL_ATTR_SSL_CA=/path/to/ca.pem

# Character Set
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

## Testing Your Configuration

Use the diagnostic command to verify your setup:

```bash
php artisan tenancy:diagnose-performance --check-mysql --detailed
```

## Common Issues and Solutions

### 1. "Too many connections" Error
**Symptoms**: MySQL error 1040
**Solution**: 
- Increase `max_connections` in MySQL configuration
- Ensure `PDO::ATTR_PERSISTENT => false`
- Implement proper connection cleanup

### 2. Tenant Data Leakage
**Symptoms**: Data from one tenant appears in another
**Solution**:
- Verify `PDO::ATTR_PERSISTENT => false`
- Check database isolation is working
- Run tenant isolation tests

### 3. Slow Database Switching
**Symptoms**: High latency when switching between tenants
**Solution**:
- Use shorter timeouts in tenant template
- Implement connection pooling
- Optimize MySQL configuration

### 4. Memory Usage Issues
**Symptoms**: High memory consumption with many tenants
**Solution**:
- Set `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false`
- Implement connection cleanup
- Monitor and limit concurrent connections

## Performance Monitoring

### Monitor Connection Usage
```sql
-- Check current connections
SHOW PROCESSLIST;

-- Monitor connection counts
SELECT 
    db as Database_Name,
    COUNT(*) as Connection_Count
FROM INFORMATION_SCHEMA.PROCESSLIST 
WHERE db IS NOT NULL 
GROUP BY db 
ORDER BY Connection_Count DESC;
```

### Check for Long-Running Queries
```sql
-- Find long-running queries (>5 seconds)
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    info
FROM INFORMATION_SCHEMA.PROCESSLIST 
WHERE time > 5 
    AND command != 'Sleep'
ORDER BY time DESC;
```

## Best Practices

1. **Always use the tenant template**: Never use the central connection for tenant operations
2. **Monitor connection usage**: Keep track of active connections per tenant
3. **Implement cleanup**: Release connections when no longer needed
4. **Test isolation**: Regularly verify tenant data isolation
5. **Use connection pooling**: For high-load applications, implement proper pooling
6. **Monitor performance**: Use MySQL slow query log and performance monitoring

## Related Documentation

- [MySQL Configuration Guide](./mysql-configuration.md)
- [PDO Configuration Reference](./pdo-configuration.md)
- [Performance Optimization Guide](../performance/optimization.md)
- [Troubleshooting Guide](../guides/troubleshooting.md)
