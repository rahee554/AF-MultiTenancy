# 🔧 AF-MultiTenancy Installation Troubleshooting

## 📋 Installation Process Overview

The `af-tenancy:install` command performs the following steps:

1. **Configuration Publishing** - Publishes config files
2. **Migration Publishing** - Copies migration files
3. **Documentation Publishing** - Publishes docs and stubs
4. **Database Configuration Update** - Modifies `config/database.php` automatically
5. **Environment Variable Update** - Adds 30+ environment variables
6. **Migration Execution** - Runs central and tenant migrations
7. **Performance Setup** - Configures cached lookup
8. **Cache Clearing** - Clears application caches

## 🏗️ Database Configuration Changes

The installation automatically modifies your `config/database.php` file to:

### 1. Change Default Connection
```php
// Before
'default' => env('DB_CONNECTION', 'sqlite'),

// After  
'default' => env('DB_CONNECTION', 'mysql'),
```

### 2. Add Performance Optimizations to MySQL
```php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    
    // ===== MULTI-TENANT PERFORMANCE OPTIMIZATIONS =====
    
    // Enable persistent connections for better performance
    PDO::ATTR_PERSISTENT => env('TENANT_DB_PERSISTENT', true),
    
    // Use native prepared statements (faster)
    PDO::ATTR_EMULATE_PREPARES => false,
    
    // Buffer queries for better performance with large result sets
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    
    // Connection timeout settings
    PDO::ATTR_TIMEOUT => env('DB_CONNECTION_TIMEOUT', 5),
    
    // Error handling
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    
    // Default fetch mode
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
]) : [],
```

## 🌍 Environment Variables Added

### Database Configuration
```env
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql
TENANT_DB_CHARSET=utf8mb4
TENANT_DB_COLLATION=utf8mb4_unicode_ci
TENANT_DB_PERSISTENT=true
DB_CONNECTION_TIMEOUT=5
```

### Homepage Management  
```env
TENANT_HOMEPAGE_ENABLED=true
TENANT_HOMEPAGE_VIEW_PATH=tenants
TENANT_HOMEPAGE_AUTO_CREATE_DIR=true
TENANT_HOMEPAGE_FALLBACK_REDIRECT=/login
```

### Cache Configuration (Database Default)
```env
TENANT_CACHE_DRIVER=database
TENANT_CACHE_PREFIX=tenant_
TENANT_CACHE_TTL=3600
TENANT_CACHE_STATS_TTL=300
```

### API & Security
```env
TENANT_API_KEY=your-secure-api-key-here
TENANT_API_NO_AUTH=false
TENANT_API_ALLOW_LOCALHOST=true
TENANT_API_RATE_LIMIT=true
TENANT_API_RATE_LIMIT_ATTEMPTS=60
TENANT_API_RATE_LIMIT_DECAY=1
```

### Monitoring & Performance
```env
TENANT_MONITORING_ENABLED=true
TENANT_MONITORING_RETENTION_DAYS=30
TENANT_MONITORING_PERFORMANCE=true
```

### Backup Configuration
```env
TENANT_BACKUP_ENABLED=false
TENANT_BACKUP_DISK=local
TENANT_BACKUP_RETENTION_DAYS=7
```

### Stancl/Tenancy Integration
```env
TENANCY_CACHED_LOOKUP=true
TENANCY_CACHE_TTL=3600
TENANCY_CACHE_STORE=database
```

## 🏠 Homepage Feature

### Automatic Directory Creation
When a tenant enables homepage, the system automatically creates:
```
resources/views/tenants/{domain}/home.blade.php
```

### Template Content
The auto-generated homepage includes:
- Tenant information display
- Domain details
- Status indicators
- Quick action buttons
- Customization instructions

## 🔍 Common Installation Issues

### Issue 1: Database Configuration Not Updated
**Problem:** MySQL optimizations not applied to `config/database.php`
**Solution:** The installation command automatically detects and updates the database configuration

### Issue 2: Environment Variables Not Added
**Problem:** Missing environment variables in `.env` file
**Solution:** The installation shows exactly which variables were added with verbose output

### Issue 3: Homepage Views Not Created
**Problem:** Homepage directories not auto-created when enabled
**Solution:** Ensure `TENANT_HOMEPAGE_AUTO_CREATE_DIR=true` is set

### Issue 4: Cache Driver Issues
**Problem:** Redis cache errors when using database cache
**Solution:** Default cache driver changed to `database` for better compatibility

## ✅ Verification Steps

After installation, verify:

1. **Check Database Configuration:**
   ```bash
   grep -A 20 "mysql.*=>" config/database.php
   ```

2. **Verify Environment Variables:**
   ```bash
   grep "TENANT_" .env
   ```

3. **Test API Endpoints:**
   ```bash
   curl "http://yourapp.com/api/health?api_key=your-api-key"
   ```

4. **Check Homepage Creation:**
   - Enable homepage for a tenant
   - Verify directory creation at `resources/views/tenants/{domain}/`

5. **Test Tenancy Resolution:**
   ```bash
   php artisan tenancy:health
   ```

## 🚀 Post-Installation Steps

1. **Update API Key:**
   ```env
   TENANT_API_KEY=your-actual-secure-api-key-here
   ```

2. **Configure Database Connection:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_central_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

3. **Test Tenant Creation:**
   ```bash
   php artisan tenant:manage create
   ```

4. **Enable Homepage for Testing:**
   ```bash
   php artisan tenant:manage enable-homepage
   ```

## 📊 Performance Notes

- **Database Cache:** Default cache driver changed to `database` for better out-of-the-box compatibility
- **Persistent Connections:** Enabled by default for better performance
- **Connection Timeout:** Set to 5 seconds to prevent hanging connections
- **Cached Lookup:** Enabled for fast tenant resolution

## 🆘 Getting Help

If you encounter issues:

1. Check the installation logs for specific error messages
2. Verify your database connection settings
3. Ensure your MySQL server supports the required features
4. Check file permissions for view directory creation
5. Review the environment variables for typos

The installation process is designed to be robust and handle most common Laravel setups automatically.
