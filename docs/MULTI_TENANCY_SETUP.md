# 🏢 Complete Multi-Tenancy Setup Guide

This guide explains how to properly set up multi-tenancy for your Laravel application using the ArtflowStudio Tenancy package, with special focus on authentication, Livewire integration, and session management.

## 📚 Table of Contents

1. [Quick Start](#quick-start)
2. [Models Setup](#models-setup)
3. [Authentication Setup](#authentication-setup)
4. [Livewire Integration](#livewire-integration)
5. [Session & Cache Configuration](#session--cache-configuration)
6. [Common Issues & Solutions](#common-issues--solutions)

---

## Quick Start

### 1. Install the Package

```bash
composer require artflow-studio/tenancy
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider"
php artisan migrate
```

### 2. Apply TenantAware Trait to Models

Any model that exists in **both central and tenant databases** should use the `TenantAware` trait:

```php
<?php

namespace App\Models;

use ArtflowStudio\Tenancy\Traits\TenantAware;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, TenantAware;  // ← Add this trait
    
    // Your model code...
}
```

### 3. Update Routes with Proper Middleware

```php
// For tenant routes that need authentication
Route::middleware(['tenant.web', 'auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    // ... more routes
});
```

---

## Models Setup

### Understanding TenantAware Trait

The `TenantAware` trait automatically switches database connections based on tenancy state:

```php
use ArtflowStudio\Tenancy\Traits\TenantAware;

class User extends Authenticatable
{
    use TenantAware;  // Automatically uses 'tenant' connection when initialized
}
```

**How it works:**
- When `tenancy()->initialized` is `true` → Uses `'tenant'` connection
- When `tenancy()->initialized` is `false` → Uses default `'mysql'` connection (central)

### Models That Need TenantAware

Apply this trait to any model existing in both databases:

```php
// ✅ Models needing TenantAware
User::class              // Exists in central (app admin) and tenant (tenant admin)
Customer::class         // Exists only in tenant
Product::class          // Exists only in tenant

// ❌ Models NOT needing TenantAware
Setting::class          // Only in central
Config::class           // Only in central
```

### Example: Multi-Database Model Setup

```php
<?php
namespace App\Models;

use ArtflowStudio\Tenancy\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use TenantAware;  // This model exists in tenant database
    
    protected $fillable = ['name', 'email', 'phone'];
}
```

---

## Authentication Setup

### 1. Password Hashing in Seeders

**⚠️ CRITICAL:** Always hash passwords in seeders:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ CORRECT - Hash the password
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456'),  // ← Hash it!
        ]);

        // ❌ WRONG - Never store plain text
        // User::create([
        //     'email' => 'admin@example.com',
        //     'password' => '123456',  // ← Bad!
        // ]);
    }
}
```

### 2. Login Component with TenantAware

With the `TenantAware` trait applied to the User model, login components automatically use the correct connection:

```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        // The User model automatically queries the correct database
        // thanks to the TenantAware trait!
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        session()->regenerate();
        $this->redirect(route('dashboard'));
    }
}
```

---

## Livewire Integration

### Why Livewire Needs Special Handling

Livewire's `_livewire/update` and `_livewire/call` endpoints don't apply route middleware before calling component methods. This means:

1. **Regular HTTP requests** → Route middleware runs first → Tenancy initialized ✅
2. **Livewire form submissions** → Component method runs → Tenancy NOT initialized ❌

### Automatic Tenancy Bootstrap (Package-Level)

The package automatically handles this via the Livewire hook in `TenancyServiceProvider`:

```php
protected function configureLivewire(): void
{
    if (class_exists(Livewire::class)) {
        $this->app->booted(function () {
            // ... register components ...
            
            // Automatically bootstrap tenancy for Livewire requests
            if (request()->path() === '_livewire/update' || request()->path() === '_livewire/call') {
                \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
            }
        });
    }
}
```

**Result:** You don't need to manually initialize tenancy in your components! ✅

### Example: Livewire Component (No Manual Tenancy Needed)

```php
<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;

class UserList extends Component
{
    public function mount()
    {
        // Tenancy is AUTOMATICALLY initialized by the package
        // No manual initialization needed!
        
        // This automatically queries the correct tenant database
        $this->users = User::all();
    }

    public function deleteUser($userId)
    {
        // Again, automatically uses correct tenant database
        User::find($userId)->delete();
    }

    public function render()
    {
        return view('livewire.admin.user-list');
    }
}
```

---

## Session & Cache Configuration

### Important: Session & Cache Tables

Session and cache tables **MUST use the central database**, not tenant databases:

```env
# ✅ Correct - Use central database for sessions/cache
SESSION_DRIVER=database
CACHE_DRIVER=database
QUEUE_CONNECTION=database

