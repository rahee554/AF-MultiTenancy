# Middleware Quick Reference

## Available Middleware

### 1. UniversalWebMiddleware
**Class**: `ArtflowStudio\Tenancy\Http\Middleware\UniversalWebMiddleware`  
**Alias**: `universal.web`

**Purpose**: Universal middleware that works for both central and tenant domains with graceful fallback.

**Features**:
- Automatic tenant context detection
- Graceful fallback to central domain if tenant resolution fails
- Works seamlessly across all domain types
- Error handling and logging
- Compatible with both tenant and central routes

**Usage**:
```php
// In routes/web.php - works everywhere
Route::middleware(['universal.web'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### 2. TenantMaintenanceMiddleware
**Class**: `ArtflowStudio\Tenancy\Http\Middleware\TenantMaintenanceMiddleware`  
**Alias**: `tenant.maintenance`

**Purpose**: Intercepts requests to tenants in maintenance mode and shows maintenance page.

**Features**:
- Checks if tenant is in maintenance mode
- IP whitelisting support
- Bypass key mechanism
- Custom maintenance pages
- Automatic 503 responses with Retry-After headers

**Usage**:
```php
// Add to tenant routes that should respect maintenance mode
Route::middleware(['tenant', 'tenant.maintenance'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Or combine with universal middleware
Route::middleware(['universal.web', 'tenant.maintenance'])->group(function () {
    // Routes that work everywhere but respect maintenance mode
});
```

### 3. Standard Stancl/Tenancy Middleware

#### InitializeTenancyByDomain
**Alias**: `tenant`  
**Purpose**: Initialize tenant context based on domain

#### PreventAccessFromCentralDomains  
**Alias**: `tenant.prevent-central`  
**Purpose**: Block access from central domains to tenant routes

#### ScopeSessions
**Alias**: `tenant.scope-sessions`  
**Purpose**: Scope sessions to tenant context

## Middleware Usage Patterns

### Pattern 1: Universal Routes (Recommended)
```php
// Routes that work on both central and tenant domains
Route::middleware(['universal.web', 'tenant.maintenance'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/about', [AboutController::class, 'index']);
    Route::get('/contact', [ContactController::class, 'index']);
});
```

### Pattern 2: Tenant-Only Routes
```php
// Strict tenant-only routes
Route::middleware(['tenant', 'tenant.prevent-central', 'tenant.maintenance'])->group(function () {
    Route::get('/tenant-specific', [TenantController::class, 'index']);
    Route::resource('tenant-data', TenantDataController::class);
});
```

### Pattern 3: Central-Only Routes
```php
// Central domain only
Route::middleware(['web'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
    Route::get('/system-status', [SystemController::class, 'status']);
});
```

### Pattern 4: Admin Routes (Bypass Maintenance)
```php
// Admin routes that bypass maintenance mode
Route::middleware(['universal.web'])->prefix('admin')->group(function () {
    Route::get('/maintenance', [MaintenanceController::class, 'index']);
    Route::post('/maintenance/toggle', [MaintenanceController::class, 'toggle']);
});
```

## Maintenance Mode Configuration

### Environment Variables
```env
# Enable/disable maintenance mode globally
TENANCY_MAINTENANCE_MODE_ENABLED=true

# Default redirect URL for maintenance
TENANCY_MAINTENANCE_REDIRECT_URL=/maintenance

# Allowed IPs (comma-separated)
TENANCY_MAINTENANCE_ALLOWED_IPS=127.0.0.1,::1,192.168.1.100

# Default bypass key
TENANCY_MAINTENANCE_BYPASS_KEY=secret_admin_key

# Custom maintenance view
TENANCY_MAINTENANCE_VIEW=custom.maintenance
```

### Commands for Maintenance Management
```bash
# Enable maintenance for specific tenant
php artisan tenants:maintenance enable --tenant=uuid-here --message="Upgrading database"

# Enable for all tenants
php artisan tenants:maintenance enable --all --message="System maintenance"

# Disable maintenance
php artisan tenants:maintenance disable --tenant=uuid-here

# Check status
php artisan tenants:maintenance status --tenant=uuid-here

# List all tenants in maintenance
php artisan tenants:maintenance list
```

### Bypassing Maintenance Mode

#### Method 1: IP Whitelisting
Add your IP to allowed IPs in environment or when enabling maintenance:
```bash
php artisan tenants:maintenance enable --tenant=uuid --allowed-ips=127.0.0.1,192.168.1.100
```

#### Method 2: Bypass Key
Access with bypass key in URL:
```
https://tenant.example.com?bypass_key=your_secret_key
```

#### Method 3: Header-Based Bypass
Send bypass key in header:
```
X-Bypass-Key: your_secret_key
```

## Custom Maintenance Views

### Using Custom Blade Views
1. Create custom view: `resources/views/maintenance/custom.blade.php`
2. Set in config: `TENANCY_MAINTENANCE_VIEW=maintenance.custom`

### Available Variables in Maintenance Views
- `$message` - Custom maintenance message
- `$enabled_at` - When maintenance was enabled
- `$retry_after` - Estimated seconds until back online
- `$admin_contact` - Admin contact information
- `$bypass_key` - Bypass key (only in debug mode)
- `$allowed_ips` - Array of allowed IP addresses

### Example Custom View
```blade
@extends('layouts.app')

@section('content')
<div class="maintenance-page">
    <h1>🔧 Maintenance Mode</h1>
    <p>{{ $message }}</p>
    
    @if(isset($retry_after))
    <p>Expected back online: {{ \Carbon\Carbon::now()->addSeconds($retry_after)->format('g:i A') }}</p>
    @endif
    
    @if(isset($admin_contact))
    <p>Contact: {{ $admin_contact }}</p>
    @endif
</div>
@endsection
```

## Best Practices

1. **Always use maintenance middleware** on tenant routes
2. **Combine with universal middleware** for flexibility
3. **Set appropriate retry times** (1-4 hours typical)
4. **Provide admin contact** for urgent issues
5. **Use IP whitelisting** for admin access during maintenance
6. **Test maintenance mode** before deploying
7. **Log maintenance events** for audit trails
8. **Notify users in advance** when possible

## Troubleshooting

### Common Issues

1. **Maintenance middleware not working**
   - Check middleware is registered in service provider
   - Verify middleware is applied to routes
   - Ensure tenant context is initialized

2. **Can't bypass maintenance mode**
   - Check IP is in allowed list
   - Verify bypass key is correct
   - Check if maintenance is actually enabled

3. **Custom views not loading**
   - Verify view path in config
   - Check view file exists
   - Ensure view has required variables

### Debug Mode
Set `APP_DEBUG=true` to see bypass hints and additional debugging information.
