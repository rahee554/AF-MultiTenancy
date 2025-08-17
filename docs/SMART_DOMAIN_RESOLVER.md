# 🎯 Smart Domain Resolver - Complete Guide

## Overview

The Smart Domain Resolver automatically detects whether a request is from a central domain (admin) or tenant domain, applying the appropriate context without requiring separate route definitions.

## The Problem This Solves

You have routes like `/login`, `/dashboard`, `/profile` that need to work on **both**:
- **Central domains** (localhost, admin.yoursite.com) - for admin users
- **Tenant domains** (tenant1.yoursite.com, tenant2.yoursite.com) - for tenant users

## Quick Start

### 1. Use the Smart Middleware
```php
// routes/web.php - These routes work on BOTH central and tenant domains
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
```

### 2. Access Context in Controllers
```php
class AuthController extends Controller
{
    public function showLoginForm(Request $request)
    {
        $domainType = $request->attributes->get('domain_type'); // 'central' or 'tenant'
        $currentTenant = $request->attributes->get('tenant');   // null or Tenant object
        
        return view('auth.login'); // Gets context variables automatically
    }
}
```

### 3. Use Context in Views
```blade
@if($isCentral)
    <h1>Admin Login</h1>
@else
    <h1>Welcome to {{ $currentTenant->name }}</h1>
@endif
```
        if (Auth::guard('admin')->attempt($request->only('email', 'password'))) {
            return redirect()->route('admin.dashboard');
        }
        return back()->withErrors(['email' => 'Invalid credentials']);
    }
    
    private function attemptTenantLogin(Request $request, $tenant)
    {
        // Use tenant-scoped authentication
        if (Auth::attempt($request->only('email', 'password'))) {
            return redirect()->route('tenant.dashboard');
        }
        return back()->withErrors(['email' => 'Invalid credentials']);
    }
}
```

### Shared Dashboard Controller
```php
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->attributes->get('is_central')) {
            // Central dashboard - show tenant management, system stats
            $tenants = Tenant::with('domains')->get();
            return view('dashboard.central', compact('tenants'));
        } else {
            // Tenant dashboard - show tenant-specific data
            $tenant = $request->attributes->get('tenant');
            $users = User::where('tenant_id', $tenant->id)->get();
            return view('dashboard.tenant', compact('users'));
        }
    }
}
```

## View Examples

### Shared Login View
```blade
{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.app')

@section('title')
    @if($isCentral)
        Admin Login - System Management
    @else
        Login - {{ $currentTenant->name ?? 'Tenant Area' }}
    @endif
@endsection

@section('content')
<div class="login-container">
    @if($isCentral)
        <h1>Admin Panel Login</h1>
        <p>Manage tenants and system settings</p>
    @else
        <h1>{{ $currentTenant->name ?? 'Tenant' }} Login</h1>
        <p>Welcome to your tenant area</p>
    @endif
    
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <input type="email" name="email" placeholder="Email" required>
        </div>
        <div class="form-group">
            <input type="password" name="password" placeholder="Password" required>
        </div>
        <button type="submit">
            @if($isCentral)
                Login to Admin Panel
            @else
                Login to {{ $currentTenant->name ?? 'Tenant Area' }}
            @endif
        </button>
    </form>
</div>
@endsection
```

### Shared Dashboard View
```blade
{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app')

@section('content')
@if($isCentral)
    {{-- Central Dashboard --}}
    <h1>System Administration Dashboard</h1>
    <div class="admin-stats">
        <div class="stat-card">
            <h3>Total Tenants</h3>
            <p>{{ $tenants->count() }}</p>
        </div>
        <div class="stat-card">
            <h3>Active Tenants</h3>
            <p>{{ $tenants->where('status', 'active')->count() }}</p>
        </div>
    </div>
    <a href="{{ route('tenancy.tenants.index') }}">Manage Tenants</a>
@else
    {{-- Tenant Dashboard --}}
    <h1>{{ $currentTenant->name }} Dashboard</h1>
    <div class="tenant-stats">
        <div class="stat-card">
            <h3>Users</h3>
            <p>{{ $users->count() }}</p>
        </div>
        <div class="stat-card">
            <h3>Active Users</h3>
            <p>{{ $users->where('is_active', true)->count() }}</p>
        </div>
    </div>
    <p>Tenant Domain: {{ $currentTenant->domains->first()->domain ?? 'N/A' }}</p>
@endif
@endsection
```

## Advanced Usage

### Conditional Middleware in Same Route Group
```php
Route::middleware(['central.tenant.web'])->group(function () {
    // Authentication routes - work on both
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    
    // Protected routes - work on both but with different auth
    Route::middleware('auth')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    });
    
    // Admin-only routes - only work on central, blocked on tenant
    Route::middleware(['central-only'])->group(function () {
        Route::get('system-settings', [SystemController::class, 'settings']);
    });
});
```

### API Routes with Smart Detection
```php
// Same API endpoints work on both domain types
Route::prefix('api')->middleware(['central.tenant.web'])->group(function () {
    Route::get('user', function (Request $request) {
        if ($request->attributes->get('is_central')) {
            // Return admin user data
            return response()->json(Auth::guard('admin')->user());
        } else {
            // Return tenant user data
            return response()->json(Auth::user());
        }
    })->middleware('auth');
    
    Route::get('stats', [StatsController::class, 'index']);
});
```

### Livewire Components
```php
// Smart Livewire components that work on both
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('settings', SettingsComponent::class)->name('settings');
    Route::get('user-management', UserManagementComponent::class)->name('users');
});
```

```php
// In your Livewire component
class SettingsComponent extends Component
{
    public $domainType;
    public $currentTenant;
    
