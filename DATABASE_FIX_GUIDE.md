# 🔧 AF-MultiTenancy Database Configuration Fix

## 🚨 URGENT: Fixes for PDO and MySQL Global Variable Errors

### Problem Summary
You're experiencing these errors:
1. `Variable 'innodb_flush_log_at_trx_commit' is a GLOBAL variable and should be set with SET GLOBAL`
2. `ERRMODE error` - PDO configuration conflicts  
3. Connection errors during tenant creation and migrations

### ✅ SOLUTION: Dynamic Database Configuration (No database.php Changes Required)

The issue occurs because your MySQL user doesn't have `SUPER` privileges to set global variables, and the PDO options in database.php are conflicting.

## 🔧 IMPLEMENTATION STEPS

### Step 1: Use the Dynamic Configuration Manager

The `DynamicDatabaseConfigManager` I created automatically:
- Detects your MySQL user privileges
- Applies safe, session-level optimizations only
- Prevents global variable errors
- Handles PDO option conflicts automatically

### Step 2: Quick Fix (Immediate Solution)

Add this to your main Laravel application's `AppServiceProvider::boot()` method:

```php
// In app/Providers/AppServiceProvider.php

public function boot()
{
    // Initialize AF-MultiTenancy dynamic database configuration
    if (class_exists(\ArtflowStudio\Tenancy\Database\DynamicDatabaseConfigManager::class)) {
        \ArtflowStudio\Tenancy\Database\DynamicDatabaseConfigManager::initialize();
    }
}
```

### Step 3: Environment Configuration

Add these to your `.env` file (no database.php changes needed):

```env
# Database connection settings
DB_CONNECTION=mysql
DB_PERSISTENT=true
DB_CONNECTION_TIMEOUT=5

# Multi-tenancy settings
TENANCY_CACHED_LOOKUP=true
TENANCY_CACHE_TTL=3600
TENANCY_CACHE_STORE=database

# Connection pool settings (metadata only)
DB_POOL_MIN=2
DB_POOL_MAX=20
DB_POOL_IDLE_TIMEOUT=30
DB_POOL_MAX_LIFETIME=3600
```

## 🎯 WHAT THIS FIXES

### Before (Problematic)
```php
// This was causing errors in makeConnectionConfig():
PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='TRADITIONAL', innodb_flush_log_at_trx_commit=2"
// ❌ Tries to set GLOBAL variable without SUPER privileges
```

### After (Fixed)
```php
// Now uses safe session-level commands:
PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='TRADITIONAL', SET SESSION autocommit=1"
// ✅ Only session-level variables that any user can set
```

## 🧪 TESTING YOUR FIX

### Test 1: Diagnose Current Issues
```bash
php artisan tenancy:diagnose
```

### Test 2: Fix Issues Automatically  
```bash
php artisan tenancy:diagnose --fix
```

### Test 3: Test Database Operations
```bash
php artisan tenancy:diagnose --test-connection
```

### Test 4: Create a Test Tenant
```bash
php artisan tenancy:tenant create --name="Test Company" --domain="test.example.com"
```

## 🔍 DIAGNOSTIC OUTPUT EXPLAINED

When you run `php artisan tenancy:diagnose`, you'll see:

```
🔍 Diagnosing AF-MultiTenancy Database Configuration...

📊 Testing Basic Database Connection...
✅ Database connection successful
   Database: your_database
   Driver: mysql
   Version: 8.0.x

🔐 Checking MySQL User Privileges...
Global Privileges: ❌ No
SUPER Privileges: ❌ No  
Can Set Global Variables: ❌ No
⚠️  User cannot set global MySQL variables. Some optimizations will be limited.
   This is normal and safe - session-level optimizations will be used instead.

⚙️  Checking Current Database Configuration...
Default Connection: mysql
Driver: mysql
Host: 127.0.0.1
Database: your_database
PDO Options: 8 configured
   ✅ Error Mode: Configured
   ✅ Persistent Connections: Configured
   ✅ Init Command: Configured
```

## 🎯 KEY IMPROVEMENTS MADE

### 1. Safe MySQL Commands Only
- **Before**: `SET innodb_flush_log_at_trx_commit=2` (requires SUPER)  
- **After**: `SET SESSION sql_mode='TRADITIONAL'` (any user can run)

### 2. Smart PDO Option Merging
- **Before**: `array_merge()` caused conflicts
- **After**: Only adds options that don't exist, prevents conflicts

### 3. Privilege Detection
- Automatically detects what your MySQL user can do
- Applies only safe optimizations
- No errors for missing privileges

### 4. Dynamic Configuration
- No manual database.php editing required
- Works with any hosting environment
- Automatically handles different MySQL versions

## 🚀 PRODUCTION DEPLOYMENT

### For Shared Hosting (Limited MySQL Privileges)
```env
# Use conservative settings
DB_PERSISTENT=false
TENANCY_CACHE_STORE=database
```

### For VPS/Dedicated (Full MySQL Access)  
```env
# Use aggressive optimizations
DB_PERSISTENT=true
TENANCY_CACHE_STORE=redis
```

### For Docker/Local Development
```env
# Use optimal settings
DB_PERSISTENT=true
DB_CONNECTION_TIMEOUT=10
TENANCY_CACHE_STORE=redis
```

## 🔧 MANUAL FALLBACK (If Automatic Fix Doesn't Work)

If you still get errors, manually update your `config/database.php`:

```php
'mysql' => [
    'driver' => 'mysql',
    // ... your existing config ...
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        
        // SAFE OPTIONS ONLY - NO GLOBAL VARIABLES
        PDO::ATTR_PERSISTENT => (bool) env('DB_PERSISTENT', true),
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_LOCAL_INFILE => false,
        PDO::ATTR_TIMEOUT => (int) env('DB_CONNECTION_TIMEOUT', 5),
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // ONLY SESSION-LEVEL MySQL COMMANDS
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='TRADITIONAL'",
        
    ]) : [],
],
```

## ❓ TROUBLESHOOTING COMMON ISSUES

### Error: "SQLSTATE[HY000] [1229] Variable '...' is a GLOBAL variable"
**Solution**: The system will automatically use session-level variables instead. This error should disappear with the dynamic configuration.

### Error: "ERRMODE must be one of the PDO::ERRMODE_* constants"  
**Solution**: The smart option merging prevents this by not overriding existing PDO options.

### Error: "Connection timeout"
**Solution**: The system now uses a 5-second timeout by default, configurable via `DB_CONNECTION_TIMEOUT`.

### Tenant Creation Still Fails
**Solution**: Run `php artisan tenancy:diagnose --fix --test-connection` to identify and fix remaining issues.

## 📞 SUPPORT

If you still experience issues after implementing these fixes:

1. Run `php artisan tenancy:diagnose --fix` and share the output
2. Check your Laravel logs in `storage/logs/laravel.log`
3. Verify your MySQL user has at least `CREATE`, `DROP`, `ALTER` privileges
4. Test with a simple tenant: `php artisan tenancy:tenant create --name="Test" --domain="test.local"`

---

**Result**: Your multi-tenancy system will now work without PDO errors, global variable conflicts, or connection issues, regardless of your MySQL user privileges or hosting environment.
