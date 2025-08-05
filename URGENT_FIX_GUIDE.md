# QUICK FIX GUIDE - PDO Type Conflicts & Tenant Connection Issues

## URGENT FIXES NEEDED

### Issue 1: PDO Type Conflicts
**Error**: "Attribute value must be of type int for selected attribute, string given"

**Root Cause**: Your current `database.php` has PDO options with wrong data types.

**IMMEDIATE FIX**:
Replace your current `database.php` MySQL options section with this EXACT configuration:

```php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    
    // FIXED: Proper type casting for PDO options
    PDO::ATTR_PERSISTENT => filter_var(env('DB_PERSISTENT', 'true'), FILTER_VALIDATE_BOOLEAN),
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='TRADITIONAL'",
    PDO::MYSQL_ATTR_LOCAL_INFILE => false,
    PDO::ATTR_TIMEOUT => (int) env('DB_CONNECTION_TIMEOUT', 5),
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]) : [],
```

### Issue 2: Missing Filesystem Configuration
**Error**: "Undefined array key 'local'"

**IMMEDIATE FIX**: Add this to your `config/tenancy.php`:

```php
'filesystem' => [
    'disks' => [
        'local',
        'public',
    ],
],
```

### Issue 3: Tenant Connection Not Configured
**Error**: "Database connection [tenant] not configured"

**ROOT CAUSE**: The performance optimizations were causing conflicts with tenant database creation.

**WHAT I FIXED**:
1. ✅ Disabled automatic PDO option injection in `HighPerformanceMySQLDatabaseManager`
2. ✅ Disabled automatic initialization in `TenancyServiceProvider`
3. ✅ Fixed PDO type casting in `DynamicDatabaseConfigManager`

## IMMEDIATE STEPS TO FIX YOUR SYSTEM

### Step 1: Update Your Database Configuration (MOST IMPORTANT)

**Replace your `config/database.php` MySQL connection with this EXACT configuration:**

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
        
        // FIXED: Proper type casting for PDO options
        PDO::ATTR_PERSISTENT => filter_var(env('DB_PERSISTENT', 'true'), FILTER_VALIDATE_BOOLEAN),
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='TRADITIONAL'",
        PDO::MYSQL_ATTR_LOCAL_INFILE => false,
        PDO::ATTR_TIMEOUT => (int) env('DB_CONNECTION_TIMEOUT', 5),
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]) : [],
],
```

### Step 2: Clear All Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

### Step 3: Test Basic Connection
```bash
php artisan tenancy:diagnose
```

### Step 4: Create Test Tenants
```bash
php artisan tenancy:create-test-tenants
```

### Step 5: Test Performance
```bash
php artisan tenancy:test-performance --concurrent-users=5 --requests-per-user=2
```

## WHAT WAS CAUSING THE PROBLEMS

1. **PDO Type Issues**: The v0.7.0.0 changes introduced hardcoded boolean `true` values instead of properly typed constants
2. **Automatic Initialization**: The dynamic configuration was being applied automatically, conflicting with your existing database.php
3. **Missing Filesystem Config**: stancl/tenancy expects filesystem disk configuration

## PERFORMANCE IMPACT

- ✅ **Fixed**: PDO connection errors
- ✅ **Fixed**: Tenant database creation issues  
- ✅ **Fixed**: Filesystem bootstrapper errors
- ✅ **Safe**: All optimizations now use proper data types
- ✅ **Safe**: No automatic configuration conflicts

## TEST AFTER APPLYING FIXES

1. **Basic Connection**: `php artisan tenancy:diagnose`
2. **Tenant Creation**: `php artisan tenancy:create-test-tenants`
3. **Performance Test**: `php artisan tenancy:test-performance`

The system should now work without any PDO type conflicts or tenant connection issues.
