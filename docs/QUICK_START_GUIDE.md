# 🚀 Artflow Tenancy - Quick Start Guide

## Overview

Artflow Tenancy is an **enterprise-grade multi-tenancy extension** built on top of `stancl/tenancy`. It provides:

- ✅ **Automated Tenant Initialization** - No more manual tenancy checks in controllers
- ✅ **Dynamic Session Isolation** - Each tenant gets isolated database sessions
- ✅ **Dynamic Cache Isolation** - Each tenant gets isolated database cache
- ✅ **Livewire v3 Compatible** - Full support for Livewire 3 event system
- ✅ **Zero Configuration Required** - Works out of the box with sensible defaults

## Installation

### Step 1: Install Package

```bash
composer require artflow-studio/tenancy
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider"
```

This creates:
- `config/tenancy.php` - Main configuration
- `config/artflow-tenancy.php` - Artflow-specific settings

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates the required tables:
- `tenants` - Stores tenant information
- `domains` - Stores tenant domains
- `sessions` - Tenant-specific sessions (if using database driver)
- `cache` - Tenant-specific cache (if using database driver)

### Step 4: Configure Environment

Add to your `.env` file:

```env
# Database Root Credentials (for automatic tenant database creation)
DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=your_password

# Central Domain
APP_DOMAIN=localhost

# Tenant Configuration
TENANT_DB_PREFIX=tenant_
TENANT_AUTO_MIGRATE=true
TENANT_AUTO_SEED=false

# Session & Cache
TENANT_CACHE_DRIVER=database
SESSION_DRIVER=database
```

## Core Components

### 1. Session Isolation (`SessionTenancyBootstrapper`)

**What it does:**
- Automatically switches database connection for sessions
- Each tenant stores sessions in their own database
- Central app sessions remain isolated

**Configuration:**

```php
// config/artflow-tenancy.php
'session' => [
    'table' => 'sessions',  // Table name in tenant database
    'connection' => 'tenant', // Uses tenant database connection
],
```

**How it works:**
```php
// During request:
// 1. Tenant identified via middleware
// 2. SessionTenancyBootstrapper::bootstrap() called
// 3. config('session.connection') → 'tenant'
// 4. config('session.table') → 'sessions'
// 5. All session reads/writes hit tenant database
```

### 2. Cache Isolation (`EnhancedCacheTenancyBootstrapper`)

**What it does:**
- Automatically switches database connection for cache
- Each tenant stores cache in their own database
- Supports multiple isolation modes

**Configuration:**

```php
// config/artflow-tenancy.php
'cache' => [
    'isolation_mode' => 'database', // 'database', 'prefix', or 'tags'
    'table' => 'cache',
    'connection' => 'tenant',
    'prefix_pattern' => 'tenant_{tenant_id}_',
],
```

**Isolation Modes:**

1. **database** - Separate table in tenant database
   ```
   Central DB: cache table
   Tenant 1 DB: cache table (isolated)
   Tenant 2 DB: cache table (isolated)
   ```

2. **prefix** - Same table, different prefixes
   ```
   cache table with key: tenant_1_user_profile
   cache table with key: tenant_2_user_profile
   ```

3. **tags** - Redis tags (requires Redis)
   ```
   Redis with tag: tenant:1:user_profile
   Redis with tag: tenant:2:user_profile
   ```

### 3. Livewire Integration

**What it does:**
- Automatically initializes tenancy for Livewire component calls
- Uses Livewire v3's `mount` event
- No manual tenancy initialization needed

**How it works:**

```php
// In TenancyServiceProvider.php
\Livewire\on('mount', function ($component, $params, $key, $parent) {
    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
});
```

This means:
- Every Livewire component mount automatically initializes tenancy
- No need for manual checks in component methods
- Works seamlessly with AJAX requests to `_livewire/update` endpoint

## Usage Examples

### Example 1: Remove Manual Tenancy Checks

