# Database Configuration & Performance Guide

This comprehensive guide covers database configuration, optimization, and performance management for multi-tenant Laravel applications using the artflow-studio/tenancy package.

## 📋 Table of Contents

### Configuration Guides
- [Database Template Configuration](./database-template.md) - Core database connection setup
- [PDO Configuration Guide](./pdo-configuration.md) - Critical PDO settings for multi-tenancy
- [MySQL Configuration Guide](./mysql-configuration.md) - Server-level optimizations

### Performance & Optimization
- [Concurrent Connections](./concurrent-connections.md) - Connection pooling and management
- Performance Monitoring & Diagnostics
- Load Testing & Stress Testing

### Configuration Files
- [MySQL Configuration Template](../config/mysql-tenancy.cnf) - Optimized my.cnf settings

## 🚀 Quick Start

### 1. Initial Setup
```bash
# Check and configure database privileges and PDO settings
php artisan tenant:check-privileges --interactive

# Diagnose performance issues
php artisan tenancy:diagnose-performance --check-mysql --detailed
```

### 2. Essential Configuration Steps

#### Step 1: Add Tenant Template Connection
```bash
php artisan tenant:check-privileges --interactive
# Choose: "add-tenant-template"
```

#### Step 2: Configure PDO Settings
```bash
php artisan tenant:check-privileges --interactive
# Choose: "check-pdo-config" -> "auto-configure"
```

#### Step 3: Optimize MySQL Server
Apply the [MySQL configuration template](../config/mysql-tenancy.cnf) to your MySQL server.

#### Step 4: Test Performance
```bash
# Light stress test
php artisan tenancy:stress-test --users=20 --operations=100 --tenants=3 --duration=10

# Connection pool test
php artisan tenancy:connection-pool test
```

## 🔧 Configuration Templates

### Database Configuration (config/database.php)

```php
'connections' => [
    // Central database connection
    'mysql' => [
        'driver' => 'mysql',
        // ... standard settings
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false, // CRITICAL for multi-tenancy
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            PDO::ATTR_TIMEOUT => 30,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", SESSION wait_timeout=300, SESSION interactive_timeout=300',
        ]) : [],
    ],
    
    // Optimized tenant template connection
    'tenant_template' => [
        'driver' => 'mysql',
        'database' => null, // Set dynamically by tenancy
        // ... optimized settings for tenants
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be FALSE
            PDO::ATTR_TIMEOUT => 10, // Shorter timeout for tenants
            // ... other optimized settings
        ]) : [],
    ],
],
```

### Tenancy Configuration (config/tenancy.php)

```php
'database' => [
    'central_connection' => env('DB_CONNECTION', 'mysql'),
    'template_tenant_connection' => 'tenant_template', // Use optimized template
    'prefix' => 'tenant',
    'suffix' => '',
],
```

## 🔍 Diagnostic Tools

### Performance Diagnostics
```bash
# Comprehensive performance check
php artisan tenancy:diagnose-performance --check-mysql --detailed

# Auto-fix detected issues
php artisan tenancy:diagnose-performance --fix-issues
```

### Connection Pool Management
```bash
# Check pool status
php artisan tenancy:connection-pool status --detailed

# Health check
php artisan tenancy:connection-pool health

# Test functionality
php artisan tenancy:connection-pool test --tenant=test_123
```

### Database Privilege Checking
```bash
# Interactive privilege setup
php artisan tenant:check-privileges --interactive

# Test root user connection
php artisan tenant:check-privileges --test-root
```

### Stress Testing
```bash
# Light test
php artisan tenancy:stress-test --users=10 --operations=50 --duration=30

# Heavy test
php artisan tenancy:stress-test --users=50 --operations=500 --duration=60
```

## ⚠️ Critical Settings for Multi-Tenancy

### 1. PDO Persistent Connections
```php
// ❌ NEVER do this in multi-tenant applications
PDO::ATTR_PERSISTENT => true,

// ✅ Always use this for multi-tenancy
PDO::ATTR_PERSISTENT => false,
```

**Why**: Persistent connections can leak tenant data between requests.

### 2. Connection Timeouts
```php
// Central connection (longer operations)
PDO::ATTR_TIMEOUT => 30,

// Tenant connections (faster switching)
PDO::ATTR_TIMEOUT => 10,
```

### 3. MySQL Server Settings
```ini
# Critical for multi-tenancy
max_connections = 500
wait_timeout = 300
interactive_timeout = 300
innodb_buffer_pool_size = 1G
```

## 📊 Performance Monitoring

### Connection Pool Statistics
```php
use ArtflowStudio\Tenancy\Services\Database\TenantConnectionPoolManager;

$poolManager = TenantConnectionPoolManager::create();
$stats = $poolManager->getPoolStatistics();

// Monitor pool usage
Log::info('Connection Pool Stats', $stats);
```

### Health Checks
```php
$health = $poolManager->healthCheck();
if ($health['status'] !== 'healthy') {
    Log::warning('Connection pool issues', $health);
}
```

## 🚨 Troubleshooting

### Common Issues

#### "Too many connections" Error
1. Check MySQL `max_connections` setting
2. Verify `PDO::ATTR_PERSISTENT => false`
3. Monitor connection pool usage
4. Implement proper connection cleanup

#### Tenant Data Leakage
1. Ensure `PDO::ATTR_PERSISTENT => false`
2. Test tenant isolation
3. Check database configuration

#### Poor Performance
1. Run performance diagnostics
2. Optimize MySQL configuration
3. Use connection pooling
4. Monitor slow queries

### Diagnostic Commands
```bash
# Full system check
php artisan tenancy:diagnose-performance --check-mysql --detailed

# Connection-specific issues
php artisan tenancy:connection-pool health

# Database privilege issues
php artisan tenant:check-privileges --interactive
```

## 📚 Related Documentation

- [Installation Guide](../installation/)
- [Performance Optimization](../performance/)
- [Security Best Practices](../security/)
- [API Reference](../api/)

## 🔄 Maintenance

### Regular Tasks
1. **Weekly**: Run performance diagnostics
2. **Daily**: Check connection pool health
3. **Monthly**: Review MySQL slow query log
4. **Quarterly**: Update MySQL configuration as needed

### Monitoring Checklist
- [ ] Connection pool usage < 80%
- [ ] No persistent connections enabled
- [ ] MySQL max_connections sufficient
- [ ] Tenant isolation working
- [ ] Performance metrics stable
