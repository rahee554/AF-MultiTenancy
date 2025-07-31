# Livewire Multi-Tenancy Login Fix - Complete Guide

## Problem Statement

Login requests were failing on tenant domains (e.g., `http://tenant1.local:7777/login`) because:

1. Livewire AJAX requests to `_livewire/update` endpoint were not going through the standard HTTP middleware
2. Tenancy context was not initialized for AJAX calls
3. The `TenancyBootstrapperHook` was only triggered on component mount, not on method calls

## Root Cause Analysis

### How Livewire Requests Work

```
User clicks "Login"
        ↓
Browser sends AJAX POST to /_livewire/update
        ↓
Request goes directly to Livewire's request handler
        ↓
Middleware stack runs:
  - web (sessions, CSRF, etc.)
  - universal.auth (our auth middleware)
  - ...
        ↓
Component method login() is called
  ↓
❌ PROBLEM: Tenancy might not be initialized!
  ↓
Auth::attempt() queries main database instead of tenant database
  ↓
Login fails because user not found in main database
```

### Why Mount Hook Wasn't Enough

```
Initial page load:
  - mount event fires ✓
  - TenancyBootstrapperHook::bootstrap() runs ✓
  - Tenancy initialized ✓

AJAX call (login submit):
  - mount event does NOT fire (component already mounted)
  - No tenancy initialization ✗
  - login() method called without tenant context ✗
  - Auth::attempt() queries wrong database ✗
```

## Solution: Multiple Lifecycle Hooks

We now hook into THREE Livewire events:

### 1. Mount Event

```php
\Livewire\on('mount', function ($component, $params, $key, $parent) {
    TenancyBootstrapperHook::bootstrap();
});
```

Fires when component is first loaded. Initializes tenancy for the page.

### 2. Hydrate Event

```php
\Livewire\on('hydrate', function ($component, $memo) {
    TenancyBootstrapperHook::bootstrap();
});
```

Fires when component state is rehydrated. Ensures tenancy during state updates.

### 3. Call Event ⭐ **CRITICAL**

```php
\Livewire\on('call', function ($component, $method, $params, $addEffect, $earlyReturn) {
    TenancyBootstrapperHook::bootstrap();
});
```

Fires before component methods execute. **This is what fixed the login!**

Now the flow works:

```
AJAX call to /_livewire/update
        ↓
Middleware runs (universal.auth)
        ↓
Livewire's 'call' event fires
        ↓
TenancyBootstrapperHook::bootstrap() runs ✓
        ↓
Tenancy initialized with correct database ✓
        ↓
login() method executes
        ↓
Auth::attempt() queries TENANT database ✓
        ↓
Login succeeds! ✓
```

## Changes Made

### 1. TenancyServiceProvider.php

**Added multiple Livewire event hooks:**

```php
// CRITICAL: Bootstrap tenancy for Livewire component method calls
\Livewire\on('mount', function ($component, $params, $key, $parent) {
    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
});

// CRITICAL: Bootstrap tenancy for AJAX calls (call event)
\Livewire\on('call', function ($component, $method, $params, $addEffect, $earlyReturn) {
    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
});

// CRITICAL: Bootstrap tenancy for hydration
\Livewire\on('hydrate', function ($component, $memo) {
    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
});
```

**Removed problematic middleware:**

```php
// Removed: PreventAccessFromCentralDomains
// It was causing issues with universal routes that should work on both central and tenant domains
Livewire::addPersistentMiddleware([
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    // ❌ Removed: \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
]);
```

### 2. Login Component

**Added explicit tenancy bootstrap:**

```php
public function login(): void
{
    $this->validate();
    $this->ensureIsNotRateLimited();

    // Ensure tenancy is initialized for tenant authentication
    // This is important for AJAX calls where middleware might not run
    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();

    if (! Auth::attempt([...])) {
        // Handle login failure
    }
}
```

This provides an additional safety net to ensure tenancy is initialized before authentication.

## Testing the Fix

### Manual Testing

1. Navigate to `http://tenant1.local:7777/login`
2. Enter credentials: `admin@al-emaan.pk` / `123456`
3. Click "Login"
4. Should authenticate successfully and redirect to dashboard

### Automated Testing

```bash
php artisan test tests/Feature/Auth/AuthenticationTest.php
```

