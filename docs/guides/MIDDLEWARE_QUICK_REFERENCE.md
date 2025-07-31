# Middleware Quick Reference

## TL;DR - What Middleware Should I Use?

### ✅ For Tenant-Only Routes (Recommended for most tenant features)
```php
Route::middleware(['tenant.web'])->group(function () {
    // Your tenant application routes here
    // Available on tenant domains ONLY
    // Full session scoping + all stancl/tenancy features
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::resource('posts', PostController::class);
});
```

### ✅ For Universal Routes (Work on both central and tenant domains)
```php
Route::middleware(['universal.web'])->group(function () {
    // Routes that should work everywhere
    // Automatically detects tenant context if available
    Route::get('/', [HomeController::class, 'index']);
    Route::get('about', [AboutController::class, 'index']);
});
```

### ✅ For Central-Only Routes (Admin/management)
```php
Route::middleware(['central.web'])->group(function () {
    // Your admin/management routes here
    // Available on central domains ONLY
    Route::get('admin', [AdminController::class, 'index']);
});
```

## ❌ What NOT to Use Anymore

### DON'T use these (they were removed):
- `universal.auth` - Use Laravel's built-in `auth` middleware instead
- `universal-tenancy` - Simplified to `universal.web`  
- `smart-domain` - Replaced by `universal.web`
- `central.tenant.web` - Use `universal.web` instead

## How It Works (Built on stancl/tenancy)

This package extends `stancl/tenancy` with additional features, but uses the same core concepts:

### Tenant Routes (Full stancl/tenancy features)
```php
// Uses stancl/tenancy middleware stack
Route::middleware(['tenant.web'])->group(function () {
    // ✅ Full session scoping (critical for Livewire)
    // ✅ Database switching per tenant
    // ✅ Prevented access from central domains
    // ✅ All stancl/tenancy bootstrappers
    Route::get('/', function() {
        return "Tenant: " . tenant('id'); // stancl/tenancy helper
    });
});
```

### Universal Routes (Works everywhere)
```php
// Our enhancement - tries tenant, falls back gracefully
Route::middleware(['universal.web'])->group(function () {
    Route::get('/', function() {
        if (tenancy()->initialized) {
            return "Tenant: " . tenant('id');
        }
        return "Central App";
    });
});
```

## Universal Authentication Patterns

### Pattern 1: Single Universal Login
```php
// One login route that works for both central and tenant contexts
Route::middleware(['universal.web'])->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
});

// Protected routes work everywhere
Route::middleware(['universal.web', 'universal.auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('profile', [ProfileController::class, 'index']);
});
```

### Pattern 2: Context-Aware Controller Logic
```php
// In your AuthController
public function login(Request $request)
{
    // Universal middleware automatically provides context
    if (tenancy()->central) {
        // Handle central domain login
        return $this->handleCentralLogin($request);
    } else {
        // Handle tenant domain login  
        return $this->handleTenantLogin($request);
    }
}

public function showLoginForm()
{
    // Universal middleware provides tenant context
    $tenant = tenant(); // null on central, tenant model on tenant domains
    return view('auth.login', compact('tenant'));
}
```

## Livewire with Universal Middleware

### ✅ Simple Universal Livewire Setup
```php
// routes/web.php - Works on both central and tenant domains
Route::middleware(['universal.web'])->group(function () {
    Route::get('profile', ProfileComponent::class);
    Route::get('settings', SettingsComponent::class);
    Route::get('dashboard', DashboardComponent::class);
});
```

### Livewire Component Example
```php
class ProfileComponent extends Component
{
    public function mount()
    {
        // Universal middleware provides context automatically
        if (tenancy()->central) {
            // Central domain logic
            $this->user = auth()->user(); // Central user
        } else {
            // Tenant domain logic  
            $this->user = auth()->user(); // Tenant-scoped user
            $this->tenant = tenant(); // Current tenant
        }
    }
}
```

