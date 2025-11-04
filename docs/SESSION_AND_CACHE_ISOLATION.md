# Session & Cache Isolation Architecture

## Problem Statement

In multi-tenant applications, you need to ensure:
1. Each tenant's sessions are isolated from others
2. Each tenant's cache is isolated from others
3. Central app sessions/cache remain separate
4. No sessions/cache from one tenant leak to another

## Solution: Dynamic Bootstrappers

Artflow Tenancy uses **bootstrappers** to dynamically reconfigure sessions and cache when tenancy is initialized.

## Session Isolation Flow

### Flow Diagram

```
Request comes in
        ↓
Identify tenant (via domain middleware)
        ↓
Initialize tenancy
        ↓
SessionTenancyBootstrapper::bootstrap() runs
        ↓
Reconfigure session driver to use 'tenant' connection
        ↓
All session reads/writes now hit tenant's database
```

### Code Walkthrough

#### 1. Before Tenancy Initialization

```
config('session.driver')     → 'database' (central database)
config('session.connection') → 'default'
config('session.table')      → 'sessions'

Database: central_db.sessions
```

#### 2. SessionTenancyBootstrapper::bootstrap() Runs

```php
// In vendor/artflow-studio/tenancy/src/Bootstrappers/SessionTenancyBootstrapper.php

public function bootstrap(Tenant $tenant)
{
    // Store original config (for later revert)
    $this->originalDriver = config('session.driver');
    $this->originalConnection = config('session.connection');
    $this->originalTable = config('session.table');

    // Switch to tenant connection
    config([
        'session.connection' => 'tenant',
        'session.table' => config('artflow-tenancy.session.table', 'sessions'),
    ]);

    // Clear instances so Laravel rebuilds with new config
    app()->forgetInstance('session.store');
    app()->forgetInstance('session');
}
```

#### 3. After Tenancy Initialization

```
config('session.driver')     → 'database' (still database)
config('session.connection') → 'tenant'
config('session.table')      → 'sessions'

Database: tenant_1_db.sessions ✅
```

#### 4. Request Completes

```php
public function revert()
{
    // Restore original config
    config([
        'session.driver' => $this->originalDriver,
        'session.connection' => $this->originalConnection,
        'session.table' => $this->originalTable,
    ]);

    // Clear instances so Laravel rebuilds with central config
    app()->forgetInstance('session.store');
    app()->forgetInstance('session');
}
```

## Cache Isolation Flow

### Single Isolation Mode: Database

```
Before:
config('cache.default')                    → 'database'
config('cache.stores.database.connection') → 'default'
config('cache.stores.database.table')      → 'cache'

Database: central_db.cache

                    ↓ EnhancedCacheTenancyBootstrapper::bootstrap()

After:
config('cache.default')                    → 'database'
config('cache.stores.database.connection') → 'tenant'
config('cache.stores.database.table')      → 'cache'

Database: tenant_1_db.cache ✅
```

### Dual Isolation Mode: Prefix

```
Before:
config('cache.prefix') → ''
config('cache.stores.database.table') → 'cache'

Database:
- key: 'user_profile' → value

                    ↓ EnhancedCacheTenancyBootstrapper::bootstrap()

After:
config('cache.prefix') → 'tenant_1_'
config('cache.stores.database.table') → 'cache'

Database:
- key: 'tenant_1_user_profile' → value ✅
- key: 'tenant_2_user_profile' → (not found - different tenant)
```

### Triple Isolation Mode: Tags (Redis)

```
Before:
cache()->remember('key', 3600, function() { ... })

Redis:
- key: 'key' → value

                    ↓ Redis tags isolation

After:
cache()->tags(['tenant:1'])->remember('key', 3600, fn() {...})

Redis:
- key: 'tenant:1:key' → value ✅
- Can flush by tag: Cache::tags(['tenant:1'])->flush()
```

## Database Connection Configuration

### Tenant Connection

```php
// config/database.php

'connections' => [
    'tenant' => [
        'driver' => 'mysql',
        'host' => env('TENANT_DB_HOST', 'localhost'),
        'port' => env('TENANT_DB_PORT', 3306),
        'database' => env('TENANT_DB_NAME'), // Dynamically set
        'username' => env('TENANT_DB_USERNAME'),
        'password' => env('TENANT_DB_PASSWORD'),
    ],
    'default' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
    ],
]
```

### Dynamically Set During Tenancy Initialization

