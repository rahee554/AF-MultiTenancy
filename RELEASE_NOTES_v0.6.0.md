# 🚀 Artflow Studio Tenancy v0.6.0 - Major Release

## 🎯 **Central Domain Support & Smart Domain Resolution**

### 🔥 **New Features**

#### 1. **Smart Domain Resolution System**
- ✅ **Automatic Domain Detection**: Intelligently routes between central and tenant domains
- ✅ **Central Domain Middleware**: New `central.tenant` middleware alias for central domain routes
- ✅ **Smart Domain Resolver**: Unified middleware that handles both central and tenant requests
- ✅ **Conflict-Free Routing**: No more "Tenant could not be identified" errors on central domains

#### 2. **Enhanced Middleware Stack**
```php
// Central domain routes (127.0.0.1, localhost, etc.)
Route::middleware(['central'])->group(function () {
    // Your central domain routes
});

// Smart domain resolver (automatically detects central vs tenant)
Route::middleware(['web', 'smart.domain'])->group(function () {
    // Routes that work on both central and tenant domains
});

// Traditional tenant routes
Route::middleware(['tenant'])->group(function () {
    // Tenant-specific routes
});
```

#### 3. **Central Domain Configuration**
```php
// config/tenancy.php
'central_domains' => [
    '127.0.0.1',
    'localhost',
    env('APP_DOMAIN', 'localhost'),
],
```

#### 4. **New Middleware Aliases**
- `central.tenant` - Ensures requests come from central domains only
- `smart.domain` - Automatically routes between central and tenant domains
- `tenant` - Traditional tenant middleware (unchanged)
- `tenancy.api` - API authentication middleware (unchanged)

### 🛠️ **Technical Improvements**

#### **Smart Domain Resolution Logic**
```php
class SmartDomainResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        $currentDomain = $request->getHost();
        
        if (in_array($currentDomain, $centralDomains)) {
            // Central domain - proceed without tenant initialization
            return $next($request);
        }
        
        // Tenant domain - initialize tenancy and validate tenant
        $domain = Domain::where('domain', $currentDomain)->first();
        if ($domain && $domain->tenant) {
            tenancy()->initialize($domain->tenant);
            return (new TenantMiddleware())->handle($request, $next);
        }
        
        abort(404, 'Tenant could not be identified on domain ' . $currentDomain);
    }
}
```

#### **Central Domain Middleware**
```php
class CentralDomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        $currentDomain = $request->getHost();
        
        if (!in_array($currentDomain, $centralDomains)) {
            abort(403, 'Access denied. This route is only available on central domains.');
        }
        
        // Clear any tenant context
        if (app()->bound('tenant')) {
            app()->forgetInstance('tenant');
        }
        
        return $next($request);
    }
}
```

### 📋 **Migration Guide**

#### **From v0.5.x to v0.6.0**

1. **Update Route Middleware** (Optional - backwards compatible):
```php
// Old way (still works)
Route::middleware(['web'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
});

// New way (recommended for mixed environments)
Route::middleware(['web', 'smart.domain'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
});
```

2. **Central Domain Only Routes**:
```php
// For routes that should ONLY work on central domains
Route::middleware(['central'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
    Route::get('/system-status', [SystemController::class, 'status']);
});
```

3. **API Routes** (No changes required):
```php
// API routes automatically work on central domains
Route::middleware(['api', 'central.tenant'])->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'OK']);
    });
});
```

### 🔧 **Configuration Updates**

#### **Environment Variables** (No changes required):
```env
# Central domain configuration (already supported)
APP_DOMAIN=localhost

# Tenancy configuration (unchanged)
TENANT_DB_PREFIX=tenant_
SESSION_DRIVER=database
CACHE_STORE=file
```

#### **Published Config** (Auto-updated):
```php
// config/tenancy.php - Central domains are automatically configured
'central_domains' => [
    '127.0.0.1',
    'localhost',
    env('APP_DOMAIN', 'localhost'),
],
```

### 🎯 **Use Cases**

#### **1. Admin Dashboard on Central Domain**
```php
Route::middleware(['central', 'auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
});
```

#### **2. API Endpoints on Central Domain**
```php
Route::middleware(['api', 'central.tenant'])->prefix('api')->group(function () {
    Route::get('/health', [ApiController::class, 'health']);
    Route::get('/tenants', [ApiController::class, 'tenants']);
    Route::post('/tenants', [ApiController::class, 'createTenant']);
});
```

#### **3. Shared Routes (Central + Tenant)**
```php
Route::middleware(['web', 'smart.domain'])->group(function () {
    Route::get('/', function () {
        if (app()->bound('tenant')) {
            return "Tenant: " . tenant('name');
        }
        return view('welcome'); // Central domain
    });
});
```

### 🚀 **Performance Benefits**

- ✅ **Zero Overhead**: Central domains bypass tenant resolution entirely
- ✅ **Smart Caching**: Tenant resolution only occurs when needed
- ✅ **Error Prevention**: No more failed tenant lookups on central domains
- ✅ **Backward Compatibility**: Existing routes continue to work unchanged

### 🧪 **Testing Support**

#### **Test Central Domain Access**:
```bash
# Test central domain (should work)
curl http://127.0.0.1:8000/

# Test API health (should work)
curl http://127.0.0.1:8000/api/health

# Test tenant domain (requires tenant setup)
curl http://tenant1.local/
```

#### **Available Commands** (All working):
```bash
# Test performance with central domain support
php artisan tenancy:test-performance

# Test comprehensive system
php artisan tenancy:test-comprehensive

# Manage tenants interactively
php artisan tenant:manage

# Check system health
php artisan tenancy:health
```

### 🔄 **Backward Compatibility**

- ✅ **100% Backward Compatible**: All existing routes and middleware continue to work
- ✅ **Progressive Enhancement**: Add new middleware gradually as needed
- ✅ **Existing Tenant Routes**: No changes required to existing tenant functionality
- ✅ **Configuration**: All existing configuration remains valid

### 🛡️ **Security Enhancements**

- ✅ **Domain Validation**: Strict checking of central vs tenant domains
- ✅ **Context Isolation**: Clear tenant context on central domains
- ✅ **Access Control**: Central domain routes are protected from tenant access
- ✅ **Header Tracking**: Central domain requests are marked with `X-Central-Domain: true`

### 📚 **Documentation Updates**

- ✅ **Installation Guide**: Updated with central domain setup
- ✅ **Middleware Guide**: Complete middleware usage examples
- ✅ **Routing Guide**: Best practices for central and tenant routes
- ✅ **Troubleshooting**: Common issues and solutions

### 🎉 **What's Next**

This release solves the major issue of central domain routing conflicts and provides a robust foundation for mixed central/tenant applications. The smart domain resolution system ensures your application works seamlessly whether accessed via central domains (for admin/API) or tenant domains (for tenant-specific content).

---

## 🔧 **Installation & Usage**

```bash
# Install/Update the package
composer require artflow-studio/tenancy

# Publish configuration (if not already done)
php artisan vendor:publish --tag=tenancy-config

# Test the central domain functionality
php artisan serve
# Visit http://127.0.0.1:8000/ - should work without tenant errors!
```

**Perfect for**: Multi-tenant applications that need admin dashboards, APIs, and tenant-specific content all in one application.

---

**Version**: 0.6.0  
**Release Date**: August 2, 2025  
**Compatibility**: Laravel 11.x, PHP 8.1+