    public function mount(Request $request)
    {
        $this->domainType = $request->attributes->get('domain_type');
        $this->currentTenant = $request->attributes->get('tenant');
    }
    
    public function render()
    {
        if ($this->domainType === 'central') {
            return view('livewire.settings-central');
        } else {
            return view('livewire.settings-tenant');
        }
    }
}
```

## Configuration

### Additional Central Domains
```php
// config/artflow-tenancy.php
return [
    // Add extra domains that should be treated as central
    'additional_central_domains' => [
        'admin.yoursite.com',
        'management.yoursite.com',
        'control.yoursite.com',
    ],
];
```

### Environment Configuration
```env
# .env - Central domains
TENANCY_CENTRAL_DOMAINS="localhost,127.0.0.1,admin.yoursite.com"
APP_DOMAIN="yoursite.com"
```

## Testing

### Feature Test Example
```php
class SmartDomainTest extends TestCase
{
    public function test_login_works_on_central_domain()
    {
        $response = $this->get('http://localhost/login');
        $response->assertOk();
        $response->assertViewHas('isCentral', true);
        $response->assertViewHas('currentTenant', null);
    }
    
    public function test_login_works_on_tenant_domain()
    {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => 'tenant1.test']);
        
        $response = $this->get('http://tenant1.test/login');
        $response->assertOk();
        $response->assertViewHas('isTenant', true);
        $response->assertViewHas('currentTenant');
    }
}
```

## Benefits

1. **Single Route Definition** - Define `/login` once, works everywhere
2. **Automatic Context** - Controllers and views automatically get domain context
3. **Session Isolation** - Tenant sessions are properly scoped
4. **Maintainable** - No route duplication or complex conditionals
5. **Flexible** - Easy to add domain-specific logic where needed
6. **Livewire Compatible** - Full session scoping support

## Migration Guide

### From Separate Routes:
```php
// ❌ OLD: Separate route definitions
Route::middleware(['central.web'])->group(function () {
    Route::get('admin/login', [AdminAuthController::class, 'showLoginForm']);
});

Route::middleware(['tenant.web'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'showLoginForm']);
});
```

```php
// ✅ NEW: Single smart route
Route::middleware(['central.tenant.web'])->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm']); // Works on both!
});
```

### Controller Adaptation:
```php
// Update your controllers to use the new context attributes
public function showLoginForm(Request $request)
{
    // Old way - complex domain checking
    // if (in_array($request->getHost(), config('tenancy.central_domains'))) {
    
    // New way - simple attribute check
    if ($request->attributes->get('is_central')) {
        // Central logic
    } else {
        // Tenant logic  
    }
}
```

This smart middleware gives you the best of both worlds: shared routes with proper domain-specific context!