**Before (❌ Don't do this):**
```php
class Login extends Component
{
    public function login()
    {
        // Manual tenancy initialization (REMOVED)
        if (!tenancy()->initialized) {
            $domain = request()->getHost();
            $tenant = Tenant::whereHas('domains', 
                fn($q) => $q->where('domain', $domain)
            )->first();
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }
        
        // Authenticate...
    }
}
```

**After (✅ Do this):**
```php
class Login extends Component
{
    public function mount()
    {
        // Prevent logged-in users from accessing login
        if (Auth::check()) {
            // Redirect based on role...
        }
    }
    
    public function login()
    {
        // Tenancy is already initialized via middleware
        // Just authenticate directly
        Auth::attempt([...]);
    }
}
```

### Example 2: Query Across Tenants

```php
// Get user's own data (from current tenant)
$user = User::find(1); // Uses tenant database

// Get central user data
$centralUser = app(CreateUserAction::class)->getTenantModel(null, 1); // Uses central database

// Both work seamlessly with routing!
```

### Example 3: Cache in Tenant Context

```php
// Cache is automatically isolated per tenant
cache()->put('user_profile', $user, 3600);

// In another request to same tenant: retrieved from tenant cache
// In request to different tenant: NOT found (different cache table/key)

// In central app: cached in central database
```

### Example 4: Sessions in Tenant Context

```php
// Session is automatically isolated per tenant
session()->put('logged_in_user', $user->id);

// Automatically stored in tenant's sessions table
// Not accessible from other tenants
// Central app sessions remain separate
```

## Middleware System

### Universal Middleware (Recommended)

Works for **both central and tenant** requests:

```php
Route::middleware('universal.web')->group(function () {
    Route::get('/dashboard', 'DashboardController@index');
});
```

### Tenant-Only Middleware

```php
Route::middleware('tenant.web')->group(function () {
    Route::get('/tenant-feature', 'TenantController@feature');
});
```

### Central-Only Middleware

```php
Route::middleware('central.web')->group(function () {
    Route::get('/admin', 'AdminController@index');
});
```

## TenantAware Trait

Use this trait on models that exist in both central and tenant databases:

```php
class User extends Model
{
    use ArtflowStudio\Tenancy\Traits\TenantAware;
}

// Automatically routes queries to current tenant's database
$user = User::find(1); // From current tenant
```

## Bootstrappers (Internal)

Bootstrappers automatically run when tenancy is initialized:

| Bootstrapper | Purpose |
|---|---|
| `SessionTenancyBootstrapper` | Isolates sessions to tenant database |
| `EnhancedCacheTenancyBootstrapper` | Isolates cache to tenant database or prefix |
| `HorizonTenancyBootstrapper` | Configures Horizon job queues per tenant |
| `SafeRedisTenancyBootstrapper` | Isolates Redis keys per tenant |

## Configuration Files

### `config/artflow-tenancy.php`

```php
return [
    'session' => [
        'table' => 'sessions',
        'connection' => 'tenant',
    ],
    'cache' => [
        'isolation_mode' => 'database',
        'table' => 'cache',
        'connection' => 'tenant',
        'prefix_pattern' => 'tenant_{tenant_id}_',
    ],
    'queue' => [
        'connection' => 'tenant',
    ],
];
```

### `config/tenancy.php` (stancl/tenancy)

Core tenancy configuration - see original package docs.

## Troubleshooting

### "Tenant could not be identified" in Tests

**Solution:** Mock the tenant initialization:

```php
public function setUp(): void
{
    parent::setUp();
    
    // Create a test tenant
    $tenant = Tenant::create(['name' => 'Test Tenant']);
    $tenant->domains()->create(['domain' => 'localhost']);
    
    // Initialize tenancy for tests
    tenancy()->initialize($tenant);
}
```

### Sessions Not Being Stored

**Check:**
1. Is `SESSION_DRIVER=database` in `.env`?
2. Are migrations run? (`php artisan migrate`)
3. Is `session.connection` pointing to `'tenant'` in config?

### Cache Not Isolating

**Check:**
1. Is `TENANT_CACHE_DRIVER=database` in `.env`?
2. Is `cache.default` set to `'database'`?
3. Is `artflow-tenancy.cache.isolation_mode` set correctly?

## Next Steps

1. **Read** full documentation in `docs/` directory
2. **Check** example implementations in `examples/`
3. **Review** API reference in `docs/api/`
4. **Test** with provided test suite: `php artisan test`

## Support

- 📚 Full documentation: See `docs/` directory
- 🐛 Issues: Create GitHub issue with reproduction steps
- 💬 Questions: Check existing docs/issues first

---

**Happy Multi-Tenancy Development! 🎉**
