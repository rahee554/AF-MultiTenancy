# Installation Guide

## 🚀 Complete Installation Guide for ArtflowStudio Tenancy

This guide will walk you through setting up the ArtflowStudio Tenancy package for production use with all performance optimizations.

## 📋 Prerequisites

Before installing, ensure you have:

- **PHP 8.1+**
- **Laravel 11.x**
- **MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+**
- **Composer 2.x**
- **Redis** (recommended for production)

## 🔧 Step-by-Step Installation

### Step 1: Install the Package

```bash
composer require artflow-studio/tenancy
```

### Step 2: Publish Configuration Files

```bash
# Publish the tenancy configuration
php artisan vendor:publish --tag=artflow-tenancy-config

# This will create:
# - config/tenancy.php (tenancy configuration)
# - config/artflow-tenancy.php (package-specific settings)
```

### Step 3: Update Database Configuration

**Edit `config/database.php`** and add performance optimizations to your database connection:

```php
<?php
// config/database.php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
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
                // ✅ PERFORMANCE OPTIMIZATIONS
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='TRADITIONAL'",
            ]) : [],
        ],
        
        // Add other database connections as needed...
    ],
];
```

### Step 4: Environment Configuration

**Update your `.env` file** with tenancy-specific settings:

```env
# Basic Tenancy Settings
TENANT_DB_PREFIX=tenant_
APP_DOMAIN=yourdomain.com

# ✅ PERFORMANCE OPTIMIZATIONS (Recommended for Production)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_DRIVER=redis

# Database Connection Pooling
DB_POOL_MIN=5
DB_POOL_MAX=50
DB_POOL_IDLE_TIMEOUT=30
DB_POOL_MAX_LIFETIME=3600

# Tenancy Performance Settings
TENANCY_ENABLE_CACHING=true
TENANCY_ENABLE_POOLING=true
TENANCY_MAX_CACHED_TENANTS=100
TENANCY_CACHE_TTL=300

# Connection Monitoring
DB_SLOW_QUERY_THRESHOLD=1000
DB_CONNECTION_TIMEOUT=5
DB_READ_TIMEOUT=30

# Optional: Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Step 5: Run Database Migrations

```bash
# Run the central database migrations
php artisan migrate

# The package will automatically create:
# - tenants table
# - domains table
# - Required indexes for performance
```

### Step 6: Create Your First Tenant

```bash
# Interactive tenant creation
php artisan tenant:manage

# Or create programmatically
php artisan tinker
```

```php
// In tinker
use ArtflowStudio\Tenancy\Models\Tenant;

// Create tenant with auto-generated database
$tenant = Tenant::create(['id' => 'my-first-tenant']);

// Or with custom database name
$tenant = Tenant::create([
    'id' => 'my-company',
    'database' => 'company_2025_db'
]);

// Create domain for the tenant
$tenant->domains()->create(['domain' => 'company.yourdomain.com']);
```

### Step 7: Test the Installation

```bash
# Run comprehensive tests
php artisan tenancy:test-comprehensive

# Test performance
php artisan tenancy:test-performance --test-isolation --test-persistence

# Check health
php artisan tenancy:health
```

**Expected Output:**
```
🏆 Success Rate: 100% - EXCELLENT
✅ Database isolation: 100% success
✅ Database persistence: 100% success
⚡ Performance: 46+ req/s
```

## 🎯 Verification Steps

### 1. Check Tenant Database Creation

```bash
# List all databases to see tenant databases
mysql -u root -p -e "SHOW DATABASES;"

# You should see databases like:
# - tenant_[uuid]
# - your_custom_db_name
```

### 2. Test Tenant Context Switching

```php
// Create a test file: test-tenancy.php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use ArtflowStudio\Tenancy\Models\Tenant;
use App\Models\User;

$tenant1 = Tenant::first();

$tenant1->run(function () {
    echo "Current database: " . \DB::connection()->getDatabaseName() . "\n";
    echo "Users count: " . User::count() . "\n";
});
```

```bash
php test-tenancy.php
```

### 3. Test Admin Dashboard

Visit: `http://your-app.com/admin/tenancy`

You should see:
- Tenant management interface
- Performance metrics
- Database status
- Health checks

