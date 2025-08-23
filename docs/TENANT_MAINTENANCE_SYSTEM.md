# Tenant Maintenance System - Complete Guide

## Overview

The AF-MultiTenancy package includes a comprehensive tenant maintenance system built on top of Stancl/Tenancy. This system allows you to put individual tenants into maintenance mode while keeping other tenants operational.

## Storage Architecture

### Data Storage Method
- **Storage Location**: `tenants.data` JSON column (Stancl/Tenancy standard)
- **No Dedicated Column**: Maintenance data is stored in the flexible JSON `data` column
- **Cache Layer**: Redis/file cache for performance optimization
- **Key Structure**: `tenant_maintenance_{tenant_id}` for caching

### Data Structure
```json
{
  "maintenance": {
    "enabled": true,
    "message": "We're performing maintenance. Please check back soon!",
    "allowed_ips": ["192.168.1.100", "10.0.0.0/8"],
    "bypass_key": "secret-key-123",
    "enabled_at": "2024-01-15T10:30:00Z",
    "enabled_by": "admin"
  }
}
```

## Components

### 1. TenantMaintenanceMode Service (`src/Services/TenantMaintenanceMode.php`)

Core service that handles all maintenance mode logic:

**Key Methods:**
- `enableMaintenanceMode(Tenant $tenant, array $options = [])`
- `disableMaintenanceMode(Tenant $tenant)`
- `isInMaintenanceMode(Tenant $tenant): bool`
- `getMaintenanceData(Tenant $tenant): ?array`
- `isRequestAllowed(Request $request, Tenant $tenant): bool`

**Features:**
- JSON data persistence in `tenants.data` column
- Cache layer for performance (configurable TTL)
- IP whitelisting support
- Bypass key mechanism
- Custom maintenance messages
- Audit trail (who enabled, when)

### 2. TenantMaintenanceModeCommand (`src/Commands/Maintenance/TenantMaintenanceModeCommand.php`)

CLI interface for maintenance mode management:

**Available Commands:**
```bash
# Enable maintenance mode for specific tenant
php artisan tenants:maintenance enable tenant-slug

# Enable with custom message
php artisan tenants:maintenance enable tenant-slug --message="Custom maintenance message"

# Enable with IP whitelist
php artisan tenants:maintenance enable tenant-slug --allowed-ips="192.168.1.100,10.0.0.0/8"

# Enable with bypass key
php artisan tenants:maintenance enable tenant-slug --bypass-key="secret-123"

# Disable maintenance mode
php artisan tenants:maintenance disable tenant-slug

# Check maintenance status
php artisan tenants:maintenance status tenant-slug

# List all tenants in maintenance mode
php artisan tenants:maintenance list

# Bulk operations
php artisan tenants:maintenance enable-all
php artisan tenants:maintenance disable-all

# Clear maintenance cache
php artisan tenants:maintenance clear-cache
```

**Command Features:**
- Individual tenant control
- Bulk operations for all tenants
- Status checking and listing
- Cache management
- Comprehensive error handling
- Progress indicators for bulk operations

### 3. TenantMaintenanceMiddleware (`src/Http/Middleware/TenantMaintenanceMiddleware.php`)

HTTP middleware that enforces maintenance mode:

**Functionality:**
- Intercepts all HTTP requests for tenants in maintenance
- Checks IP whitelist and bypass keys
- Returns maintenance page or JSON response
- Preserves admin/API access when configured
- Logs maintenance mode hits

**Usage in Routes:**
```php
// Apply to specific routes
Route::middleware(['tenant', 'tenant.maintenance'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
});

// Or in route groups
Route::group([
    'middleware' => ['tenant', 'tenant.maintenance']
], function () {
    // Your tenant routes
});
```

### 4. Maintenance Blade View (`resources/views/maintenance.blade.php`)

Beautiful maintenance page with:
- Responsive design
- Auto-refresh functionality
- Custom message display
- Professional styling
- SEO-friendly structure
- Mobile optimization

## Configuration

### Environment Variables
```env
# Maintenance Mode Cache
TENANCY_MAINTENANCE_CACHE_TTL=3600

# Default maintenance message
TENANCY_MAINTENANCE_DEFAULT_MESSAGE="We're performing maintenance. Please check back soon!"

# Auto-refresh interval (seconds)
TENANCY_MAINTENANCE_REFRESH_INTERVAL=30
```

### Config File (`config/tenancy.php`)
```php
'maintenance' => [
    'cache_ttl' => env('TENANCY_MAINTENANCE_CACHE_TTL', 3600),
    'default_message' => env('TENANCY_MAINTENANCE_DEFAULT_MESSAGE', 'We\'re performing maintenance. Please check back soon!'),
    'refresh_interval' => env('TENANCY_MAINTENANCE_REFRESH_INTERVAL', 30),
    'allowed_ips' => [],
    'bypass_header' => 'X-Maintenance-Bypass',
    'bypass_cookie' => 'maintenance_bypass',
],
```

## Integration Guide

### 1. Middleware Registration

The middleware is automatically registered in `TenancyServiceProvider.php`:

```php
// Tenant maintenance middleware
$router->aliasMiddleware('tenant.maintenance', Http\Middleware\TenantMaintenanceMiddleware::class);
```

### 2. Route Protection

Apply maintenance middleware to your tenant routes:

```php
// In your routes/web.php or tenant route files
Route::middleware(['tenant', 'tenant.maintenance'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/about', [AboutController::class, 'index']);
    // ... other routes
});
```

### 3. API Routes

