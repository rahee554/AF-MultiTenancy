# 🚀 Smart Domain Resolver - Quick Start

## The Problem Solved
- ❌ **Before**: Need separate `/login` routes for central and tenant domains
- ✅ **After**: One `/login` route works intelligently on both domain types

## Quick Usage

### 1. Use the Smart Middleware
```php
// routes/web.php
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
```

### 2. Smart Controller Logic
```php
class AuthController extends Controller
{
    public function showLoginForm(Request $request)
    {
        // Automatic context detection - no manual domain checking needed!
        if ($request->attributes->get('is_central')) {
            // Central domain: localhost/login
            return view('auth.admin-login');
        } else {
            // Tenant domain: tenant1.yoursite.com/login
            $tenant = $request->attributes->get('tenant');
            return view('auth.tenant-login', compact('tenant'));
        }
    }
}
```

### 3. Smart Views (Automatic Variables)
```blade
{{-- auth/login.blade.php --}}
@if($isCentral)
    <h1>Admin Login</h1>
@else
    <h1>{{ $currentTenant->name }} Login</h1>
@endif
```

## Available Context Variables

### In Controllers (Request Attributes)
```php
$request->attributes->get('domain_type');    // 'central' or 'tenant'
$request->attributes->get('is_central');     // true/false
$request->attributes->get('is_tenant');      // true/false  
$request->attributes->get('tenant');         // Tenant object or null
```

### In Views (Automatic)
```blade
{{ $domainType }}        {{-- 'central' or 'tenant' --}}
{{ $isCentral }}         {{-- true/false --}}
{{ $isTenant }}          {{-- true/false --}}
{{ $currentTenant }}     {{-- Tenant object or null --}}
```

## Domain Behavior

| Domain Type | Example | Context | Sessions |
|-------------|---------|---------|----------|
| Central | `localhost/login` | No tenant, admin area | Standard Laravel |
| Tenant | `tenant1.yoursite.com/login` | Tenant context active | Tenant-scoped |

## What Happens Automatically

### On Central Domain (localhost):
- ✅ No tenant initialization
- ✅ Standard Laravel sessions
- ✅ `$isCentral = true`, `$currentTenant = null`

### On Tenant Domain (tenant1.yoursite.com):
- ✅ Full tenant initialization
- ✅ Tenant-scoped sessions (Livewire safe)
- ✅ `$isTenant = true`, `$currentTenant = TenantObject`

## Common Patterns

### Pattern 1: Same View, Different Content
```php
// Controller
public function dashboard(Request $request)
{
    return view('dashboard'); // Same view for both
}
```
```blade
{{-- dashboard.blade.php --}}
@if($isCentral)
    <h1>Admin Dashboard</h1>
    <!-- Show system stats, tenant management -->
@else
    <h1>{{ $currentTenant->name }} Dashboard</h1>
    <!-- Show tenant-specific data -->
@endif
```

### Pattern 2: Same Controller, Different Logic
```php
public function store(Request $request)
{
    if ($request->attributes->get('is_central')) {
        // Create system-wide resource
        return $this->createSystemResource($request);
    } else {
        // Create tenant-scoped resource
        $tenant = $request->attributes->get('tenant');
        return $this->createTenantResource($request, $tenant);
    }
}
```

### Pattern 3: Livewire Components
```php
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('settings', SettingsComponent::class); // Works on both!
});
```

## Comparison with Other Middleware

| Middleware | Central Domains | Tenant Domains | Sessions | Use Case |
|------------|----------------|----------------|----------|----------|
| `central.web` | ✅ Only | ❌ Blocked | Standard | Admin/management only |
| `tenant.web` | ❌ Blocked | ✅ Only | Tenant-scoped | Tenant app only |
| `central.tenant.web` | ✅ Smart | ✅ Smart | Context-aware | **Shared routes** |

## Quick Migration

### Before (Separate Routes):
```php
// ❌ OLD: Two separate route definitions
Route::domain('admin.yoursite.com')->middleware(['central.web'])->group(function () {
    Route::get('login', [AdminController::class, 'login']);
});

Route::middleware(['tenant.web'])->group(function () {
    Route::get('login', [TenantController::class, 'login']);
});
```

### After (Smart Routes):
```php
// ✅ NEW: One smart route definition
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('login', [AuthController::class, 'login']); // Works on both!
});
```

## Testing

```php
// Test central domain
$response = $this->get('http://localhost/login');
$response->assertViewHas('isCentral', true);

// Test tenant domain  
$response = $this->get('http://tenant1.test/login');
$response->assertViewHas('isTenant', true);
```

## ⚡ Quick Tips

1. **Use for shared routes**: login, dashboard, profile, settings
2. **Keep domain-specific routes separate**: admin panels, tenant-only features
3. **Check context in controllers**: `$request->attributes->get('is_central')`
4. **Use view variables**: `@if($isCentral)` in Blade templates
5. **Livewire ready**: Sessions automatically scoped per context

## 🔧 Configuration

```env
# .env - Define your central domains
TENANCY_CENTRAL_DOMAINS="localhost,127.0.0.1,admin.yoursite.com"
```

```php
// config/artflow-tenancy.php - Add more central domains
'additional_central_domains' => [
    'admin.yoursite.com',
    'management.yoursite.com',
],
```

---
**Perfect for**: Login pages, dashboards, profiles, settings that need to work on both central and tenant domains with proper context.
