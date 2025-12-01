# ArtFlow Studio Tenancy - Improvements & Fixes Required

## Package Overview

**ArtFlow Studio Tenancy** is built on top of `stancl/tenancy` v3.9.1+ and extends it with enterprise-grade features for Laravel multi-tenancy applications. The package provides enhanced CLI commands, performance monitoring, universal routing capabilities, and comprehensive tenant management.

### Core Architecture
- **Base Package**: `stancl/tenancy` v3.9.1+
- **Laravel Support**: Laravel 10+ / 12+ (current implementation)
- **PHP Support**: PHP 8.0+
- **Enhanced Features**: CLI tools, monitoring, universal middleware, homepage management

## Current Implementation Analysis

### Homepage Route Handling

Currently, tenant homepage routing is handled through:

1. **Configuration**: `config/artflow-tenancy.php`
   ```php
   'homepage' => [
       'enabled' => env('TENANT_HOMEPAGE_ENABLED', true),
       'view_path' => env('TENANT_HOMEPAGE_VIEW_PATH', 'tenants'),
       'auto_create_directory' => env('TENANT_HOMEPAGE_AUTO_CREATE_DIR', true),
       'fallback_redirect' => env('TENANT_HOMEPAGE_FALLBACK_REDIRECT', '/login'),
   ]
   ```

2. **Current Route Structure**: 
   - **Central Domain Routes**: Handled in main `routes/web.php` with `tenant.web` middleware
   - **Tenant Routes**: Defined in `routes/tenant.php` using `stancl/tenancy` middleware
   - **View Structure**: Homepage views stored in `resources/views/tenants/{domain}/home.blade.php`

3. **Middleware System**:
   ```php
   'middleware' => [
       'universal' => ['universal.web'], // Routes for both central and tenant
       'central_only' => ['central.web'], // Central domains only
       'tenant_only' => ['tenant.web'], // Tenant domains only
       'ui' => ['web'], // UI routes
       'api' => ['api'], // API routes
   ]
   ```

### Current Issues Identified

## 1. Performance Issues - Cache & Session Database Usage

**CRITICAL ISSUE**: Cache and session tables are using the central database instead of tenant-specific databases.

### Problem Analysis:
- **Cache Configuration** (`config/cache.php`): Uses `'connection' => env('DB_CACHE_CONNECTION')` - defaults to central DB
- **Session Configuration** (`config/session.php`): Uses `'connection' => env('SESSION_CONNECTION')` - defaults to central DB
- **Performance Impact**: All tenants share the same cache/session storage, reducing isolation and performance

### Required Fix:
```php
// config/cache.php - Tenant-aware cache
'database' => [
    'driver' => 'database',
    'connection' => tenancy()->initialized ? 'tenant' : env('DB_CACHE_CONNECTION'),
    'table' => env('DB_CACHE_TABLE', 'cache'),
],

// config/session.php - Tenant-aware sessions
'connection' => tenancy()->initialized ? 'tenant' : env('SESSION_CONNECTION'),
```

### Recommendation:
- Implement tenant-aware cache and session bootstrapper
- Add configuration for per-tenant Redis databases with proper prefixing
- Update `stancl/tenancy` bootstrappers to handle cache/session isolation

## 2. Database Command Issues (`tenant:db`)

### Current Problems:

1. **Migration Output**: Shows generic "completed" message instead of detailed Laravel migration output
2. **No Progress Tracking**: Doesn't show individual migration progress like native Laravel commands
3. **Missing Migration Status**: Should display which migrations are pending/completed

### Current Implementation Issues:
```php
// Current in TenantDatabaseCommand.php
// Missing detailed output and progress tracking
$this->runOperation($operation, $tenant);
```

### Required Improvements:

#### A. Enhanced Migration Output
```php
private function runMigration($tenant): int
{
    return tenancy()->run($tenant, function () {
        // Capture and display real Laravel migration output
        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--verbose' => true
        ]);
        
        // Show actual migration files being processed
        $this->info(Artisan::output());
        return $exitCode;
    });
}
```

#### B. Individual Migration Progress
- Show each migration file as it's being processed
- Display timing information per migration
- Show rollback operations step-by-step
- Include memory usage and performance metrics

#### C. Migration Status Command
```php
php artisan tenant:db migrate:status --tenant=uuid
```
Should show:
- ✅ Completed migrations with timestamps
- ⏳ Pending migrations
- ❌ Failed migrations with error details
- 📊 Database statistics (table count, size, etc.)

## 3. Seeder Command Improvements

### Current Issues:
- No interactive seeder selection
- Limited seeder class options
- No progress tracking for large seeders

### Required Enhancements:

#### A. Interactive Seeder Selection
```bash
php artisan tenant:db seed --tenant=uuid

# Should display:
0. DatabaseSeeder (Default)
1. TenantDatabaseSeeder
2. AccountsTableSeeder
3. AirlinesTableSeeder
4. AirportsTableSeeder
5. CountriesTableSeeder
6. CustomSeeder

Select seeder to run [0-6]: 
```

#### B. Custom Seeder Class Support
```php
// Allow running specific seeder classes
php artisan tenant:db seed --class=AirlinesTableSeeder --tenant=uuid

// Allow running multiple seeders
php artisan tenant:db seed --class=AirlinesTableSeeder,AirportsTableSeeder --tenant=uuid
```

