# 🚀 Central Domain Setup Guide - Artflow Studio Tenancy v0.6.0

## 🎯 **Quick Start - Central Domain Support**

### **Problem Solved**
Before v0.6.0, accessing your Laravel app on `127.0.0.1` or `localhost` would show:
```
❌ Tenant could not be identified on domain 127.0.0.1
```

After v0.6.0, this works perfectly:
```
✅ Welcome to Laravel (on central domain)
✅ Admin dashboards work
✅ APIs work  
✅ Tenant domains still work
```

## 🛠️ **Installation**

### **1. Install/Update the Package**
```bash
composer require artflow-studio/tenancy
```

### **2. Publish Configuration**
```bash
# Publish tenancy configuration
php artisan vendor:publish --tag=tenancy-config

# Publish artflow-tenancy configuration  
php artisan vendor:publish --tag=artflow-tenancy-config
```

### **3. Update Environment** (Optional)
```env
# .env file
APP_DOMAIN=localhost

# Central domains are automatically configured:
# - 127.0.0.1
# - localhost  
# - Your APP_DOMAIN value
```

### **4. Test Central Domain**
```bash
# Start development server
php artisan serve

# Visit http://127.0.0.1:8000/
# Should show Laravel welcome page (no tenant errors!)
```

## 🎯 **Usage Examples**

### **1. Central Domain Routes (Admin, API)**
```php
// routes/web.php or routes/api.php

// Admin dashboard - only accessible on central domains
Route::middleware(['central', 'auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::get('/system-health', [SystemController::class, 'health']);
});

// API endpoints - only on central domains
Route::middleware(['api', 'central.tenant'])->prefix('api')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'OK',
            'domain' => request()->getHost(),
            'type' => 'central'
        ]);
    });
    
    Route::get('/tenants', [ApiController::class, 'tenants']);
    Route::post('/tenants', [ApiController::class, 'createTenant']);
});
```

### **2. Smart Domain Routes (Central + Tenant)**
```php
// routes/web.php

// Routes that work on both central and tenant domains
Route::middleware(['web', 'smart.domain'])->group(function () {
    Route::get('/', function () {
        if (app()->bound('tenant')) {
            return "Welcome to " . tenant('name') . " tenant!";
        }
        return view('welcome'); // Central domain welcome
    });
    
    Route::get('/dashboard', function () {
        if (app()->bound('tenant')) {
            return view('tenant.dashboard');
        }
        return view('central.dashboard');
    })->middleware('auth');
});
```

### **3. Tenant-Only Routes** (Unchanged)
```php
// routes/tenant.php

Route::middleware(['tenant'])->group(function () {
    Route::get('/tenant-dashboard', [TenantController::class, 'dashboard']);
    Route::get('/tenant-settings', [TenantController::class, 'settings']);
    Route::get('/tenant-users', [UserController::class, 'index']);
});
```

## 🧩 **Middleware Reference**

### **Available Middleware Aliases**

| Middleware | Purpose | Use Case |
|------------|---------|----------|
| `central` | Central domains only | Admin dashboards, system pages |
| `central.tenant` | Central domains only (API) | API endpoints, webhooks |
| `smart.domain` | Auto-detect central/tenant | Shared routes, homepage |
| `tenant` | Tenant domains only | Tenant-specific features |
| `tenancy.api` | API authentication | Protected API endpoints |

### **Middleware Groups**

```php
// Central domain group
Route::middleware(['central'])->group(function () {
    // Central domain routes with web middleware
});

// Smart domain group  
Route::middleware(['web', 'smart.domain'])->group(function () {
    // Routes that work on both central and tenant domains
});

// Tenant group (unchanged)
Route::middleware(['tenant'])->group(function () {
    // Tenant-specific routes
});
```

## 🔧 **Configuration Options**

### **Central Domains Configuration**
```php
// config/tenancy.php

'central_domains' => [
    '127.0.0.1',
    'localhost',
    env('APP_DOMAIN', 'localhost'),
    // Add custom central domains here
    'admin.yourapp.com',
    'api.yourapp.com',
],
```

### **Environment Variables**
```env
# .env file

# Your main application domain (automatically added to central domains)
APP_DOMAIN=yourapp.com

# Tenant configuration (unchanged)
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql

# Session and cache (Redis optional)
SESSION_DRIVER=database
CACHE_STORE=file
```

## 🧪 **Testing Your Setup**