```php
// In stancl/tenancy middleware:

$tenant->makeCurrent(); // This internally:
// 1. Calls DatabaseTenancyBootstrapper::bootstrap()
// 2. Sets config('database.connections.tenant.database') = $tenant_database_name
// 3. Creates new PDO connection
// 4. Calls our custom bootstrappers (session, cache)
```

## Complete Request Lifecycle

```
1. HTTP Request arrives (e.g., GET /dashboard)
   ↓
2. Middleware pipeline starts
   ↓
3. InitializeTenancyByDomain middleware runs
   ↓
4. Identifies tenant from domain
   ↓
5. Calls $tenant->makeCurrent()
   ↓
6. DatabaseTenancyBootstrapper::bootstrap() runs
   ├─ Sets database connection to tenant database
   ├─ Creates PDO connection
   └─ Returns
   ↓
7. SessionTenancyBootstrapper::bootstrap() runs
   ├─ Reconfigures session to use 'tenant' connection
   ├─ Clears session manager instances
   └─ Returns
   ↓
8. EnhancedCacheTenancyBootstrapper::bootstrap() runs
   ├─ Reconfigures cache based on isolation_mode
   ├─ Clears cache manager instances
   └─ Returns
   ↓
9. Request handler (Controller/Livewire) executes
   ├─ All database queries → tenant database ✅
   ├─ All sessions → tenant sessions table ✅
   ├─ All cache → tenant cache table/prefix ✅
   └─ Returns response
   ↓
10. Response middleware runs
   ↓
11. All bootstrappers revert()
   ├─ DatabaseTenancyBootstrapper::revert() → central database
   ├─ SessionTenancyBootstrapper::revert() → central sessions
   ├─ EnhancedCacheTenancyBootstrapper::revert() → central cache
   └─ Returns
   ↓
12. Response sent to client
```

## Testing Session/Cache Isolation

```php
public function test_sessions_are_isolated()
{
    $tenant1 = Tenant::create(['name' => 'Tenant 1']);
    $tenant2 = Tenant::create(['name' => 'Tenant 2']);

    // Request as Tenant 1
    tenancy()->initialize($tenant1);
    session()->put('user_id', 1);
    $this->assertEquals(1, session('user_id'));

    // Switch to Tenant 2
    tenancy()->end();
    tenancy()->initialize($tenant2);
    session()->put('user_id', 2);
    $this->assertEquals(2, session('user_id'));

    // Back to Tenant 1 - should have original value
    tenancy()->end();
    tenancy()->initialize($tenant1);
    $this->assertEquals(1, session('user_id')); ✅
}
```

## Configuration Reference

### Session Configuration

```php
// config/artflow-tenancy.php
'session' => [
    'table' => env('TENANT_SESSION_TABLE', 'sessions'),
    'connection' => 'tenant',
],

// config/session.php
'driver' => env('SESSION_DRIVER', 'database'), // Must be 'database'
'connection' => null, // Will be set dynamically
'table' => env('SESSION_TABLE', 'sessions'),
```

### Cache Configuration

```php
// config/artflow-tenancy.php
'cache' => [
    'isolation_mode' => env('TENANT_CACHE_ISOLATION_MODE', 'database'),
    'table' => env('TENANT_CACHE_TABLE', 'cache'),
    'connection' => 'tenant',
    'prefix_pattern' => env('TENANT_CACHE_PREFIX_PATTERN', 'tenant_{tenant_id}_'),
],

// config/cache.php
'default' => env('CACHE_DRIVER', 'database'),
'stores' => [
    'database' => [
        'driver' => 'database',
        'connection' => null, // Will be set dynamically
        'table' => env('CACHE_TABLE', 'cache'),
    ],
],
```

## Performance Considerations

| Aspect | Database | Prefix | Tags |
|--------|----------|--------|------|
| **Storage** | Separate tables | Single table | Redis |
| **Isolation** | Strong | Strong | Strong |
| **Performance** | Good | Best | Best (Redis) |
| **Query Count** | Lower | Same | Varies |
| **Flush All** | SQL DELETE | Prefix LIKE | Tag FLUSH |

## Best Practices

1. **Always use 'database' sessions** for per-tenant isolation
2. **Use 'database' cache mode** for stability (avoid Redis dependency)
3. **Use 'prefix' mode** if all tenants share Redis
4. **Use 'tags' mode** for advanced cache control
5. **Test session/cache isolation** in your test suite
6. **Monitor database sizes** - each tenant gets separate tables

---

**This architecture ensures zero session/cache leakage between tenants! 🔒**