## 🚀 Production Deployment

### 1. Redis Setup (Recommended)

```bash
# Install Redis
sudo apt-get install redis-server

# Or using Docker
docker run -d --name redis -p 6379:6379 redis:7-alpine
```

### 2. Database Optimization

**MySQL Configuration** (`/etc/mysql/my.cnf`):
```ini
[mysqld]
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
query_cache_size = 32M
```

### 3. Application Server Configuration

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name *.yourdomain.com;
    root /var/www/html/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Process Manager (Supervisor)

**Create `/etc/supervisor/conf.d/laravel-worker.conf`:**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
```

## 🔧 Advanced Configuration

### Custom Tenant Model

```php
<?php
// app/Models/CustomTenant.php

namespace App\Models;

use ArtflowStudio\Tenancy\Models\Tenant as BaseTenant;

class CustomTenant extends BaseTenant
{
    protected $fillable = [
        'id',
        'name',
        'database',
        'plan',
        'status',
        'max_users',
    ];
    
    protected $casts = [
        'max_users' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Custom methods
    public function isActive()
    {
        return $this->status === 'active';
    }
    
    public function canCreateUser()
    {
        return $this->users()->count() < $this->max_users;
    }
}
```

**Update `config/tenancy.php`:**
```php
'tenant_model' => \App\Models\CustomTenant::class,
```

### Custom Database Naming Strategy

```php
<?php
// app/Services/CustomDatabaseNaming.php

namespace App\Services;

use ArtflowStudio\Tenancy\Models\Tenant;

class CustomDatabaseNaming
{
    public static function generateName(Tenant $tenant): string
    {
        // Custom naming: company_year_id
        $year = date('Y');
        $cleanId = preg_replace('/[^a-zA-Z0-9]/', '', $tenant->id);
        
        return "company_{$year}_{$cleanId}";
    }
}
```

## 🐛 Troubleshooting

### Common Issues & Solutions

**Issue 1: "Class not found" errors**
```bash
# Solution: Clear and regenerate autoload
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

**Issue 2: Database connection errors**
```bash
# Solution: Check database permissions
mysql -u root -p -e "GRANT ALL ON *.* TO 'laravel_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

**Issue 3: Performance is slow**
```bash
# Solution: Enable Redis and check configuration
redis-cli ping  # Should return PONG
php artisan config:cache
php artisan route:cache
```

**Issue 4: Tenant isolation not working**
```bash
# Solution: Verify event listeners are registered
php artisan event:list | grep -i tenancy
```

### Debug Commands

```bash
# Check configuration
php artisan config:show tenancy

# List all tenants
php artisan tenants:list

# Check tenant database
php artisan tinker
>>> $tenant = \ArtflowStudio\Tenancy\Models\Tenant::first()
>>> $tenant->database()->getName()

# Test tenant switching
>>> tenancy()->initialize($tenant)
>>> \DB::connection()->getDatabaseName()
```

## 📊 Performance Monitoring

### Enable Performance Logging

**Add to `config/logging.php`:**
```php
'channels' => [
    'tenancy' => [
        'driver' => 'single',
        'path' => storage_path('logs/tenancy.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

### Monitor Performance Metrics

```bash
# Regular performance checks
php artisan tenancy:test-performance

# Monitor cache efficiency
php artisan tinker
>>> \ArtflowStudio\Tenancy\Services\TenantContextCache::getStats()
```

## 🎉 Success!

If all tests pass, you now have a production-ready multi-tenant Laravel application with:

- ✅ **High-performance database switching**
- ✅ **Perfect tenant isolation**
- ✅ **Redis caching for optimal speed**
- ✅ **Admin dashboard for management**
- ✅ **Comprehensive testing tools**
- ✅ **Production-ready configuration**

## 📞 Need Help?

- **Documentation**: Check the [full documentation](../README.md)
- **Issues**: Report bugs on [GitHub Issues](https://github.com/artflow-studio/tenancy/issues)
- **Community**: Join our [Discord server](https://discord.gg/artflow-studio)
- **Support**: Email us at support@artflow-studio.com

---

**🎯 Next Steps:** Start building your SaaS application with confidence!