For API routes, the middleware returns JSON responses:

```php
// In your routes/api.php
Route::middleware(['tenant', 'tenant.maintenance'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

JSON Response Format:
```json
{
  "message": "This tenant is currently in maintenance mode",
  "maintenance": true,
  "retry_after": 30
}
```

### 4. Administrative Bypass

To allow admin access during maintenance:

```php
// Option 1: IP Whitelist
php artisan tenants:maintenance enable tenant-slug --allowed-ips="192.168.1.100"

// Option 2: Bypass Key
php artisan tenants:maintenance enable tenant-slug --bypass-key="admin-access-123"

// Use bypass key in requests
// Header: X-Maintenance-Bypass: admin-access-123
// Or Cookie: maintenance_bypass=admin-access-123
```

## Usage Examples

### Basic Maintenance Mode

```bash
# Put tenant in maintenance
php artisan tenants:maintenance enable my-tenant

# Check status
php artisan tenants:maintenance status my-tenant

# Disable maintenance
php artisan tenants:maintenance disable my-tenant
```

### Advanced Maintenance Mode

```bash
# Enable with custom message and IP whitelist
php artisan tenants:maintenance enable my-tenant \
  --message="Upgrading database. Back in 30 minutes!" \
  --allowed-ips="192.168.1.0/24,10.0.0.1" \
  --bypass-key="admin-2024"
```

### Bulk Operations

```bash
# Put all tenants in maintenance
php artisan tenants:maintenance enable-all --message="System-wide maintenance"

# List all tenants in maintenance
php artisan tenants:maintenance list

# Disable maintenance for all tenants
php artisan tenants:maintenance disable-all
```

### Programmatic Usage

```php
use ArtflowStudio\Tenancy\Services\TenantMaintenanceMode;
use ArtflowStudio\Tenancy\Models\Tenant;

$maintenanceService = app(TenantMaintenanceMode::class);
$tenant = Tenant::find('my-tenant');

// Enable maintenance mode
$maintenanceService->enableMaintenanceMode($tenant, [
    'message' => 'Custom maintenance message',
    'allowed_ips' => ['192.168.1.100'],
    'bypass_key' => 'secret-key'
]);

// Check if in maintenance
if ($maintenanceService->isInMaintenanceMode($tenant)) {
    // Handle maintenance mode
}

// Disable maintenance mode
$maintenanceService->disableMaintenanceMode($tenant);
```

## FastPanel Integration

The maintenance system works seamlessly with FastPanel deployments:

### Deployment Verification

```bash
# Verify FastPanel deployment and maintenance system
php artisan fastpanel:verify-deployment --detailed

# Test maintenance mode after deployment
php artisan tenants:maintenance enable test-tenant
php artisan fastpanel:verify-deployment
```

### Production Considerations

1. **Cache Configuration**: Ensure Redis is properly configured for production
2. **IP Whitelisting**: Configure office/admin IP ranges
3. **Bypass Keys**: Use strong, rotating bypass keys
4. **Monitoring**: Monitor maintenance mode usage and duration

## Troubleshooting

### Common Issues

1. **Middleware Not Applied**
   - Ensure middleware is registered in service provider
   - Check route definitions include maintenance middleware

2. **Cache Issues**
   - Clear maintenance cache: `php artisan tenants:maintenance clear-cache`
   - Verify Redis/cache configuration

3. **IP Whitelist Not Working**
   - Check IP format (use CIDR notation for ranges)
   - Verify request IP detection (proxy considerations)

4. **Bypass Key Not Working**
   - Ensure key is passed in header or cookie
   - Check for URL encoding issues

### Debug Commands

```bash
# Check tenant maintenance status
php artisan tenants:maintenance status tenant-slug

# List all maintenance data
php artisan tenants:maintenance list --detailed

# Clear cache and test
php artisan tenants:maintenance clear-cache
php artisan tenants:maintenance status tenant-slug
```

## Performance Considerations

### Cache Strategy
- Maintenance data is cached for quick access
- Cache TTL configurable via environment
- Automatic cache invalidation on status changes

### Database Impact
- Uses existing `tenants.data` JSON column
- No additional database schema required
- Efficient JSON queries with indexes

### Scalability
- Redis cache for high-traffic scenarios
- Bulk operations optimized for large tenant counts
- Minimal performance impact when not in maintenance

## Security Features

### IP Whitelisting
- Support for individual IPs and CIDR ranges
- Proxy-aware IP detection
- Multiple IP support

### Bypass Mechanisms
- Secure bypass keys
- Header and cookie support
- Time-limited access (optional)

### Audit Trail
- Track who enabled maintenance mode
- Record enable/disable timestamps
- Optional logging integration

## Future Enhancements

Planned improvements for the maintenance system:

1. **Scheduled Maintenance**: Ability to schedule maintenance windows
2. **Tenant Notifications**: Email/SMS notifications to tenant users
3. **Custom Templates**: Tenant-specific maintenance page templates
4. **API Webhooks**: Webhook notifications for maintenance events
5. **Dashboard Integration**: Web interface for maintenance management
6. **Maintenance Logs**: Detailed logging and reporting
7. **Rolling Maintenance**: Gradual rollout across tenant groups

## Support

For issues or questions about the tenant maintenance system:

1. Check this documentation
2. Review configuration settings
3. Test with debug commands
4. Check application logs
5. Contact support with specific error details

The tenant maintenance system provides a robust, scalable solution for managing per-tenant maintenance modes in multi-tenant applications, ensuring minimal disruption to your users while allowing necessary maintenance operations.
