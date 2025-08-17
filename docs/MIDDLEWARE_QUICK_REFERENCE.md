# 🛡️ Middleware Reference Guide

**Complete middleware guide for ArtFlow Studio Tenancy Package**

## Quick Reference

### ✅ For Central Domain Routes (localhost, admin.yoursite.com)
```php
Route::middleware(['central.web'])->group(function () {
    // Your admin/management routes here
    // Available on central domains ONLY
});
```

### ✅ For Tenant Domain Routes (tenant1.yoursite.com, tenant2.yoursite.com)  
```php
Route::middleware(['tenant.web'])->group(function () {
    // Your tenant application routes here
    // Available on tenant domains ONLY
    // Sessions properly scoped per tenant
});
```

### ✅ For Tenant API Routes
```php
Route::middleware(['tenant.api'])->group(function () {
    // Your tenant API endpoints here
    // No sessions, API-only
});
```

## Understanding Central vs Tenant Domains

**This is BY DESIGN in stancl/tenancy!** 

- **Central domains** (like `localhost`, `admin.yoursite.com`) are for admin/management
- **Tenant domains** (like `tenant1.yoursite.com`, `tenant2.yoursite.com`) are for tenant applications
- **They should NOT share the same login page** - they serve different purposes

## Available Middleware Groups

| Middleware Group | Purpose | Available On | Session Scoping |
|------------------|---------|--------------|-----------------|
| `central.web` | Central domain web routes | Central only | Standard Laravel |
| `tenant.web` | Tenant domain web routes | Tenant only | Per-tenant scoped |
| `tenant.api` | Tenant domain API routes | Tenant only | No sessions |

## ❌ Common Mistakes

### DON'T mix middleware:
```php
// ❌ WRONG - Don't mix central and tenant
Route::middleware(['central.web', 'tenant.web']) 

// ❌ WRONG - Don't use on same route group  
Route::middleware(['central.web'])->group(function () {
    Route::middleware(['tenant.web'])->get('/mixed'); // This breaks everything
});
```

**Central Login** (for admins):
```php
// routes/web.php - accessible on admin.yoursite.com
Route::middleware(['central.web'])->group(function () {
    Route::get('admin/login', [AdminAuthController::class, 'showLoginForm']);
    Route::post('admin/login', [AdminAuthController::class, 'login']);
});
```

**Tenant Login** (for tenant users):
```php
// routes/tenant.php - accessible on tenant1.yoursite.com, tenant2.yoursite.com  
Route::middleware(['tenant.web'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'showLoginForm']); 
    Route::post('login', [TenantAuthController::class, 'login']);
});
```

### Pattern 2: Different Paths on Same Controller

```php
// Central domain routes
Route::middleware(['central.web'])->group(function () {
    Route::get('admin/login', [AuthController::class, 'showAdminLogin']);
    Route::post('admin/login', [AuthController::class, 'adminLogin']);
});

// Tenant domain routes  
Route::middleware(['tenant.web'])->group(function () {
    Route::get('login', [AuthController::class, 'showTenantLogin']);
    Route::post('login', [AuthController::class, 'tenantLogin']);
});
```

## Livewire Setup

### ✅ Correct Livewire Setup
```php
// routes/tenant.php
Route::middleware(['tenant.web'])->group(function () {
    // All Livewire components here get proper session scoping
    Route::get('profile', ProfileComponent::class);
    Route::get('settings', SettingsComponent::class);
});
```

### ❌ Wrong Livewire Setup
```php
// ❌ Don't put Livewire components on central.web if they need tenant data
Route::middleware(['central.web'])->group(function () {
    Route::get('tenant-profile', ProfileComponent::class); // Won't have tenant context
});
```

## Route File Organization

```
routes/
├── web.php          # Central domain routes with ['central.web']
├── api.php          # Central domain API with ['central.web'] 
├── tenant.php       # Tenant domain routes with ['tenant.web'] (stancl/tenancy convention)
└── af-tenancy.php   # Package routes (already configured)
```

## Testing Your Setup

### Test Central Domain Routes:
```bash
# Visit on localhost or your central domain
curl http://localhost/admin/login        # Should work
curl http://tenant1.localhost/admin/login # Should be blocked
```

### Test Tenant Domain Routes:
```bash 
# Visit on tenant subdomain
curl http://tenant1.localhost/login      # Should work
curl http://localhost/login              # Should be blocked (or 404)
```

## Common Error Messages and Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| "Route not found" | Wrong middleware for domain type | Use `central.web` for central, `tenant.web` for tenant |
| "Session data mixing between tenants" | Not using `tenant.web` | Always use `tenant.web` for tenant routes |
| "Livewire component errors" | Session scoping issues | Use `tenant.web` middleware group |
| "Call to undefined method tenant()" | No tenant context | Use `tenant.web` or check if on tenant domain |
| "Access denied" | Domain restrictions | Check `config('tenancy.central_domains')` |

## Domain Configuration

Make sure your `.env` has:
```env
# Central domains (no tenancy)
TENANCY_CENTRAL_DOMAINS="localhost,127.0.0.1,admin.yoursite.com"

# Your main app domain
APP_DOMAIN="yoursite.com"
```

And your `config/tenancy.php`:
```php
'central_domains' => [
    'localhost',
    '127.0.0.1', 
    'admin.yoursite.com',
    // Add your central domains here
],
```
