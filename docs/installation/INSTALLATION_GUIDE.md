# 🚀 ArtflowStudio Tenancy Installation Guide

## 🌟 What This Package Provides

### Built on stancl/tenancy 
**No more "tenant identification failed" errors!** The new Universal Middleware automatically:
- Detects tenant domains and initializes tenant context
- Falls back to central context for non-tenant domains  
- Handles unknown domains gracefully
- Provides seamless context switching

### ✅ Enhanced stancl/tenancy Integration
Built on the solid foundation of stancl/tenancy with enterprise enhancements:
- Prioritizes stancl/tenancy bootstrappers
- Extends with Redis multi-database support
- Adds Laravel Horizon & Telescope integration
- Maintains full compatibility with stancl/tenancy

## 📋 Quick Setup Checklist

### ✅ **Step 1: Installation**

```bash
# Install the package
composer require artflow-studio/tenancy

# Publish configurations
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider"

# Run migrations to create tenants and domains tables
php artisan migrate
```

### ✅ **Step 2: Environment Configuration**

Add to your `.env` file:

```env
# 🔐 Database Root Credentials (REQUIRED for tenant creation)
DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=your_mysql_root_password

# 🏢 Central Domain Configuration
APP_DOMAIN=localhost
UNKNOWN_DOMAIN_ACTION=central

# 🏠 Tenant Database Configuration
TENANT_DB_PREFIX=tenant_
TENANT_AUTO_MIGRATE=false
TENANT_AUTO_SEED=false

# ⚡ Performance & Caching
TENANT_CACHE_DRIVER=database
TENANT_CACHE_PREFIX=tenant_
TENANT_CACHE_TTL=3600

# � Redis Configuration (Optional)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
TENANT_REDIS_ENABLED=true

# �📊 Monitoring (Optional)
TENANT_MONITORING_ENABLED=true
TENANT_MONITORING_RETENTION_DAYS=30
```

### ✅ **Step 3: Update Routes with Universal Middleware**

**This is the key change that solves tenant identification issues:**

```php
// ❌ OLD - Don't use this anymore:
Route::group(['middleware' => 'web'], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// ✅ NEW - Use Universal Middleware (works for BOTH central and tenant):
Route::group(['middleware' => 'universal.web'], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/settings', [SettingsController::class, 'index']);
});

// 🏢 For CENTRAL-ONLY routes (admin, management):
Route::group(['middleware' => 'central.web'], function () {
    Route::get('/admin', [AdminController::class, 'index']);
    Route::get('/tenants', [TenantsController::class, 'index']);
    Route::get('/system-settings', [SystemController::class, 'settings']);
});

// 🏠 For TENANT-ONLY routes (if needed):
Route::group(['middleware' => 'tenant.web'], function () {
    Route::get('/tenant-specific-feature', [TenantController::class, 'feature']);
});

// 🔌 For API routes:
Route::group(['middleware' => 'universal.api'], function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('posts', PostController::class);
});
```

```bash
php artisan tenant:create
```

**Follow the interactive wizard:**
1. Enter tenant name and domain
2. Choose creation mode (localhost/FastPanel)
3. Configure database settings
4. Run migrations and seeders

### ✅ **Step 4: Verify Everything Works**

```bash
# Check tenant was created
php artisan tenant:list

# Test database connections
php artisan tenancy:test-connections

# Verify FastPanel integration (if used)
/usr/local/fastpanel2/fastpanel databases list
```

---

## 🔧 Configuration Details

### Database Root Credentials

**Why needed?**
- Creates tenant databases automatically
- Grants MySQL privileges to database users
- Ensures phpMyAdmin/FastPanel auto-login access
- Enables seamless FastPanel integration

**Security:**
- Credentials used only during tenant creation
- Temporary connection switching for privilege operations
- All tenant credentials encrypted when stored

### Database Naming

- **Auto-generated**: `tenant_1a2b3c4d5e6f7g8h` (UUID-based)
- **Custom names**: `mycompany` → `tenant_mycompany` (prefix applied automatically)
- **Prefix configuration**: Controlled by `TENANT_DB_PREFIX` environment variable

### FastPanel Integration

**Requirements:**
- FastPanel CLI available at `/usr/local/fastpanel2/fastpanel`
- MySQL root credentials in environment
- User access to FastPanel management

**Features:**
- Automatic database creation via FastPanel
- Database user assignment and privilege management
- Panel owner assignment and site linking
- phpMyAdmin auto-login compatibility

---

## 🧪 Testing & Validation

### Basic Health Check
```bash
php artisan tenancy:validate
```

### Database Connection Testing
```bash
php artisan tenancy:test-connections
```

### Performance Testing
```bash
php artisan tenancy:test-performance-enhanced --concurrent-users=5
```

### Create Test Tenants
```bash
php artisan tenancy:create-test-tenants
```

---

## 🛠️ Troubleshooting

### Common Issues

**1. "Access denied for user" during tenant creation**
```
Solution: Add DB_ROOT_USERNAME and DB_ROOT_PASSWORD to .env
Alternative: GRANT CREATE ON *.* TO 'your_app_user'@'localhost';
```

**2. "Custom database name without prefix"**
```
Solution: System automatically applies TENANT_DB_PREFIX
Example: Input 'mydb' becomes 'tenant_mydb'
```

**3. "Database not visible in phpMyAdmin"**
```
Solution: Check if privileges were granted
Verify: SHOW GRANTS FOR 'username'@'localhost';
```

**4. "FastPanel CLI not found"**
```
Solution: Ensure FastPanel installed at /usr/local/fastpanel2/fastpanel
Check: ls -la /usr/local/fastpanel2/fastpanel
```

### Debug Commands

```bash
# View detailed logs
tail -f storage/logs/laravel.log

# Test specific tenant connection
php artisan tenant:db your-tenant-domain.com

# Validate tenant isolation
php artisan tenancy:test-isolation --tenants=3
```

---

## 📚 Quick Reference

### Essential Commands

| Command | Purpose |
|---------|---------|
| `tenant:create` | Create new tenant with wizard |
| `tenant:list` | List all tenants |
| `tenant:manage` | Interactive tenant management |
| `tenancy:validate` | System health check |
| `tenancy:test-connections` | Test all database connections |

### Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_ROOT_USERNAME` | - | MySQL root user (required) |
| `DB_ROOT_PASSWORD` | - | MySQL root password (required) |
| `TENANT_DB_PREFIX` | `tenant_` | Database name prefix |
| `TENANT_HOMEPAGE_ENABLED` | `1` | Enable tenant homepages |
| `TENANT_CACHE_DRIVER` | `database` | Cache driver for tenants |

---

## ✅ Success Indicators

After setup, you should see:

- ✅ `php artisan tenancy:validate` passes all checks
- ✅ Tenant databases created with proper prefixes
- ✅ MySQL privileges granted to database users
- ✅ FastPanel databases visible and accessible
- ✅ phpMyAdmin auto-login shows tenant databases
- ✅ Migrations and seeders run successfully

---

**🎉 You're all set! Your multi-tenant application is ready for production.**

For advanced configuration and API usage, see the main [README.md](README.md) documentation.

---

**Support**: Check logs, verify environment variables, test with debug mode enabled  
**Version**: Compatible with Laravel 10.x, 11.x | PHP 8.1+  
**Last Updated**: August 21, 2025