All 4 tests should pass:
- ✓ login screen can be rendered
- ✓ users can authenticate using the login screen
- ✓ users can not authenticate with invalid password
- ✓ users can logout

## How It Works Now - Complete Flow

### Page Load (Tenant: tenant1.local)

```
1. Browser: GET /login
2. Laravel Route: Route::get('login', Login::class)->middleware('universal.auth')
3. Middleware: universal.auth runs
   └─ TenantAuthMiddleware initializes tenancy for tenant1 ✓
4. Livewire 'mount' event fires
   └─ TenancyBootstrapperHook::bootstrap() ensures tenancy still initialized ✓
5. Component renders with proper tenant database context ✓
```

### Login Form Submit (AJAX)

```
1. Browser: POST /_livewire/update
2. Livewire Request Handler receives AJAX request
3. Middleware runs:
   └─ universal.auth runs
      └─ Initializes tenancy for tenant1 (from domain) ✓
4. Livewire 'call' event fires
   └─ TenancyBootstrapperHook::bootstrap() ensures tenancy initialized ✓
5. login() method executes
   └─ TenancyBootstrapperHook::bootstrap() called again (safety net) ✓
6. Auth::attempt() queries tenant1 database ✓
7. User found ✓
8. Login successful ✓
9. Session created in tenant1 sessions table ✓
10. Cache stored in tenant1 cache table ✓
```

### Request Completion

```
1. All bootstrappers revert to central context
2. Response sent
3. Request ends
```

## Session & Cache Isolation Verification

After login, verify isolation:

```php
// In any controller or component on tenant1.local:
session()->put('test_key', 'tenant1_value');
cache()->put('test_key', 'tenant1_value', 3600);

// These are stored in:
// - Session: tenant_1_db.sessions table
// - Cache: tenant_1_db.cache table

// Switch to tenant2.local in another browser tab:
session()->get('test_key'); // null (not in tenant2 sessions)
cache()->get('test_key');   // null (not in tenant2 cache)

// Back to tenant1.local:
session()->get('test_key'); // 'tenant1_value' ✓
cache()->get('test_key');   // 'tenant1_value' ✓
```

## Troubleshooting

### Issue: Login still fails on tenant domain

**Check:**
1. Is the tenant database connection properly configured?
   ```bash
   php artisan tinker
   >>> tenancy()->initialize(\ArtflowStudio\Tenancy\Models\Tenant::first())
   >>> DB::connection('tenant')->table('users')->count()
   ```

2. Are the bootstrap hooks being called?
   - Add `logger()->info('Bootstrapping tenancy')` to `TenancyBootstrapperHook::bootstrap()`
   - Check logs after login attempt

3. Is the universal.auth middleware properly set up?
   ```php
   // Check config/tenancy.php and routes/auth.php
   ```

### Issue: Sessions not being stored in tenant database

**Check:**
1. Is `SESSION_DRIVER=database` in `.env`?
2. Are migrations run? `php artisan migrate`
3. Check `SessionTenancyBootstrapper` configuration

### Issue: Users can still access login when logged in

This is now fixed by the `mount()` method in the Login component which redirects authenticated users.

## Best Practices

1. **Always use universal.auth middleware** for auth routes
2. **Use TenantAware trait** on models that exist in both databases
3. **Test multi-tenant flows** with actual tenant requests
4. **Monitor logs** for tenancy initialization errors
5. **Use database sessions** for better isolation (avoid cookie sessions)

## Performance Considerations

- Multiple `TenancyBootstrapperHook::bootstrap()` calls are idempotent
- Each call checks if tenancy is already initialized and returns early
- No performance impact from redundant calls

## Configuration

### Config: universal.auth middleware

```php
// routes/auth.php
Route::middleware(['web', 'universal.auth'])->group(function () {
    Route::get('login', Login::class)->name('login');
    // Other auth routes...
});
```

### Config: Session Isolation

```env
SESSION_DRIVER=database
TENANT_CACHE_DRIVER=database
```

### Config: Tenancy

```php
// config/artflow-tenancy.php
'session' => [
    'table' => 'sessions',
    'connection' => 'tenant',
],
'cache' => [
    'isolation_mode' => 'database',
    'table' => 'cache',
    'connection' => 'tenant',
],
```

---

**Login is now working universally on both central and tenant domains! 🎉**