# ❌ Don't use tenant database for sessions
# This breaks multi-tenancy!
```

### Session Configuration

**`config/session.php`:**

```php
return [
    // Use central database for sessions
    'driver' => env('SESSION_DRIVER', 'database'),
    
    // Important: Sessions table must be in CENTRAL database
    'table' => 'sessions',
    
    // Lifetime in minutes
    'lifetime' => 525600,  // or use env('SESSION_LIFETIME', 525600)
    
    // Prevent sessions from being shared across tenants
    'expire_on_close' => false,
    'encrypt' => false,
];
```

### Cache Configuration

**`config/cache.php`:**

```php
return [
    // Use central database for cache
    'default' => env('CACHE_DRIVER', 'database'),
    
    'stores' => [
        'database' => [
            'driver' => 'database',
            // Must use CENTRAL database
            'connection' => 'mysql',  // NOT 'tenant'
            'table' => 'cache',
            'prefix' => 'laravel_cache:',
        ],
    ],
];
```

### Why This Matters

- **Sessions table**: Stores authenticated user info after login
- **Cache table**: Stores cached data across requests
- **Jobs table**: Queued jobs need to be accessible globally

All three **MUST** use the central database for multi-tenancy to work correctly.

### Queue Configuration

**`config/queue.php`:**

```php
return [
    'default' => env('QUEUE_CONNECTION', 'database'),
    
    'connections' => [
        'database' => [
            'driver' => 'database',
            // Must use CENTRAL database
            'connection' => 'mysql',  // NOT 'tenant'
            'table' => 'jobs',
            'retry_after' => 90,
            'after_commit' => false,
        ],
    ],
];
```

---

## Migration & Seeding

### Create Migrations for Tenants

```bash
# Create migration for tenant database
php artisan make:migration create_users_table
```

**`database/migrations/xxxx_xx_xx_xxxxxx_create_users_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### Seed Tenant Database

```bash
# Run migrations and seed for tenant
php artisan tenant:db migrate:fresh --seed --domain="tenant1.local" --force
```

**`database/seeders/TenantDatabaseSeeder.php`:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Hash passwords!
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456'),
        ]);

        $this->call([
            // Other seeders...
        ]);
    }
}
```

---

## Common Issues & Solutions

### Issue 1: "These credentials do not match our records"

**Cause:** Password not hashed in seeder or User model not using TenantAware trait

**Solution:**
1. Add `use TenantAware` to User model
2. Hash passwords in seeder: `'password' => Hash::make('123456')`
3. Re-seed: `php artisan tenant:db migrate:fresh --seed --domain="tenant1.local" --force`

### Issue 2: User loads from wrong database (central instead of tenant)

**Cause:** User model doesn't have TenantAware trait

**Solution:**
```php
class User extends Authenticatable
{
    use TenantAware;  // ← Add this
}
```

### Issue 3: 403 "User does not have the right roles" after login

**Cause:** Role check happens before user is loaded from correct database

**Solution:**
1. Ensure User model has `use TenantAware`
2. Check middleware order: `['tenant.web', 'auth', 'role:admin']`
3. Verify roles are seeded in tenant database

### Issue 4: Livewire form submission fails silently

**Cause:** Tenancy not initialized for Livewire requests

**Solution:**
- The package automatically handles this via TenancyBootstrapperHook
- No manual code needed!
- Just ensure `TenantAware` trait is on User model

### Issue 5: Sessions shared across tenants

**Cause:** Session/cache driver using tenant database

**Solution:**
1. Set `SESSION_DRIVER=database` (use central database)
2. Set `CACHE_DRIVER=database` (use central database)
3. Never set these to use 'tenant' connection

---

## Checklist: Complete Setup

- [ ] Package installed: `composer require artflow-studio/tenancy`
- [ ] Package published: `php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider"`
- [ ] Migrations run: `php artisan migrate`
- [ ] User model has `use TenantAware` trait
- [ ] Other tenant models have `use TenantAware` trait
- [ ] Passwords hashed in seeders with `Hash::make()`
- [ ] Session driver set to `database` (central)
- [ ] Cache driver set to `database` (central)
- [ ] Queue driver set to `database` (central)
- [ ] Routes use proper middleware: `['tenant.web', 'auth']`
- [ ] Tenant database seeded: `php artisan tenant:db migrate:fresh --seed`
- [ ] Login tested in browser
- [ ] Livewire components tested

---

## Additional Resources

- [Stancl/Tenancy Docs](https://tenancyforlaravel.com/)
- [Package Repository](https://github.com/artflow-studio/tenancy)
- [Installation Troubleshooting](./installation/INSTALLATION_TROUBLESHOOTING.md)
- [Architecture Overview](./architecture/)

---

**Last Updated:** 2025-11-06
**Package Version:** 1.0.0+
**Laravel Version:** 12+
**PHP Version:** 8.2+