### **1. Test Central Domain Access**
```bash
# Test homepage
curl http://127.0.0.1:8000/

# Test API health  
curl http://127.0.0.1:8000/api/health

# Test admin access (if configured)
curl http://127.0.0.1:8000/admin/dashboard
```

### **2. Test Tenant Domain Access**
```bash
# Create a tenant first
php artisan tenant:manage
# Select: create
# Name: Test Company
# Domain: test.local

# Test tenant access (requires local DNS or hosts file)
curl http://test.local/
```

### **3. Use Built-in Testing Commands**
```bash
# Test comprehensive system
php artisan tenancy:test-comprehensive

# Test performance  
php artisan tenancy:test-performance

# Check system health
php artisan tenancy:health

# Manage tenants interactively
php artisan tenant:manage
```

## 🎯 **Common Use Cases**

### **1. SaaS Application Setup**
```php
// Central domain: yourapp.com
Route::middleware(['central'])->group(function () {
    Route::get('/', [MarketingController::class, 'homepage']);
    Route::get('/pricing', [MarketingController::class, 'pricing']);
    Route::get('/signup', [AuthController::class, 'signup']);
    Route::post('/register-tenant', [TenantController::class, 'register']);
});

// Tenant domains: tenant1.yourapp.com, tenant2.yourapp.com
Route::middleware(['tenant'])->group(function () {
    Route::get('/', [TenantDashboardController::class, 'index']);
    Route::resource('users', TenantUserController::class);
    Route::resource('projects', ProjectController::class);
});
```

### **2. Admin Panel + Multi-Tenant App**
```php
// Admin on central domain: admin.yourapp.com
Route::middleware(['central', 'auth:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::resource('/tenants', AdminTenantController::class);
    Route::get('/system-stats', [SystemController::class, 'stats']);
});

// Customer access on tenant domains
Route::middleware(['tenant', 'auth'])->group(function () {
    Route::get('/dashboard', [CustomerController::class, 'dashboard']);
    Route::resource('/orders', OrderController::class);
});
```

### **3. API + Web Application**
```php
// API on central domain
Route::middleware(['api', 'central.tenant'])->prefix('api/v1')->group(function () {
    Route::get('/tenants', [ApiController::class, 'tenants']);
    Route::post('/tenants', [ApiController::class, 'createTenant']);
    Route::get('/health', [ApiController::class, 'health']);
});

// Web interface on both central and tenant domains
Route::middleware(['web', 'smart.domain'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::middleware('auth')->get('/dashboard', [DashboardController::class, 'index']);
});
```

## 🔍 **Troubleshooting**

### **Common Issues & Solutions**

#### **1. Still getting "Tenant could not be identified" error**
```bash
# Clear configuration cache
php artisan config:clear

# Check your central domains configuration
php artisan config:show tenancy.central_domains

# Expected output:
# [
#   "127.0.0.1",
#   "localhost", 
#   "yourapp.com"  // your APP_DOMAIN
# ]
```

#### **2. Routes not working on central domain**
```php
// Make sure you're using the correct middleware
Route::middleware(['central'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// Or use smart domain resolver
Route::middleware(['web', 'smart.domain'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
});
```

#### **3. Tenant routes not working**
```php
// Tenant routes should use the tenant middleware (unchanged)
Route::middleware(['tenant'])->group(function () {
    Route::get('/', [TenantController::class, 'dashboard']);
});
```

#### **4. Check middleware registration**
```bash
# List all registered middleware
php artisan route:list --middleware

# Should show:
# central, central.tenant, smart.domain, tenant, tenancy.api
```

## 🎉 **Success Indicators**

You know everything is working when:

- ✅ `http://127.0.0.1:8000/` shows Laravel welcome page (no tenant errors)
- ✅ `http://127.0.0.1:8000/api/health` returns JSON response
- ✅ Tenant domains still work: `http://tenant.local/`
- ✅ `php artisan tenancy:health` shows all checks passing
- ✅ `php artisan tenancy:test-comprehensive` shows 100% success rate

## 🚀 **Next Steps**

1. **Configure your admin dashboard** on the central domain
2. **Set up your API endpoints** using `central.tenant` middleware  
3. **Create tenant-specific routes** using the `tenant` middleware
4. **Use smart domain resolver** for shared functionality
5. **Test with the built-in commands** to ensure everything works

---

**Need help?** Check the [full documentation](README.md) or run `php artisan tenancy:health` to diagnose issues.
