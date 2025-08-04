# Homepage Redirect Middleware Usage

The `HomepageRedirectMiddleware` is already registered in the package and can be used to handle tenant homepage display and login redirects.

## How it works

1. **Tenant Detection**: Checks if we're in a valid tenant context using `app('tenant')`
2. **Homepage Check**: Verifies if the tenant has homepage enabled via `$tenant->hasHomepage()`
3. **View Resolution**: Looks for tenant-specific homepage views in this order:
   - `tenants.{domain}.home` (e.g., `tenants.example.com.home`)
   - `tenants.home` (fallback)
4. **Redirect**: If no tenant, no homepage enabled, or no views found, redirects to `/login`

## Usage in your web.php routes

```php
// Apply to tenant homepage route
Route::get('/', function() {
    return view('welcome'); // This won't be reached if middleware handles it
})->name('tenant.home')->middleware(['tenant', 'homepage.redirect']);

// Or apply to a group of routes
Route::middleware(['tenant', 'homepage.redirect'])->group(function () {
    Route::get('/', function() {
        return view('welcome');
    })->name('tenant.home');
    
    // Other tenant routes...
});
```

## Configuration

The middleware uses these config values from `artflow-tenancy.php`:

```php
'homepage' => [
    'view_path' => 'tenants', // Base path for homepage views
    // ... other homepage config
],
```

## View Structure

Create your tenant homepage views in:
- `resources/views/tenants/{domain}/home.blade.php` - Domain-specific homepage
- `resources/views/tenants/home.blade.php` - Default tenant homepage

The views will receive:
- `$tenant` - The current tenant model
- `$domain` - The current domain name

## Middleware Alias

The middleware is registered with the alias `homepage.redirect` so you can use it like:

```php
->middleware('homepage.redirect')
```
