# PDO Configuration Guide for Multi-Tenancy

This guide provides comprehensive information about PDO configuration options that are critical for multi-tenant Laravel applications using the artflow-studio/tenancy package.

## Overview

PDO (PHP Data Objects) configuration is crucial for multi-tenant applications because improper settings can lead to:
- Connection pool exhaustion
- Tenant data leakage
- Poor performance
- Memory issues
- Security vulnerabilities

## Critical PDO Settings for Multi-Tenancy

### 1. PDO::ATTR_PERSISTENT (CRITICAL)

```php
PDO::ATTR_PERSISTENT => false, // MUST be false for multi-tenancy
```

**Why this is critical:**
- **`true`**: Connections persist across requests and can be reused
- **`false`**: New connection created for each request
- **Multi-tenancy issue**: Persistent connections can leak tenant data between requests

**Example of the problem:**
```php
// Request 1: User accesses Tenant A
$connection->exec("USE tenant_a_database");
// Connection persists with tenant_a_database selected

// Request 2: User accesses Tenant B (same connection reused)
// Still connected to tenant_a_database - DATA LEAKAGE!
```

### 2. PDO::ATTR_ERRMODE

```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
```

**Options:**
- `PDO::ERRMODE_SILENT`: No errors reported (dangerous)
- `PDO::ERRMODE_WARNING`: PHP warnings (not recommended)
- `PDO::ERRMODE_EXCEPTION`: Throws exceptions (recommended)

**Why use exceptions:**
- Proper error handling in multi-tenant context
- Easier debugging of tenant-specific database issues
- Prevents silent failures that could affect data integrity

### 3. PDO::ATTR_EMULATE_PREPARES

```php
PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
```

**Options:**
- `true`: PHP emulates prepared statements
- `false`: Use MySQL native prepared statements

**Benefits of native prepared statements:**
- Better performance
- Enhanced security
- Reduced memory usage
- Proper type handling

### 4. PDO::ATTR_DEFAULT_FETCH_MODE

```php
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
```

**Common options:**
- `PDO::FETCH_ASSOC`: Associative array (recommended)
- `PDO::FETCH_NUM`: Numeric array
- `PDO::FETCH_BOTH`: Both associative and numeric (memory waste)
- `PDO::FETCH_OBJ`: Object

**Why FETCH_ASSOC:**
- Consistent with Laravel's Eloquent behavior
- Better readability
- No memory waste from duplicate data

### 5. PDO::MYSQL_ATTR_USE_BUFFERED_QUERY

```php
PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Reduce memory usage
```

**Options:**
- `true`: Load entire result set into memory
- `false`: Stream results as needed

**Multi-tenant considerations:**
- With multiple tenants, memory usage can multiply quickly
- Streaming results prevents memory exhaustion
- Better for large datasets common in tenant databases

### 6. PDO::ATTR_TIMEOUT

```php
PDO::ATTR_TIMEOUT => 10, // Connection timeout in seconds
```

**Recommended timeouts:**
- **Central connection**: 30 seconds (longer operations)
- **Tenant connections**: 10 seconds (faster switching)

**Benefits:**
- Prevents hanging connections
- Faster failure detection
- Better resource management

### 7. PDO::ATTR_STRINGIFY_FETCHES

```php
PDO::ATTR_STRINGIFY_FETCHES => false, // Preserve data types
```

**Options:**
- `true`: Convert all values to strings
- `false`: Preserve original data types

**Why preserve types:**
- Consistent with Eloquent behavior
- Proper type casting in models
- Better performance in some cases

### 8. PDO::MYSQL_ATTR_INIT_COMMAND

```php
PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", SESSION wait_timeout=120, SESSION interactive_timeout=120',
```

**Purpose:**
- Execute commands when connection is established
- Set session variables for consistency
- Configure timeouts and SQL modes

**Multi-tenant optimizations:**
- **Strict SQL mode**: Prevents data integrity issues
- **Session timeouts**: Shorter for tenant connections
- **Character set**: Ensure consistent encoding

## Complete Configuration Examples

### Central Database Connection

```php
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
        
        // Error handling
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Connection settings - CRITICAL for multi-tenancy
        PDO::ATTR_PERSISTENT => false,
        
        // Performance settings
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        PDO::ATTR_TIMEOUT => 30,
        
        // Data handling
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        
        // Session initialization
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", SESSION wait_timeout=300, SESSION interactive_timeout=300',
    ]) : [],
],
```

### Tenant Template Connection

