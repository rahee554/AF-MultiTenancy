# ArtflowStudio Multi-Tenancy Installation Guide

## Prerequisites

- Laravel 9.x or 10.x
- PHP 8.1+
- MySQL/PostgreSQL database
- Redis (optional, but recommended for caching)

## Step 1: Install Dependencies

First, install the package via Composer:

```bash
composer require artflowstudio/multi-tenancy
```

The package will automatically install `stancl/tenancy` as a dependency.

## Step 2: Run Installation Command

Run the comprehensive installation command:

```bash
php artisan tenancy:install
```

This command will:
- Publish configuration files
- Set up stancl/tenancy integration
- Create necessary directory structure
- Configure middleware groups
- Optionally create a sample tenant

For minimal installation (no sample tenant):
```bash
php artisan tenancy:install --minimal
```

## Step 3: Configure Your Application

### Update Environment Variables

Add these variables to your `.env` file:

```env
# Central domain configuration
CENTRAL_DOMAINS=admin.yourapp.com,app.yourapp.com,localhost

# Database configuration for tenancy
TENANCY_DATABASE_AUTO_DELETE_TENANT_DATABASE=true
TENANCY_CACHE_TTL=300

# Redis configuration (if using Redis)
REDIS_TENANT_PREFIX=tenant_
REDIS_CENTRAL_PREFIX=central_
```

### Configure Domains

Edit `config/artflow-tenancy.php`:

```php
'central_domains' => [
    'admin.yourapp.com',
    'app.yourapp.com', 
    'localhost',
    '127.0.0.1',
    // Wildcard patterns supported
    '*.admin.yourapp.com',
],
```

## Step 4: Update Routes

### Central Routes (routes/web.php)
```php
// Central domain routes - admin interface, main website
Route::middleware(['central.web'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
    
    Route::get('/admin', [AdminController::class, 'index']);
});
```

### Tenant Routes (routes/tenant.php)
Create `routes/tenant.php`:

```php
<?php

// Tenant-specific routes
Route::middleware(['tenant.web'])->group(function () {
    Route::get('/', [TenantHomeController::class, 'index']);
    Route::get('/dashboard', [TenantDashboardController::class, 'index']);
});

// Tenant authentication routes with enhanced logging
Route::middleware(['tenant.auth.web'])->group(function () {
    Auth::routes();
});
```

### Smart Routes (for both central and tenant)
For routes that should work on both central and tenant domains:

```php
// Routes that intelligently work on both central and tenant domains
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/status', [StatusController::class, 'index']);
});
```

## Step 5: Update Service Provider Registration

If auto-discovery is disabled, add to `config/app.php`:

```php
'providers' => [
    // Other providers...
    ArtflowStudio\Tenancy\TenancyServiceProvider::class,
],
```

## Step 6: Run Migrations

Run migrations for both central and tenant databases:

```bash
# Central database migrations
php artisan migrate

# Tenant database migrations  
php artisan tenants:migrate
```

## Step 7: Create Your First Tenant

```bash
php artisan tenant:create tenant1.yourapp.com --name="First Tenant"
```

Or create programmatically:

```php
use Stancl\Tenancy\Database\Models\Tenant;

$tenant = Tenant::create([
    'id' => 'tenant1',
    'data' => [
        'name' => 'First Tenant',
        'email' => 'admin@tenant1.com'
    ]
]);

$tenant->domains()->create(['domain' => 'tenant1.yourapp.com']);
```

## Step 8: Test Installation

Run comprehensive tests:

```bash
php artisan tenancy:test:comprehensive
```

Run quick installation test:
```bash
php artisan tenancy:test:quick-install
```

## Middleware Groups Reference

| Middleware Group | Use Case | Features |
|------------------|----------|----------|
| `central.web` | Central domain routes | Web middleware, central domain validation |
| `tenant.web` | Tenant application routes | Full tenant context, session scoping, Livewire support |
| `tenant.api` | Tenant API routes | Tenant context without sessions |
| `tenant.auth.web` | Tenant auth routes | Enhanced logging, tenant context |
| `central.tenant.web` | Smart shared routes | Automatic domain detection, works on both |

## Advanced Configuration

### Redis Multi-Database Setup

Enable per-tenant Redis databases in `config/artflow-tenancy.php`:

```php
'redis' => [
    'per_tenant_database' => true,
    'database_offset' => 10, // Central uses 0-9, tenants start at 10
    'prefix_pattern' => 'tenant_{tenant_id}_',
    'central_prefix' => 'central_',
],
```

### Custom Tenant Model

Create custom tenant model:

```php
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    protected $fillable = [
        'id', 'data', 'name', 'email', 'plan'
    ];
    
    // Custom accessor for plan
    public function getPlanAttribute()
    {
        return $this->data['plan'] ?? 'basic';
    }
}
```

Update `config/tenancy.php`:
```php
'tenant_model' => App\Models\Tenant::class,
```

## Troubleshooting

### Common Issues

1. **"Tenant identification failed"**
   - Check domain configuration in `config/artflow-tenancy.php`
   - Verify tenant exists: `php artisan tenant:list`
   - Use smart domain middleware: `central.tenant.web`

2. **Session issues with Livewire**
   - Ensure `tenant.scope-sessions` middleware is applied
   - Check Livewire is configured for tenancy

3. **Database connection errors**
   - Verify tenant databases exist: `php artisan tenants:migrate`
   - Check database permissions

### Debug Commands

```bash
# List all tenants
php artisan tenant:list

# Check tenant health
php artisan tenancy:health-check

# Test system performance
php artisan tenancy:test:performance

# Run comprehensive diagnostics
php artisan tenancy:test:comprehensive --verbose
```

## Next Steps

1. [Configure your tenant model](./tenant-model.md)
2. [Set up tenant-specific storage](./storage.md)
3. [Configure queues for multi-tenancy](./queues.md)
4. [Implement tenant-aware notifications](./notifications.md)

## Support

- Documentation: [GitHub Repository](https://github.com/artflowstudio/multi-tenancy)
- Issues: [GitHub Issues](https://github.com/artflowstudio/multi-tenancy/issues)
- Discussions: [GitHub Discussions](https://github.com/artflowstudio/multi-tenancy/discussions)
