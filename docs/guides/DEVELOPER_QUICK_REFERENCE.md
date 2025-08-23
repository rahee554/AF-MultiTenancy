# AF-MultiTenancy: Developer Quick Reference

## Quick Setup

```bash
# Install package
composer require artflow-studio/af-multitenancy

# Publish config
php artisan vendor:publish --tag=tenancy-config

# Run tests
php artisan tenancy:test-comprehensive --verbose
```

## Essential Commands

### Testing
```bash
# Test all features
php artisan tenancy:test-comprehensive --tenant=TENANT_ID

# Test specific features
php artisan tenancy:test-cached-lookup --tenant=TENANT_ID
php artisan tenancy:test-sanctum --tenant=TENANT_ID

# Performance testing
php artisan tenancy:test-comprehensive --performance
```

### Maintenance Mode
```bash
# Enable maintenance
php artisan tenants:maintenance enable --tenant=TENANT_ID --message="Under maintenance"

# Enable with IP whitelist
php artisan tenants:maintenance enable --tenant=TENANT_ID --allowed-ips=127.0.0.1,192.168.1.1

# Enable with bypass key
php artisan tenants:maintenance enable --tenant=TENANT_ID --bypass-key=secret123

# Disable maintenance
php artisan tenants:maintenance disable --tenant=TENANT_ID

# Check status
php artisan tenants:maintenance status --tenant=TENANT_ID

# List all in maintenance
php artisan tenants:maintenance list
```

### Cache Management
```bash
# Warm up tenant cache
php artisan tenancy:warm-cache

# Clear tenant cache
php artisan cache:clear
```

## Service Injection

### Cached Tenant Resolver
```php
use ArtflowStudio\Tenancy\Services\CachedTenantResolver;

class YourController extends Controller
{
    public function __construct(
        private CachedTenantResolver $tenantResolver
    ) {}
    
    public function resolve(string $domain)
    {
        $tenant = $this->tenantResolver->resolve($domain);
        $stats = $this->tenantResolver->getCacheStats();
        
        return response()->json([
            'tenant' => $tenant,
            'cache_stats' => $stats
        ]);
    }
}
```

### Maintenance Mode Service
```php
use ArtflowStudio\Tenancy\Services\TenantMaintenanceMode;

class MaintenanceController extends Controller
{
    public function __construct(
        private TenantMaintenanceMode $maintenance
    ) {}
    
    public function enable(Request $request)
    {
        $this->maintenance->enableForTenant(
            $request->tenant_id,
            [
                'message' => $request->message,
                'allowed_ips' => $request->allowed_ips ?? [],
                'bypass_key' => $request->bypass_key,
                'retry_after' => $request->retry_after ?? 3600,
            ]
        );
        
        return response()->json(['status' => 'enabled']);
    }
}
```

### Sanctum Service
```php
use ArtflowStudio\Tenancy\Services\TenantSanctumService;

class ApiController extends Controller
{
    public function __construct(
        private TenantSanctumService $sanctum
    ) {}
    
    public function createToken(Request $request)
    {
        $this->sanctum->configureSanctumForTenant(tenant());
        
        $token = $this->sanctum->createTenantToken(
            $request->user(),
            'API Token',
            ['read', 'write']
        );
        
        return response()->json(['token' => $token]);
    }
    
    public function tokenStats()
    {
        $stats = $this->sanctum->getTenantTokenStats(tenant());
        return response()->json($stats);
    }
}
```

## Configuration Patterns

### Basic tenancy.php
```php
return [
    'features' => [
        'cached_lookup' => true,
        'maintenance_mode' => true,
        'early_identification' => true,
        'sanctum_integration' => true,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'tenant_',
        'driver' => 'redis',
    ],
    
    'maintenance' => [
        'enabled' => true,
        'view' => 'tenancy::maintenance',
        'retry_after' => 3600,
    ],
];
```

### Middleware Setup
```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... Laravel defaults
        \ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware::class,
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ],
    
    'api' => [
        // ... Laravel defaults
        \ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware::class,
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        'auth:sanctum',
    ],
];

// Middleware aliases
protected $middlewareAliases = [
    'early-identification' => \ArtflowStudio\Tenancy\Http\Middleware\EarlyIdentificationMiddleware::class,
    'tenant' => \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
];
```

## Route Patterns

