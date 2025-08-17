# 🏗️ ArtFlow Studio Tenancy Package Architecture

**Version: 2.0** - Complete Technical Architecture and Developer Guide  
**Compatible with**: Laravel 10+ & 11+, stancl/tenancy v3+, Livewire 3+

## 📋 Table of Contents

1. [Overview](#overview)
2. [Core Architecture](#core-architecture)
3. [Package Structure](#package-structure)
4. [Component Details](#component-details)
5. [Database Design](#database-design)
6. [Middleware Stack](#middleware-stack)
7. [Service Layer](#service-layer)
8. [Livewire Integration](#livewire-integration)
9. [Command System](#command-system)
10. [Extension Points](#extension-points)
11. [Development Guidelines](#development-guidelines)

## 🎯 Overview

ArtFlow Studio Tenancy is an **enterprise-grade Laravel package** that extends `stancl/tenancy` with enhanced multi-tenancy features including:

- ✅ **Complete Livewire 3 Integration** with session scoping
- ✅ **Status Management** (active, suspended, blocked, inactive)
- ✅ **Enhanced Middleware Stack** with proper session isolation
- ✅ **Comprehensive CLI Tools** (20+ Artisan commands)
- ✅ **Real-time Monitoring** and analytics
- ✅ **Performance Optimizations** and caching
- ✅ **API Management** with authentication

### **Design Principles**
- **Built ON stancl/tenancy** - Extends, never replaces core functionality
- **Session Isolation** - Proper Livewire support with ScopeSessions middleware
- **Event-Driven** - Uses Laravel events for lifecycle management
- **Middleware-Based** - Request handling through enhanced middleware stack
- **Service-Oriented** - Business logic encapsulated in service classes
- **Zero-Config** - Works out of the box with sensible defaults

---

## 🏗️ Core Architecture

### **Foundation Layer - stancl/tenancy**
```
┌─────────────────────────────────────────────┐
│              APPLICATION REQUEST            │
└─────────────────────────────────────────────┘
                     │
┌─────────────────────────────────────────────┐
│           STANCL/TENANCY CORE               │
│  ┌─────────────┐  ┌─────────────────────────┤
│  │ Domain      │  │ Database                │
│  │ Resolution  │  │ Switching               │
│  └─────────────┘  └─────────────────────────┤
│  ┌─────────────┐  ┌─────────────────────────┤
│  │ Tenant      │  │ Migration               │
│  │ Bootstrap   │  │ Management              │
│  └─────────────┘  └─────────────────────────┤
└─────────────────────────────────────────────┘
                     │
┌─────────────────────────────────────────────┐
│         ARTFLOW STUDIO ENHANCEMENTS         │
│  ┌─────────────┐  ┌─────────────────────────┤
│  │ Status      │  │ Session                 │
│  │ Management  │  │ Scoping                 │
│  └─────────────┘  └─────────────────────────┤
│  ┌─────────────┐  ┌─────────────────────────┤
│  │ Livewire    │  │ Performance             │
│  │ Integration │  │ Monitoring              │
│  └─────────────┘  └─────────────────────────┤
└─────────────────────────────────────────────┘
```

### **Middleware Stack Integration**
Our package integrates seamlessly with stancl/tenancy's middleware:

```php
// Middleware Group: tenant.web (Critical Order)
[
    'web',                        // Laravel web middleware (sessions, CSRF)
    'tenant',                     // stancl/tenancy: InitializeTenancyByDomain
    'tenant.prevent-central',     // stancl/tenancy: PreventAccessFromCentralDomains  
    'tenant.scope-sessions',      // stancl/tenancy: ScopeSessions (CRITICAL for Livewire)
    'af-tenant',                 // Our enhancements: status checks, logging
]
```
```
stancl/tenancy (Core) → AF-MultiTenancy (Enhancements) → Your Application
       ↓                         ↓                              ↓
   Domain Resolution         Status Checking                  Custom Logic
   Database Switching        Activity Tracking               Business Rules
   Event System             Homepage Management             Application Code
```

**We enhance stancl/tenancy with additional features while maintaining full compatibility.**

---

## 🏗️ Core Architecture

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
│                 Model Layer                                 │
│  ┌─────────────┐ ┌─────────────┐                           │
│  │   Tenant    │ │   Domain    │                           │
│  │   Model     │ │   Model     │                           │
│  └─────────────┘ └─────────────┘                           │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                Database Layer                               │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐│
│  │   Central   │ │   Tenant    │ │       Cache             ││
│  │  Database   │ │ Databases   │ │      Store              ││
│  └─────────────┘ └─────────────┘ └─────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Package Structure

```
artflow-studio/tenancy/
├── 📂 config/
│   ├── artflow-tenancy.php      # Our package configuration
│   └── tenancy.php              # Enhanced stancl/tenancy config
├── 📂 database/
│   └── migrations/              # Package migrations
├── 📂 docs/                     # Complete documentation
│   ├── ARCHITECTURE.md          # This file
│   ├── API.md                   # API reference
│   ├── COMMANDS.md              # CLI commands
│   ├── FEATURES.md              # Feature overview
│   └── INSTALLATION.md          # Setup guide
├── 📂 resources/
│   └── views/                   # Admin interface views
├── 📂 routes/
│   └── af-tenancy.php          # Package routes
├── 📂 src/
│   ├── 📂 Commands/            # 20+ CLI commands (organized into subfolders)
│   │   ├── 📂 Database/         # Database-related CLI commands
│   │   ├── 📂 Tenancy/          # Tenant management commands
│   │   └── 📂 Testing/          # Testing, diagnostics, and performance commands
│   │   # Note: Some commands were previously located in `src/Console/Commands` and have been moved here.
│   ├── 📂 Http/
│   │   ├── Controllers/        # API & web controllers
│   │   └── Middleware/         # Enhanced middleware
│   ├── 📂 Models/              # Enhanced models
│   ├── 📂 Providers/           # Service providers
│   ├── 📂 Services/            # Business logic
│   └── TenancyServiceProvider.php
├── 📂 stubs/                   # Template files
└── 📂 tests/                   # Test suites
```

---

## 🧩 Component Details

### **1. Service Provider (TenancyServiceProvider.php)**

The main service provider that bootstraps the entire package:

```php
class TenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load package resources
        $this->loadRoutesFrom(__DIR__ . '/../routes/af-tenancy.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'af-tenancy');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // Register middleware and commands
        $this->registerMiddleware();
        $this->configureLivewire();
    }

    public function register(): void
    {
        // Register stancl/tenancy first
        $this->app->register(\Stancl\Tenancy\TenancyServiceProvider::class);
        
        // Register our services
        $this->app->singleton(TenantService::class);
        $this->app->singleton(TenantContextCache::class);
    }
}
```

**Key Features:**
- ✅ Auto-registers stancl/tenancy service provider
- ✅ Configures Livewire for multi-tenancy
- ✅ Sets up middleware groups with proper ordering
- ✅ Registers 20+ Artisan commands
├── Http/
│   ├── Controllers/            # Web and API controllers
│   │   ├── TenantApiController.php
│   │   ├── TenantViewController.php
│   │   └── RealTimeMonitoringController.php
│   └── Middleware/             # Request middleware
│       ├── TenantMiddleware.php
│       ├── HomepageRedirectMiddleware.php
│       ├── SmartDomainResolver.php
│       └── ...
├── Models/                     # Eloquent models
│   ├── Tenant.php             # Main tenant model
│   └── Domain.php             # Domain model
├── Services/                   # Business logic services
│   ├── TenantService.php      # Core tenant operations
│   └── TenantContextCache.php # Caching service
├── Database/                   # Database management
│   └── HighPerformanceMySQLDatabaseManager.php
├── Providers/                  # Service providers
│   └── EventServiceProvider.php
└── TenancyServiceProvider.php  # Main service provider
```

## 🔧 Component Details

### **1. Service Provider (TenancyServiceProvider)**

**Purpose**: Main entry point that registers all package services, middleware, and configurations.

**Key Responsibilities**:
- Register stancl/tenancy service provider
- Bind custom services to container
- Register middleware aliases and groups
- Publish configuration files
- Load package routes

**Extension Points**:
```php
// Override database manager
$this->app->singleton(
    \Stancl\Tenancy\Contracts\TenantDatabaseManager::class,
    YourCustomDatabaseManager::class
);

// Add custom middleware to tenant group
$router->middlewareGroup('tenant', [
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    TenantMiddleware::class,
    HomepageRedirectMiddleware::class,
    YourCustomMiddleware::class, // Add here
]);
```

### **2. Tenant Model**

**Purpose**: Represents a tenant with enhanced features beyond stancl/tenancy base model.

**Key Features**:
- Custom database name support
- Homepage control
- Status management
- Settings storage

**Extension Points**:
```php
// Add custom attributes
protected $fillable = [
    'id', 'data', 'name', 'database', 'status', 
    'has_homepage', 'last_accessed_at', 'settings',
    'your_custom_field' // Add here
];

// Add custom methods
public function yourCustomMethod(): string
{
    return $this->settings['custom_setting'] ?? 'default';
}
```

### **3. Middleware Stack**

**Purpose**: Handle request processing and tenant resolution.

**Components**:

#### **SmartDomainResolver**
```php
// Handles central vs tenant domain routing
if ($this->isCentralDomain($domain)) {
    return $next($request); // Skip tenant resolution
}
// Continue with tenant resolution
```

#### **HomepageRedirectMiddleware**
```php
// Controls homepage access based on tenant settings
if (!$tenant->hasHomepage()) {
    return redirect('/login'); // Redirect to login
}
return $next($request); // Show homepage
```

**Extension Points**:
```php
// Create custom middleware
class YourCustomMiddleware
{
    public function handle($request, Closure $next)
    {
        // Your logic here
        return $next($request);
    }
}

// Register in service provider
$router->aliasMiddleware('your.middleware', YourCustomMiddleware::class);
```

### **4. Service Layer**

#### **TenantService**
**Purpose**: Core business logic for tenant operations.

**Key Methods**:
- `createTenant()` - Create new tenant with database
- `deleteTenant()` - Remove tenant and cleanup
- `migrateTenant()` - Run tenant migrations
- `seedTenant()` - Run tenant seeders

**Extension Points**:
```php
// Extend TenantService
class YourTenantService extends TenantService
{
    public function createTenant(
        string $name,
        string $domain,
        string $status = 'active',
        ?string $customDatabase = null,
        ?string $notes = null,
        bool $hasHomepage = false
    ): Tenant {
        // Your custom logic before creation
        $tenant = parent::createTenant($name, $domain, $status, $customDatabase, $notes, $hasHomepage);
        // Your custom logic after creation
        return $tenant;
    }
}

// Bind in service provider
$this->app->singleton(TenantService::class, YourTenantService::class);
```

## 🗄️ Database Design

### **Central Database Schema**

#### **tenants table**
```sql
CREATE TABLE tenants (
    id VARCHAR(36) PRIMARY KEY,           -- UUID
    data JSON,                            -- stancl/tenancy data storage
    name VARCHAR(255),                    -- Tenant display name
    database VARCHAR(255) UNIQUE,        -- Custom database name
    status ENUM('active','inactive','blocked') DEFAULT 'active',
    has_homepage BOOLEAN DEFAULT FALSE,   -- Homepage control
    last_accessed_at TIMESTAMP NULL,     -- Activity tracking
    settings JSON,                        -- Custom settings
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX(status),
    INDEX(last_accessed_at)
);
```

#### **domains table**
```sql
CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) UNIQUE,
    tenant_id VARCHAR(36),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX(tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### **Tenant Database Schema**
Each tenant gets its own database with:
- Laravel default tables (users, migrations, etc.)
- Custom application tables
- Tenant-specific data

## 🎭 Middleware Stack Flow

```
Request → SmartDomainResolver → InitializeTenancyByDomain → TenantMiddleware → HomepageRedirectMiddleware → Controller
    ↓              ↓                        ↓                     ↓                        ↓
Central?      Resolve Tenant         Set Tenant Context    Check Homepage        Handle Request
    ↓              ↓                        ↓                     ↓                        ↓
Skip Resolution   Initialize             Update Activity      Redirect/Continue      Response
```

## 🔄 Event System

### **Available Events**
```php
// stancl/tenancy events
- TenantCreated::class
- TenantDeleted::class  
- TenancyInitialized::class
- TenancyEnded::class

// Custom events (extend as needed)
- TenantStatusChanged::class
- TenantHomepageToggled::class
```

### **Event Listeners**
```php
// Register in EventServiceProvider
protected $listen = [
    TenantCreated::class => [
        YourTenantCreatedListener::class,
    ],
    TenantDeleted::class => [
        YourTenantDeletedListener::class,
    ],
];
```

## 🔌 Extension Points

### **1. Custom Database Manager**

```php
class YourDatabaseManager extends HighPerformanceMySQLDatabaseManager
{
    public function createDatabase(Tenant $tenant): bool
    {
        // Your custom database creation logic
        return parent::createDatabase($tenant);
    }
}

// Register in service provider
$this->app->singleton(
    \Stancl\Tenancy\Contracts\TenantDatabaseManager::class,
    YourDatabaseManager::class
);
```

### **2. Custom Commands**

```php
class YourCustomCommand extends Command
{
    protected $signature = 'tenant:your-command';
    
    public function handle()
    {
        // Your command logic
    }
}

// Register in service provider
$this->commands([YourCustomCommand::class]);
```

### **3. Custom Controllers**

```php
class YourTenantController extends Controller
{
    public function customAction(TenantService $tenantService)
    {
        // Your custom logic
    }
}

// Add routes in routes/af-tenancy.php
Route::middleware(['tenant'])->group(function () {
    Route::get('/custom', [YourTenantController::class, 'customAction']);
});
```

### **4. Custom Middleware**

```php
class YourTenantMiddleware
{
    public function handle($request, Closure $next)
    {
        if (Tenancy::initialized()) {
            $tenant = Tenancy::tenant();
            // Your tenant-specific logic
        }
        
        return $next($request);
    }
}
```

## 📋 Development Guidelines

### **Adding New Features**

1. **Follow Laravel Conventions**
   - Use Laravel's directory structure
   - Follow PSR-4 autoloading standards
   - Use Laravel's service container

2. **Maintain Backward Compatibility**
   - Don't break existing APIs
   - Add new parameters as optional
   - Use deprecation warnings before removing features

3. **Add Tests**
   - Unit tests for services
   - Integration tests for middleware
   - Feature tests for commands

4. **Update Documentation**
   - Update this architecture guide
   - Update features list
   - Add code examples

### **Testing Your Extensions**

```php
// Test custom service
public function testCustomTenantService()
{
    $service = app(YourTenantService::class);
    $tenant = $service->createTenant('Test', 'test.example.com');
    
    $this->assertInstanceOf(Tenant::class, $tenant);
    $this->assertEquals('Test', $tenant->name);
}

// Test custom middleware
public function testCustomMiddleware()
{
    $request = Request::create('/');
    $middleware = new YourCustomMiddleware();
    
    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });
    
    $this->assertEquals('OK', $response->getContent());
}
```

### **Performance Considerations**

1. **Database Queries**
   - Use eager loading for relationships
   - Add indexes for frequently queried columns
   - Cache expensive queries

2. **Memory Usage**
   - Clean up tenant context after use
   - Avoid loading unnecessary data
   - Use generators for large datasets

3. **Caching**
   - Cache tenant resolution results
   - Use Redis for session storage
   - Implement cache tags for selective clearing

## 🚀 Deployment Considerations

### **Environment Setup**
```env
# Database configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql
TENANT_DB_PERSISTENT=true

# Cache configuration
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1

# Performance tuning
TENANT_AUTO_MIGRATE=true
TENANT_CACHED_LOOKUP=true
```

### **Server Requirements**
- PHP 8.1+
- Laravel 11.x
- MySQL 8.0+ (recommended)
- Redis (for caching)
- Sufficient database connections for multiple tenants

This architecture provides a solid foundation for multi-tenant Laravel applications while maintaining flexibility for customization and extension.
