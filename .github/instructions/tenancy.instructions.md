# 🏢 AF-MultiTenancy Package - AI Agent Instructions

**Version: 2.0** | **Date: December 2024** | **Package Health: 9.5/10**

> **CRITICAL**: This is a comprehensive guide for AI agents working with the AF-MultiTenancy package. Read this document in its entirety before making any changes to ensure proper understanding of the system architecture, security requirements, and best practices.

---

## 📋 Table of Contents

1. [Project Overview & Stack](#project-overview--stack)
2. [Architecture & Core Components](#architecture--core-components)
3. [Security Guidelines](#security-guidelines)
4. [Development Standards](#development-standards)
5. [Database Management](#database-management)
6. [Testing & Quality Assurance](#testing--quality-assurance)
7. [Data Protection & Privacy](#data-protection--privacy)
8. [Performance Guidelines](#performance-guidelines)
9. [Troubleshooting & Debugging](#troubleshooting--debugging)
10. [Command Reference](#command-reference)
11. [API Guidelines](#api-guidelines)
12. [Maintenance & Operations](#maintenance--operations)

---

## 🎯 Project Overview & Stack

### **What This Package Is**
AF-MultiTenancy is an **enterprise-grade Laravel multi-tenancy package** that extends `stancl/tenancy` with advanced features, comprehensive CLI tools, real-time monitoring, and enhanced security. It provides complete database isolation, status management, and performance optimization for SaaS applications.

### **Technology Stack**
```yaml
Core Framework:
  - Laravel: 10.x / 11.x (Primary framework)
  - PHP: 8.0+ (8.2+ recommended)
  - stancl/tenancy: 3.9.1+ (Foundation package - NEVER replace)

Database:
  - MySQL: 8.0+ (Primary)
  - MariaDB: 10.4+ (Supported)
  - Multi-tenant database architecture (separate DB per tenant)

Frontend & UI:
  - Livewire: 3.x (Real-time components)
  - Blade Templates (Admin interface)
  - Responsive CSS (Mobile-first design)

Performance & Caching:
  - Redis: 6.x+ (Recommended for caching)
  - Database caching (Fallback)
  - Multi-layer tenant context caching

Integration:
  - FastPanel: Hosting integration (Optional)
  - Laravel Sanctum: API authentication
  - REST API: Complete tenant management

Additional Tools:
  - 30+ Artisan commands
  - Real-time monitoring
  - Comprehensive testing suite
```

### **Package Philosophy**
1. **Built ON stancl/tenancy** - We extend, never replace core functionality
2. **Security First** - Complete tenant isolation is paramount
3. **Performance Optimized** - Sub-25ms tenant switching
4. **Developer Experience** - Zero-config setup with sensible defaults
5. **Production Ready** - Battle-tested with 100+ concurrent tenants

---

## 🏗️ Architecture & Core Components

### **System Architecture Overview**
```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Request                             │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                Middleware Stack                             │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐│
│  │   Smart     │ │   Tenant    │ │    Homepage             ││
│  │   Domain    │ │ Resolution  │ │   Redirect              ││
│  │  Resolver   │ │             │ │                         ││
│  └─────────────┘ └─────────────┘ └─────────────────────────┘│
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                Service Layer                                │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐│
│  │   Tenant    │ │  Database   │ │       Cache             ││
│  │  Service    │ │  Manager    │ │     Service             ││
│  └─────────────┘ └─────────────┘ └─────────────────────────┘│
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                 stancl/tenancy Core                         │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐│
│  │   Domain    │ │  Database   │ │     Event               ││
│  │ Resolution  │ │  Switching  │ │    System               ││
│  └─────────────┘ └─────────────┘ └─────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

### **Core Components & Their Roles**

#### **1. TenancyServiceProvider.php** ⚡
**Purpose**: Main entry point that orchestrates the entire package
**Key Responsibilities**:
- Registers stancl/tenancy service provider FIRST (CRITICAL)
- Configures middleware groups with proper ordering
- Registers 30+ Artisan commands
- Sets up Livewire integration with session scoping
- Configures Redis multi-database support

**CRITICAL**: When modifying this file:
```php
// ✅ ALWAYS register stancl/tenancy first
public function register(): void
{
    $this->app->register(\Stancl\Tenancy\TenancyServiceProvider::class);
    // Then register our services...
}
```

#### **2. Middleware Stack** 🛡️
**Purpose**: Handle request routing between central and tenant domains

**Critical Middleware Groups**:
```php
// ✅ For tenant routes (CRITICAL ORDER):
'tenant.web' => [
    'web',                    // Laravel web middleware
    'tenant',                 // stancl/tenancy initialization
    'tenant.prevent-central', // Block central domain access
    'tenant.scope-sessions',  // Session isolation (REQUIRED for Livewire)
],

// ✅ For universal routes (work on both central and tenant):
'universal.web' => [
    'web',
    UniversalWebMiddleware::class, // Tries tenant initialization, falls back gracefully
],

// ✅ For central-only routes (admin interface):
'central.web' => [
    'web',
    'central', // Blocks tenant domains
],
```

#### **3. Service Layer** 🔧
**Key Services**:
- `TenantService`: Core tenant operations (create, delete, migrate)
- `TenantContextCache`: Multi-layer caching system
- `TenantAnalyticsService`: Performance monitoring and analytics
- `TenantBackupService`: Database backup and restore operations

#### **4. Model Layer** 📊
**Enhanced Models**:
- `Tenant`: Extends stancl/tenancy model with status management, homepage control
- `Domain`: Enhanced domain model with validation and caching

---

## 🔐 Security Guidelines

### **CRITICAL Security Rules**

#### **1. Tenant Isolation (ABSOLUTE REQUIREMENT)**
```php
// ✅ ALWAYS verify tenant context before database operations
if (!tenancy()->initialized) {
    throw new TenantNotInitializedException();
}

// ✅ NEVER perform direct database operations without tenant context
// ❌ BAD:
DB::table('users')->get(); // Could access wrong tenant data

// ✅ GOOD:
if (tenant()) {
    tenant()->run(function () {
        return DB::table('users')->get(); // Properly scoped
    });
}
```

#### **2. API Security**
```php
// ✅ ALWAYS validate API keys
if (!$this->validateApiKey($request)) {
    return response()->json(['error' => 'Unauthorized'], 401);
}

// ✅ ALWAYS rate limit API endpoints
Route::middleware(['throttle:60,1'])->group(function () {
    // API routes here
});
```

#### **3. Input Validation**
```php
// ✅ ALWAYS validate and sanitize input
$request->validate([
    'name' => 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-_]+$/',
    'domain' => 'required|string|max:255|regex:/^[a-zA-Z0-9\-\.]+$/',
    'status' => 'required|in:active,inactive,suspended,blocked',
]);
```

#### **4. Database Security**
```php
// ✅ ALWAYS use parameterized queries
DB::table('tenants')->where('id', '=', $tenantId)->get();

// ❌ NEVER use raw SQL with user input
DB::raw("SELECT * FROM tenants WHERE id = '$tenantId'"); // SQL injection risk
```

### **Status-Based Access Control**
```php
// ✅ ALWAYS check tenant status before allowing access
public function handle($request, Closure $next)
{
    if (tenant() && tenant()->status !== 'active') {
        return $this->handleInactiveTenant(tenant());
    }
    return $next($request);
}
```

---

## 💻 Development Standards

### **Code Quality Standards**

#### **1. PSR-12 Compliance**
- Use 4 spaces for indentation (no tabs)
- Keep lines under 120 characters
- Use proper namespacing and imports
- Follow Laravel naming conventions

#### **2. Documentation Requirements**
```php
/**
 * Create a new tenant with validation and database setup
 *
 * @param string $name Tenant display name
 * @param string $domain Primary domain for tenant
 * @param string $status Tenant status (active, inactive, suspended, blocked)
 * @param string|null $databaseName Custom database name (auto-generated if null)
 * @param bool $runMigrations Whether to run migrations after creation
 * @return Tenant Created tenant instance
 * @throws TenantCreationException If tenant creation fails
 * @throws DatabaseCreationException If database creation fails
 */
public function createTenant(
    string $name,
    string $domain,
    string $status = 'active',
    ?string $databaseName = null,
    bool $runMigrations = false
): Tenant {
    // Implementation...
}
```

#### **3. Error Handling**
```php
// ✅ ALWAYS use try-catch for database operations
try {
    $tenant = $this->createTenantDatabase($databaseName);
    $this->runMigrations($tenant);
} catch (DatabaseException $e) {
    $this->cleanupFailedTenant($tenant);
    throw new TenantCreationException("Failed to create tenant: " . $e->getMessage(), 0, $e);
}

// ✅ ALWAYS log errors with context
Log::error('Tenant creation failed', [
    'tenant_name' => $name,
    'domain' => $domain,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

#### **4. Method Naming & Structure**
```php
// ✅ Use descriptive method names
public function createTenantWithValidation() // ✅ Clear purpose
public function create() // ❌ Too generic

// ✅ Keep methods focused (Single Responsibility)
public function createTenant() { /* Only create tenant */ }
public function validateTenantData() { /* Only validate */ }
public function setupTenantDatabase() { /* Only setup database */ }
```

### **Testing Requirements**

#### **1. Test Coverage**
- All public methods must have unit tests
- All API endpoints must have integration tests
- All commands must have feature tests
- Critical security features must have specific security tests

#### **2. Test Structure**
```php
class TenantServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('tenancy:install'); // Setup test environment
    }

    /** @test */
    public function it_creates_tenant_with_valid_data()
    {
        $tenantData = [
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'status' => 'active',
        ];

        $tenant = $this->tenantService->createTenant(...$tenantData);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Tenant', $tenant->name);
        $this->assertTrue($this->databaseExists($tenant->database));
    }

    /** @test */
    public function it_throws_exception_for_duplicate_domain()
    {
        $this->expectException(DuplicateDomainException::class);
        
        $this->tenantService->createTenant('Tenant 1', 'test.example.com');
        $this->tenantService->createTenant('Tenant 2', 'test.example.com'); // Should fail
    }
}
```

---

## 🗄️ Database Management

### **Database Architecture**
```
Central Database (Primary):
├── tenants (Tenant metadata)
├── domains (Domain mappings)
├── migrations (Migration tracking)
└── cache (System cache)

Tenant Databases (Per tenant):
├── users (Tenant users)
├── [custom_app_tables]
├── migrations (Tenant migration tracking)
└── cache (Tenant-specific cache)
```

### **Database Naming Convention**
```php
// ✅ Standard naming pattern
$databaseName = 'tenant_' . $tenant->uuid; // tenant_1a2b3c4d...

// ✅ Custom naming with validation
$databaseName = $this->validateDatabaseName($customName);
$databaseName = config('artflow-tenancy.database.prefix') . $databaseName;
```

### **Migration Management**
```php
// ✅ ALWAYS run migrations within tenant context
public function migrateTenant(Tenant $tenant): void
{
    tenancy()->initialize($tenant);
    
    try {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
        ]);
    } finally {
        tenancy()->end(); // ALWAYS clean up
    }
}
```

### **Database Connection Management**
```php
// ✅ Use proper connection switching
DB::purge('tenant'); // Clear old connections
Config::set('database.connections.tenant.database', $tenant->database);
DB::reconnect('tenant');
```

---

## 🧪 Testing & Quality Assurance

### **Testing Philosophy**
1. **Test Early & Often** - Write tests before implementing features
2. **Test All Layers** - Unit, integration, and end-to-end tests
3. **Test Security** - Dedicated security test suites
4. **Test Performance** - Benchmark critical operations
5. **Test Edge Cases** - Error conditions and boundary cases

### **Available Testing Commands**
```bash
# ✅ Primary testing commands (run these frequently)
php artisan tenancy:validate              # Complete system validation
php artisan tenancy:test-system          # System component testing
php artisan tenancy:test-connections     # Database connectivity testing
php artisan tenancy:test-isolation       # Tenant isolation verification
php artisan tenancy:test-performance     # Performance benchmarking

# ✅ Stress testing (before production deployment)
php artisan tenancy:stress-test          # High-intensity load testing
php artisan tenancy:test-performance-enhanced --concurrent=10

# ✅ Component-specific testing
php artisan tenancy:test-redis           # Redis functionality testing
php artisan af-tenancy:test-middleware   # Middleware validation
php artisan tenancy:test-api             # API endpoint testing
```

### **Testing Best Practices**
```php
// ✅ ALWAYS test tenant isolation
public function test_tenant_data_isolation()
{
    $tenant1 = $this->createTenant('Tenant 1', 'tenant1.test');
    $tenant2 = $this->createTenant('Tenant 2', 'tenant2.test');
    
    tenancy()->initialize($tenant1);
    User::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    
    tenancy()->initialize($tenant2);
    $users = User::all();
    
    $this->assertCount(0, $users); // Should not see tenant1's data
}

// ✅ ALWAYS test error conditions
public function test_handles_invalid_tenant_gracefully()
{
    $this->expectException(TenantNotFoundException::class);
    $this->tenantService->getTenant('invalid-uuid');
}
```

### **Performance Testing Guidelines**
```php
// ✅ Test critical performance metrics
public function test_tenant_switching_performance()
{
    $tenants = $this->createMultipleTenants(10);
    
    $startTime = microtime(true);
    
    foreach ($tenants as $tenant) {
        tenancy()->initialize($tenant);
        $this->assertNotNull(tenant());
        tenancy()->end();
    }
    
    $totalTime = microtime(true) - $startTime;
    $averageTime = ($totalTime / count($tenants)) * 1000; // ms
    
    $this->assertLessThan(25, $averageTime, 'Tenant switching should be under 25ms');
}
```

---

## 🛡️ Data Protection & Privacy

### **Data Isolation Requirements**
1. **Database Isolation** - Each tenant has its own database
2. **Session Isolation** - Sessions are scoped per tenant
3. **Cache Isolation** - Cache keys are tenant-prefixed
4. **File Isolation** - File storage is tenant-scoped
5. **Log Isolation** - Logs contain tenant context but no sensitive data

### **Privacy Guidelines**
```php
// ✅ NEVER log sensitive tenant data
Log::info('Tenant created', [
    'tenant_id' => $tenant->uuid,
    'domain' => $tenant->domain,
    // ❌ 'name' => $tenant->name, // Could contain sensitive info
]);

// ✅ Sanitize data before logging
Log::info('Database operation failed', [
    'tenant_id' => $tenant->uuid,
    'operation' => 'migration',
    'error_type' => get_class($exception),
    // ❌ 'error_message' => $exception->getMessage(), // Could leak info
]);
```

### **Data Retention**
```php
// ✅ Implement proper data cleanup
public function deleteTenant(Tenant $tenant): void
{
    // 1. Backup data (if required by policy)
    if (config('artflow-tenancy.backup_before_delete')) {
        $this->backupTenantData($tenant);
    }
    
    // 2. Delete tenant database
    $this->deleteTenantDatabase($tenant);
    
    // 3. Clear caches
    $this->clearTenantCache($tenant);
    
    // 4. Delete tenant record
    $tenant->delete();
    
    // 5. Log the deletion (without sensitive data)
    Log::info('Tenant deleted', ['tenant_id' => $tenant->uuid]);
}
```

---

## ⚡ Performance Guidelines

### **Performance Targets**
- **Tenant Switching**: < 25ms average
- **API Response**: < 100ms for CRUD operations
- **Database Queries**: < 50ms per query
- **Memory Usage**: < 50MB per tenant in memory
- **Concurrent Tenants**: 100+ simultaneous active tenants

### **Caching Strategy**
```php
// ✅ Use multi-layer caching
class TenantContextCache
{
    private $memoryCache = []; // L1: In-memory
    private $redisCache;       // L2: Redis
    private $databaseCache;    // L3: Database
    
    public function getTenant(string $domain): ?Tenant
    {
        // L1: Check memory cache
        if (isset($this->memoryCache[$domain])) {
            return $this->memoryCache[$domain];
        }
        
        // L2: Check Redis cache
        if ($cached = $this->redisCache->get("tenant:$domain")) {
            $this->memoryCache[$domain] = $cached;
            return $cached;
        }
        
        // L3: Database lookup
        $tenant = Tenant::whereHas('domains', function ($query) use ($domain) {
            $query->where('domain', $domain);
        })->first();
        
        if ($tenant) {
            $this->cacheInAllLayers($domain, $tenant);
        }
        
        return $tenant;
    }
}
```

### **Database Optimization**
```php
// ✅ Use connection pooling
public function initializeTenant(Tenant $tenant): void
{
    if ($this->isConnectionCached($tenant)) {
        $this->reuseCachedConnection($tenant);
    } else {
        $this->createNewConnection($tenant);
        $this->cacheConnection($tenant);
    }
}

// ✅ Optimize queries with indexes
Schema::table('tenants', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
    $table->index('last_accessed_at');
});
```

### **Memory Management**
```php
// ✅ Clean up resources
public function endTenantContext(): void
{
    // Clear tenant-specific caches
    $this->clearMemoryCache();
    
    // Reset database connections
    DB::purge('tenant');
    
    // Clear any tenant-specific services
    app()->forgetInstance(TenantSpecificService::class);
}
```

---

## 🔍 Troubleshooting & Debugging

### **Common Issues & Solutions**

#### **1. Tenant Identification Failed**
```php
// ✅ Use proper middleware ordering
'tenant.web' => [
    'web',                    // Must be first
    'tenant',                 // Then tenant initialization
    'tenant.prevent-central', // Then access control
    'tenant.scope-sessions',  // Then session scoping
],
```

#### **2. Database Connection Issues**
```bash
# ✅ Debug commands to run
php artisan tenancy:diagnose              # System diagnostics
php artisan af-tenancy:debug-connection   # Connection debugging
php artisan tenancy:test-connections      # Connection validation
```

#### **3. Middleware Registration Issues**
```php
// ✅ Check middleware registration in service provider
protected function registerMiddleware(): void
{
    $router = $this->app->make(Router::class);
    
    // Register all required middleware
    $router->aliasMiddleware('tenant', InitializeTenancyByDomain::class);
    $router->aliasMiddleware('tenant.scope-sessions', ScopeSessions::class);
    // ... register all middleware
}
```

#### **4. Livewire Session Issues**
```php
// ✅ Ensure proper session scoping
'tenant.web' => [
    'web',
    'tenant',
    'tenant.prevent-central',
    'tenant.scope-sessions', // CRITICAL for Livewire
],
```

### **Debugging Tools**
```php
// ✅ Use comprehensive logging
Log::debug('Tenant context debug', [
    'tenant_initialized' => tenancy()->initialized,
    'current_tenant' => tenant()?->uuid,
    'request_domain' => request()->getHost(),
    'middleware_stack' => request()->route()?->middleware() ?? [],
]);

// ✅ Use debug commands
if (app()->hasDebugModeEnabled()) {
    $this->line('Debug: Tenant UUID = ' . tenant()?->uuid);
    $this->line('Debug: Database = ' . tenant()?->database);
}
```

---

## 🎯 Command Reference

### **Essential Commands for AI Agents**
```bash
# ✅ ALWAYS validate system before making changes
php artisan tenancy:validate

# ✅ Test specific functionality after changes
php artisan tenancy:test-system           # System-wide validation
php artisan tenancy:test-connections      # Database connectivity
php artisan tenancy:test-isolation        # Data isolation
php artisan af-tenancy:test-middleware    # Middleware functionality

# ✅ Performance validation
php artisan tenancy:test-performance      # Performance benchmarks
php artisan tenancy:stress-test           # High-load testing

# ✅ Tenant management
php artisan tenant:create                 # Create new tenant
php artisan tenant:manage list            # List all tenants
php artisan tenant:manage status          # Check tenant status
php artisan tenant:manage health          # System health check

# ✅ Database operations
php artisan tenant:db migrate             # Run tenant migrations
php artisan tenant:db seed                # Seed tenant databases
php artisan tenancy:fix-databases         # Fix database issues

# ✅ Diagnostics and debugging
php artisan tenancy:diagnose              # System diagnostics
php artisan af-tenancy:debug-connection   # Connection debugging
php artisan tenancy:health                # Health monitoring
```

### **Command Categories**
- **Core**: `tenant:create`, `tenant:manage`, `tenant:db`
- **Testing**: `tenancy:test-*`, `tenancy:validate`, `tenancy:stress-test`
- **Diagnostics**: `tenancy:diagnose`, `tenancy:health`, `af-tenancy:debug-*`
- **Maintenance**: `tenancy:fix-*`, `tenant:backup-manager`, `tenancy:cache:warm`
- **Performance**: `tenancy:test-performance*`, `tenancy:connection-pool`

---

## 🔌 API Guidelines

### **API Security Standards**
```php
// ✅ ALWAYS validate API keys
protected function validateApiKey(Request $request): bool
{
    $apiKey = $request->header('X-API-Key') 
             ?? $request->input('api_key')
             ?? $request->bearerToken();
    
    if (!$apiKey || !hash_equals(config('artflow-tenancy.api.api_key'), $apiKey)) {
        return false;
    }
    
    return true;
}

// ✅ Rate limiting
Route::middleware(['throttle:60,1'])->group(function () {
    Route::apiResource('tenants', TenantApiController::class);
});
```

### **API Response Standards**
```php
// ✅ Consistent response format
return response()->json([
    'success' => true,
    'data' => $tenant,
    'message' => 'Tenant created successfully',
    'timestamp' => now()->toISOString(),
]);

// ✅ Error response format
return response()->json([
    'success' => false,
    'error' => [
        'code' => 422,
        'type' => 'validation_error',
        'message' => 'The given data was invalid',
        'details' => $validator->errors(),
    ],
    'timestamp' => now()->toISOString(),
], 422);
```

### **API Endpoints Structure**
```
/api/tenancy/
├── tenants/                    # Tenant CRUD
│   ├── GET /                   # List tenants
│   ├── POST /                  # Create tenant
│   ├── GET /{id}               # Get tenant
│   ├── PUT /{id}               # Update tenant
│   ├── DELETE /{id}            # Delete tenant
├── monitoring/                 # Monitoring endpoints
│   ├── GET /system-stats       # System statistics
│   ├── GET /tenant-stats/{id}  # Tenant statistics
│   └── GET /health             # Health check
└── operations/                 # Tenant operations
    ├── POST /migrate-all       # Bulk migrations
    └── POST /clear-cache       # Cache management
```

---

## 🛠️ Maintenance & Operations

### **Regular Maintenance Tasks**
```bash
# ✅ Daily health checks
php artisan tenancy:health --detailed

# ✅ Weekly performance validation
php artisan tenancy:test-performance --detailed

# ✅ Monthly system validation
php artisan tenancy:validate --fix

# ✅ Quarterly stress testing
php artisan tenancy:stress-test --users=100 --duration=300
```

### **Monitoring Checklist**
- [ ] System health score > 9.0/10
- [ ] All database connections working (100% success rate)
- [ ] Average response time < 25ms
- [ ] Memory usage < optimal thresholds
- [ ] No failed tenants in isolation tests
- [ ] Cache hit ratio > 90%
- [ ] All middleware properly registered

### **Backup Strategy**
```php
// ✅ Regular tenant backups
public function performRegularBackups(): void
{
    $tenants = Tenant::where('status', 'active')->get();
    
    foreach ($tenants as $tenant) {
        try {
            $this->backupService->createBackup($tenant, [
                'include_data' => true,
                'compress' => true,
                'retention_days' => 30,
            ]);
        } catch (BackupException $e) {
            Log::error('Backup failed for tenant', [
                'tenant_id' => $tenant->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

---

## 🚨 Critical Warnings & Don'ts

### **NEVER DO THESE THINGS**
```php
// ❌ NEVER bypass tenant context for data access
DB::connection('mysql')->table('users')->get(); // Wrong database!

// ❌ NEVER modify stancl/tenancy core files
// If you need changes, extend or override properly

// ❌ NEVER ignore middleware ordering
'tenant.web' => [
    'tenant.scope-sessions', // ❌ Wrong order - will break sessions
    'web',
    'tenant',
];

// ❌ NEVER store sensitive data in logs
Log::info('User login', ['password' => $password]); // Security risk!

// ❌ NEVER perform operations without proper error handling
$tenant = $this->createTenant($data); // What if it fails?
$this->setupDatabase($tenant);        // This could fail too!
```

### **ALWAYS DO THESE THINGS**
```php
// ✅ ALWAYS verify tenant context
if (!tenancy()->initialized) {
    throw new TenantContextRequiredException();
}

// ✅ ALWAYS use proper error handling
try {
    $tenant = $this->createTenant($data);
    $this->setupDatabase($tenant);
    $this->runMigrations($tenant);
} catch (Exception $e) {
    $this->cleanupFailedTenant($tenant);
    throw new TenantCreationException($e->getMessage(), 0, $e);
}

// ✅ ALWAYS validate input
$validated = $request->validate([
    'name' => 'required|string|max:255',
    'domain' => 'required|string|max:255|unique:domains,domain',
    'status' => 'required|in:active,inactive,suspended,blocked',
]);
```

---

## 📚 Additional Resources

### **Key Documentation Files**
- `docs/ARCHITECTURE.md` - Technical architecture details
- `docs/API.md` - Complete API reference
- `docs/COMMAND_REFERENCE.md` - All available commands
- `docs/installation/INSTALLATION_GUIDE.md` - Setup instructions
- `docs/features/FEATURES.md` - Complete feature list

### **Testing Resources**
- Run `php artisan tenancy:test-all` for interactive testing
- Use `php artisan tenancy:validate --detailed` for comprehensive validation
- Refer to `.mcp` file for current package health status

### **Support & Community**
- Check `storage/logs/laravel.log` for error details
- Use debug commands with `--verbose` flag for detailed output
- Test changes in isolated environment before production deployment

---

## 🎯 Final Guidelines for AI Agents

### **Before Making Any Changes**
1. **Read this entire document** - Understand the architecture and constraints
2. **Run system validation** - `php artisan tenancy:validate`
3. **Check current package health** - Review `.mcp` file
4. **Understand the change impact** - Consider tenant isolation and security

### **During Development**
1. **Follow security guidelines** - Tenant isolation is paramount
2. **Write comprehensive tests** - Test all code paths and edge cases
3. **Use proper error handling** - Graceful failure and cleanup
4. **Document changes** - Update relevant documentation

### **After Making Changes**
1. **Run full test suite** - `php artisan tenancy:test-all`
2. **Validate system health** - `php artisan tenancy:validate --detailed`
3. **Test performance impact** - `php artisan tenancy:test-performance`
4. **Update documentation** - Keep docs synchronized with changes

### **Remember**
- This package handles sensitive multi-tenant data
- Security and tenant isolation are non-negotiable
- Performance matters - aim for sub-25ms tenant switching
- Always test thoroughly before deployment
- When in doubt, run the validation commands

---

**🏢 Package Information**
- **Name**: artflow-studio/tenancy
- **Version**: 2.0+
- **Base**: stancl/tenancy 3.9.1+
- **Laravel**: 10.x / 11.x
- **PHP**: 8.0+ (8.2+ recommended)
- **Health Score**: 9.5/10
- **Status**: Production Ready

**📝 Document Version**: 1.0 | **Last Updated**: December 2024 | **Next Review**: March 2025