#### C. Seeder Progress Tracking
```php
private function runSeederWithProgress($seederClass, $tenant)
{
    $this->info("🌱 Running {$seederClass} for tenant {$tenant->name}");
    
    return tenancy()->run($tenant, function () use ($seederClass) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $exitCode = Artisan::call('db:seed', [
            '--class' => $seederClass,
            '--force' => true
        ]);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = $this->formatBytes(memory_get_usage() - $startMemory);
        
        if ($exitCode === 0) {
            $this->info("✅ {$seederClass} completed in {$duration}ms (Memory: {$memoryUsed})");
        } else {
            $this->error("❌ {$seederClass} failed");
        }
        
        return $exitCode;
    });
}
```

## 4. Homepage Route Handling Improvements

### Current Implementation Gap:

The current `routes/tenant.php` has a basic implementation:
```php
Route::get('/', function () {
    return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id');
});
```

### Required Implementation:

#### A. Dynamic Homepage Route Resolution
```php
// routes/tenant.php - Enhanced homepage handling
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    
    Route::get('/', function () {
        $tenant = tenant();
        
        // Check if tenant has homepage enabled
        if (!$tenant->homepage_enabled) {
            return redirect(config('artflow-tenancy.homepage.fallback_redirect', '/login'));
        }
        
        // Try to find tenant-specific homepage view
        $homepageView = "tenants.{$tenant->domains->first()->domain}.home";
        
        if (view()->exists($homepageView)) {
            return view($homepageView, compact('tenant'));
        }
        
        // Fallback to default tenant homepage
        $fallbackView = "tenants.default.home";
        if (view()->exists($fallbackView)) {
            return view($fallbackView, compact('tenant'));
        }
        
        // Final fallback - redirect to dashboard or login
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }
        
        return redirect()->route('login');
    })->name('tenant.homepage');
});
```

#### B. Homepage Management Commands
```php
// Enhanced tenant creation with homepage
php artisan tenant:create --with-homepage

// Enable/disable homepage for existing tenant
php artisan tenant:manage enable-homepage --tenant=uuid
php artisan tenant:manage disable-homepage --tenant=uuid

// Create homepage view for tenant
php artisan tenant:homepage:create --tenant=uuid --domain=example.com
```

#### C. Automatic Homepage View Creation

When creating a tenant with homepage enabled:
```php
// In tenant creation job pipeline
Jobs\CreateTenantHomepage::class,
```

This job should:
1. Create view directory: `resources/views/tenants/{domain}/`
2. Copy template homepage: `home.blade.php`
3. Update tenant model with homepage settings
4. Set up appropriate routing

## 5. Additional Performance Improvements

### A. Connection Pool Optimization
```php
// config/database.php - Tenant connection template
'tenant_template' => [
    'options' => [
        PDO::ATTR_PERSISTENT => false, // CRITICAL for multi-tenancy
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION wait_timeout=120',
    ],
],
```

### B. Redis Tenant Isolation
```php
// Implement proper Redis tenant isolation
'redis' => [
    'per_tenant_database' => true,
    'database_offset' => 10,
    'prefix_pattern' => 'tenant_{tenant_id}_',
    'central_prefix' => 'central_',
],
```

### C. Queue Tenant Awareness
```php
// Ensure queue jobs are tenant-aware
'queue' => [
    'tenant_aware' => true,
    'default_queue' => 'tenant_{tenant_id}',
],
```

## 6. Command Structure Improvements

### A. Unified Command Interface
```bash
# Current scattered commands should be unified
php artisan tenant:create     # Create tenant
php artisan tenant:manage     # Manage tenant (status, activate, etc.)
php artisan tenant:db         # Database operations (migrate, seed, rollback)
php artisan tenant:homepage   # Homepage management
php artisan tenant:backup     # Backup operations
php artisan tenant:monitor    # Health monitoring
```

### B. Enhanced Progress Reporting
All commands should include:
- Real-time progress bars
- Detailed status messages
- Performance metrics (time, memory)
- Error handling with suggestions
- Colored output for better UX

## Implementation Priority

### High Priority (Performance Critical)
1. **Fix cache/session database isolation** - Critical for performance and security
2. **Enhance migration command output** - Essential for debugging and monitoring
3. **Implement proper Redis tenant isolation** - Required for scalability

### Medium Priority (User Experience)
4. **Interactive seeder selection** - Improves developer experience
5. **Enhanced homepage route handling** - Better tenant management
6. **Command output improvements** - Better debugging and monitoring

### Low Priority (Nice to Have)
7. **Advanced backup features** - Operational improvements
8. **Enhanced monitoring dashboards** - Operational insights
9. **Automated performance optimization** - Long-term scalability

## Testing Requirements

Each improvement should include:
1. **Unit Tests** for core functionality
2. **Feature Tests** for command interactions  
3. **Performance Tests** for tenant isolation
4. **Integration Tests** with stancl/tenancy
5. **Load Tests** for multi-tenant scenarios

## Migration Path

1. **Phase 1**: Fix critical performance issues (cache/session)
2. **Phase 2**: Enhance command interfaces and output
3. **Phase 3**: Implement advanced features and monitoring
4. **Phase 4**: Optimize performance and add advanced features

---

**Note**: All improvements should maintain backward compatibility with existing `stancl/tenancy` features while extending functionality in a clean, maintainable way.