## Route File Organization (Simplified)

```
routes/
├── web.php          # Universal routes with ['universal.web']
├── api.php          # API routes (can use universal middleware too)
└── af-tenancy.php   # Package routes (already configured)
```

### Single Route File Example
```php
// routes/web.php - Everything in one place
Route::middleware(['universal.web'])->group(function () {
    // Public routes (work everywhere)
    Route::get('/', [HomeController::class, 'index']);
    Route::get('about', [AboutController::class, 'index']);
    Route::get('login', [AuthController::class, 'showLoginForm']);
    Route::post('login', [AuthController::class, 'login']);
    
    // Protected routes (work everywhere with auth)
    Route::middleware(['universal.auth'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::resource('posts', PostController::class);
        Route::get('profile', [ProfileController::class, 'index']);
    });
});
```

## Testing Universal Setup

### Test Any Domain:
```bash
# Central domain
curl http://localhost/login              # Works
curl http://localhost/dashboard          # Works (if authenticated)

# Tenant domain  
curl http://tenant1.localhost/login      # Works
curl http://tenant1.localhost/dashboard  # Works (if authenticated)

# Admin domain
curl http://admin.yoursite.com/login     # Works
curl http://admin.yoursite.com/dashboard # Works (if authenticated)
```

## Common Scenarios with Universal Middleware

### Multi-Tenant SaaS Application
```php
Route::middleware(['universal.web'])->group(function () {
    // Landing page (works on central domain)
    Route::get('/', [LandingController::class, 'index']);
    
    // Tenant application (works on tenant domains)
    Route::middleware(['universal.auth'])->group(function () {
        Route::get('app', [AppController::class, 'index']);
        Route::resource('projects', ProjectController::class);
    });
    
    // Admin panel (works on central domain when authenticated)
    Route::prefix('admin')->middleware(['universal.auth'])->group(function () {
        Route::get('tenants', [AdminTenantController::class, 'index']);
    });
});
```

### Blog with Multi-Tenancy
```php
Route::middleware(['universal.web'])->group(function () {
    // Public blog routes (work everywhere)
    Route::get('/', [BlogController::class, 'index']);
    Route::get('post/{slug}', [BlogController::class, 'show']);
    
    // Author dashboard (authenticated, works on all domains)
    Route::middleware(['universal.auth'])->group(function () {
        Route::get('write', [BlogController::class, 'create']);
        Route::post('posts', [BlogController::class, 'store']);
    });
});
```

## Migration from Legacy Middleware

### Before (Complex):
```php
// Central routes
Route::middleware(['central.web'])->group(function () {
    Route::get('admin/dashboard', [AdminController::class, 'dashboard']);
});

// Tenant routes
Route::middleware(['tenant.web'])->group(function () {
    Route::get('dashboard', [TenantController::class, 'dashboard']);  
});

// Auth routes (duplicated)
Route::middleware(['central.web'])->group(function () {
    Route::get('admin/login', [AdminAuthController::class, 'login']);
});
Route::middleware(['tenant.web'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'login']);
});
```

### After (Simple):
```php
// Universal routes (work everywhere)
Route::middleware(['universal.web'])->group(function () {
    // Single login for all contexts
    Route::get('login', [AuthController::class, 'showLogin']);
    Route::post('login', [AuthController::class, 'login']);
    
    // Protected routes with context awareness
    Route::middleware(['universal.auth'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']); // Context-aware
    });
});
```

## Domain Configuration (Unchanged)

Your domain configuration remains the same:

```env
# .env
TENANCY_CENTRAL_DOMAINS="localhost,127.0.0.1,admin.yoursite.com"
APP_DOMAIN="yoursite.com"
```

```php
// config/tenancy.php
'central_domains' => [
    'localhost',
    '127.0.0.1', 
    'admin.yoursite.com',
],
```

The universal middleware automatically detects the context based on these settings.