```php
'tenant_template' => [
    'driver' => 'mysql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => null, // Set dynamically by tenancy
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
        
        // Error handling
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Connection settings - CRITICAL for multi-tenancy
        PDO::ATTR_PERSISTENT => false, // NEVER true for tenants
        
        // Performance settings - optimized for quick tenant switching
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        PDO::ATTR_TIMEOUT => 10, // Shorter timeout for tenants
        
        // Data handling
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        
        // Session initialization - shorter timeouts for tenant connections
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", SESSION wait_timeout=120, SESSION interactive_timeout=120',
    ]) : [],
],
```

## Advanced PDO Options

### SSL Configuration

```php
// SSL options for secure connections
PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
PDO::MYSQL_ATTR_SSL_CERT => env('MYSQL_ATTR_SSL_CERT'),
PDO::MYSQL_ATTR_SSL_KEY => env('MYSQL_ATTR_SSL_KEY'),
PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
```

### Compression

```php
// Enable MySQL compression (for slow networks)
PDO::MYSQL_ATTR_COMPRESS => true,
```

### Local Infile

```php
// Disable for security (prevent local file inclusion attacks)
PDO::MYSQL_ATTR_LOCAL_INFILE => false,
```

## Performance Monitoring

### Connection Monitoring

```php
// Monitor PDO connection options
$pdo = DB::connection()->getPdo();
$options = [
    PDO::ATTR_PERSISTENT => 'Persistent',
    PDO::ATTR_ERRMODE => 'Error Mode',
    PDO::ATTR_DEFAULT_FETCH_MODE => 'Fetch Mode',
    PDO::ATTR_EMULATE_PREPARES => 'Emulate Prepares',
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => 'Buffered Query',
    PDO::ATTR_TIMEOUT => 'Timeout',
];

foreach ($options as $option => $name) {
    try {
        $value = $pdo->getAttribute($option);
        echo "{$name}: " . var_export($value, true) . "\n";
    } catch (PDOException $e) {
        echo "{$name}: Not available\n";
    }
}
```

### Performance Testing

```php
// Test query performance with different PDO settings
$start = microtime(true);
$result = DB::select('SELECT * FROM large_table LIMIT 1000');
$end = microtime(true);
$duration = ($end - $start) * 1000; // Convert to milliseconds
echo "Query took: {$duration}ms\n";
echo "Memory usage: " . memory_get_usage(true) / 1024 / 1024 . "MB\n";
```

## Troubleshooting Common Issues

### 1. "MySQL server has gone away"

**Cause**: Connection timeout or persistent connection issues
**Solution**:
```php
PDO::ATTR_PERSISTENT => false, // Ensure not using persistent connections
PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION wait_timeout=120', // Set appropriate timeout
```

### 2. Memory exhaustion with large result sets

**Cause**: Buffered queries loading everything into memory
**Solution**:
```php
PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Stream results
```

### 3. Tenant data appearing in wrong tenant

**Cause**: Persistent connections reusing database context
**Solution**:
```php
PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be false
```

### 4. Slow prepared statements

**Cause**: PHP emulating prepared statements
**Solution**:
```php
PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
```

## Validation and Testing

### Test PDO Configuration

```bash
# Use the diagnostic command to check PDO settings
php artisan tenancy:diagnose-performance --detailed

# Run stress tests to verify performance
php artisan tenancy:stress-test --users=20 --operations=100
```

### Manual Testing

```php
// Test tenant isolation
Tenant::first()->run(function () {
    // Verify correct database is selected
    $database = DB::select('SELECT DATABASE() as db')[0]->db;
    echo "Current database: {$database}\n";
    
    // Verify PDO settings
    $pdo = DB::connection()->getPdo();
    $persistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT);
    echo "Persistent: " . ($persistent ? 'true' : 'false') . "\n";
});
```

## Best Practices

1. **Never use persistent connections** for tenant databases
2. **Always use exceptions** for error handling
3. **Use native prepared statements** for better performance
4. **Stream large result sets** to prevent memory issues
5. **Set appropriate timeouts** for tenant connections
6. **Monitor connection usage** in production
7. **Test tenant isolation** regularly
8. **Use strict SQL mode** to prevent data integrity issues

## Related Documentation

- [Database Template Configuration](./database-template.md)
- [MySQL Configuration Guide](./mysql-configuration.md)
- [Performance Optimization](../performance/optimization.md)
- [Security Best Practices](../security/database-security.md)