### Basic Tenant Routes
```php
// routes/web.php
Route::middleware(['early-identification', 'tenant'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### API Routes with Sanctum
```php
// routes/api.php
Route::middleware(['early-identification', 'tenant'])->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        
        Route::apiResource('posts', PostController::class);
    });
    
    Route::post('/login', [AuthController::class, 'login']);
});
```

## Helper Functions

### Current Tenant
```php
// Get current tenant
$tenant = tenant();

// Check if in tenant context
if (tenancy()->initialized) {
    // Tenant-specific logic
}

// Get tenant ID
$tenantId = tenant('id');
```

### Cache Helpers
```php
// Cache with tenant prefix
Cache::put("user_{$userId}", $userData, 3600);

// Tenant-specific cache key
$cacheKey = "tenant_" . tenant('id') . "_posts";
Cache::remember($cacheKey, 3600, function () {
    return Post::all();
});
```

## Error Handling

### Maintenance Mode Check
```php
use ArtflowStudio\Tenancy\Services\TenantMaintenanceMode;

public function handle(Request $request, Closure $next)
{
    $maintenance = app(TenantMaintenanceMode::class);
    
    if ($maintenance->isInMaintenanceMode(tenant('id'))) {
        if (!$maintenance->shouldBypassMaintenance(
            tenant('id'), 
            $request->ip(), 
            $request->get('bypass_key')
        )) {
            return $maintenance->generateMaintenanceResponse(tenant('id'));
        }
    }
    
    return $next($request);
}
```

### Tenant Not Found
```php
// In your exception handler
public function render($request, Throwable $exception)
{
    if ($exception instanceof TenantCouldNotBeIdentifiedException) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant not found',
                'code' => 'TENANT_NOT_FOUND'
            ], 404);
        }
        
        return response()->view('errors.tenant-not-found', [], 404);
    }
    
    return parent::render($request, $exception);
}
```

## Performance Tips

### 1. Cache Optimization
```php
// Use Redis for better performance
'cache' => [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
    ],
],
```

### 2. Database Optimization
```php
// Use persistent connections
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => true,
    ],
],
```

### 3. Early Identification
```php
// Warm up cache in a scheduled command
class WarmTenantCache extends Command
{
    public function handle()
    {
        $middleware = app(EarlyIdentificationMiddleware::class);
        $warmedCount = $middleware->warmUpIdentificationCache();
        
        $this->info("Warmed {$warmedCount} tenant domains");
    }
}
```

## Debugging

### Enable Debug Logging
```php
// config/logging.php
'channels' => [
    'tenancy' => [
        'driver' => 'daily',
        'path' => storage_path('logs/tenancy.log'),
        'level' => 'debug',
    ],
],

// In your service
Log::channel('tenancy')->debug('Tenant resolved', [
    'tenant_id' => $tenant->id,
    'domain' => $domain,
    'cache_hit' => $cacheHit,
]);
```

### Test with Verbose Output
```bash
# See detailed test output
php artisan tenancy:test-comprehensive --tenant=example --verbose

# Monitor cache performance
php artisan tenancy:test-cached-lookup --tenant=example --iterations=100
```

## Common Patterns

### Service Layer Pattern
```php
class TenantAwareService
{
    public function getData()
    {
        $tenantId = tenant('id');
        
        return Cache::remember("tenant_{$tenantId}_data", 3600, function () {
            return $this->fetchFromDatabase();
        });
    }
    
    private function fetchFromDatabase()
    {
        // Tenant-scoped database query
        return Model::where('tenant_id', tenant('id'))->get();
    }
}
```

### Repository Pattern
```php
class TenantRepository
{
    public function findById(string $id): ?Tenant
    {
        $resolver = app(CachedTenantResolver::class);
        return $resolver->resolve($id);
    }
    
    public function isInMaintenance(string $tenantId): bool
    {
        $maintenance = app(TenantMaintenanceMode::class);
        return $maintenance->isInMaintenanceMode($tenantId);
    }
}
```

---

**Quick Commands Reference:**
- Test: `php artisan tenancy:test-comprehensive --tenant=ID`
- Maintenance: `php artisan tenants:maintenance COMMAND --tenant=ID`
- Cache: `php artisan tenancy:warm-cache`
- Debug: Add `--verbose` to any command
