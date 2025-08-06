# Artflow Studio Tenancy Package

Enhanced Laravel multi-tenancy package built on top of stancl/tenancy with status management, homepage functionality, and Livewire compatibility.

## Features

- Built on top of the stable and battle-tested [stancl/tenancy](https://tenancyforlaravel.com/) package
- Enhanced tenant models with status management (active, inactive, blocked)
- Automatic tenant database creation and migration
- Livewire compatibility with proper session scoping
- Homepage redirection middleware
- API authentication middleware
- Performance optimizations with caching
- Easy installation and configuration

## Installation

1. Make sure you have stancl/tenancy installed:
```bash
composer require stancl/tenancy
```

2. Install this package:
```bash
composer require artflow-studio/tenancy
```

3. Publish the configuration files:
```bash
php artisan vendor:publish --tag=tenancy-config
php artisan vendor:publish --tag=af-tenancy-config
```

4. Configure your middleware in `bootstrap/app.php` (Laravel 11):

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        // Register stancl/tenancy middleware aliases
        $middleware->alias([
            ## Stancl Tenancy Middleware ##
            'tenant' => \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            'tenant.prevent-central' => \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            'tenant.scope-sessions' => \Stancl\Tenancy\Middleware\ScopeSessions::class,
            
            ## ArtflowStudio Tenancy Middleware (built on top of stancl) ##
            'af-tenant' => \ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware::class,
            'central' => \ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware::class,
            'tenant.homepage' => \ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware::class,
            'tenant.auth' => \ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware::class,
        ]);

        // Register middleware groups for tenancy
        $middleware->group('tenant.web', [
            'web',
            'tenant', // stancl/tenancy domain initialization
            'tenant.prevent-central', // prevent access from central domains  
            'tenant.scope-sessions', // scope sessions per tenant
            'af-tenant', // artflow enhancements
        ]);

        $middleware->group('central.web', [
            'web',
        ]);

        $middleware->group('tenant.api', [
            'api',
            'tenant',
            'tenant.prevent-central',
            'tenant.scope-sessions',
        ]);
    })
    ->create();
```

## Usage

### Route Configuration

#### For Tenant Routes (in `routes/web.php`):

```php
// Homepage (uses tenant.homepage middleware for redirects)
Route::middleware(['tenant.homepage'])->get('/', function () {
    return view('homepage.home');
});

// Admin routes (protected tenant routes)
Route::middleware([
    'tenant.web', // Full tenant middleware group
    'auth', 
    'role:admin'
])->name('admin::')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    // ... other admin routes
});
```

#### For Auth Routes (in `routes/auth.php`):

```php
// Guest auth routes (login, register, etc.)
Route::middleware(['guest', 'tenant.web'])->group(function () {
    Route::get('login', Login::class)->name('login');   
    Route::get('register', Register::class)->name('register');
    Route::get('forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Authenticated routes
Route::middleware(['auth', 'tenant.web'])->group(function () {
    Route::get('verify-email', VerifyEmail::class)->name('verification.notice');
    // ... other authenticated routes
});

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->middleware('tenant.web')
    ->name('logout');
```

### Central Domain Configuration

Update your `config/tenancy.php`:

```php
'central_domains' => [
    '127.0.0.1',
    'localhost',
    env('APP_DOMAIN', 'localhost'),
    // Add your actual central domain here
],
```

### Database Configuration

The package automatically handles database creation for tenants. Make sure your database configuration supports multiple databases:

```php
// In config/database.php
'connections' => [
    'mysql' => [
        // Your main connection
    ],
    'tenant' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        // Database name will be set dynamically by the package
    ],
],
```

## Middleware Overview

### Core Middleware Stack (tenant.web group):
1. `web` - Laravel's web middleware group
2. `tenant` - stancl/tenancy domain initialization
3. `tenant.prevent-central` - Prevents access from central domains
4. `tenant.scope-sessions` - Scopes sessions per tenant (prevents session bleeding)
5. `af-tenant` - ArtflowStudio enhancements (status checks, last accessed updates)

### Individual Middleware:
- `af-tenant` - Tenant status management and enhancements
- `central` - Central domain handling
- `tenant.homepage` - Homepage redirects for tenants
- `tenant.auth` - Authentication route handling with proper tenant context

## Livewire Compatibility

The package automatically configures Livewire for multi-tenancy:
- Session scoping is handled by stancl/tenancy's `ScopeSessions` middleware
- Livewire components work seamlessly within tenant contexts
- No additional configuration required

## Commands

### Create a tenant:
```bash
php artisan tenant:create example.com
```

### Health check:
```bash
php artisan af-tenancy:health-check
```

### Test system:
```bash
php artisan af-tenancy:test-system
```

## Troubleshooting

### Common Issues:

1. **"Attempt to read property 'headers' on null"**
   - This usually means middleware is not properly configured
   - Make sure you're using the correct middleware groups: `tenant.web` instead of individual middleware

2. **Session bleeding between tenants**
   - Ensure `tenant.scope-sessions` middleware is included in your middleware groups
   - Check that the middleware order is correct (sessions must be scoped after tenant initialization)

3. **Routes not working on subdomains**
   - Verify your DNS/hosts file configuration
   - Check that central domains are properly configured in `config/tenancy.php`
   - Ensure your web server (Apache/Nginx) is configured for wildcard subdomains

4. **Database connection issues**
   - Make sure the database user has permissions to create databases
   - Check database configuration in `config/database.php`
   - Verify tenant database prefix in configuration

### Debug Mode:

Enable detailed logging by setting in your `.env`:
```
LOG_LEVEL=debug
```

This will provide detailed information about tenant initialization, middleware execution, and database connections.

## Architecture

This package enhances stancl/tenancy by:
- Adding tenant status management (active/inactive/blocked)
- Providing optimized middleware groups for Laravel 11
- Including Livewire-specific configurations
- Adding performance caching for tenant lookups
- Providing enhanced debugging and logging

The middleware stack works as follows:
1. stancl/tenancy handles core tenant identification and database switching
2. ArtflowStudio enhancements add status checks and additional features
3. All components work together seamlessly without conflicts

## Requirements

- PHP 8.1+
- Laravel 11+
- stancl/tenancy (latest version)
- MySQL/PostgreSQL/SQLite database

## License

MIT License
