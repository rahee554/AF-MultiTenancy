# Artflow Studio Tenancy Package

[![Latest Version](https://img.shields.io/packagist/v/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)
[![Total Downloads](https://img.shields.io/packagist/dt/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)
[![License](https://img.shields.io/packagist/l/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)

**Version: 0.3.0**

A comprehensive, production-ready multi-tenant Laravel package with admin dashboard, API endpoints, and domain management. Built on top of `stancl/tenancy` with additional enterprise features and zero-configuration setup.

## 🚀 Quick Start

### One-Command Installation

```bash
composer require artflow-studio/tenancy
```

That's it! The package automatically:
- ✅ Installs `stancl/tenancy` as a dependency
- ✅ Publishes required configuration files
- ✅ Registers middleware and routes
- ✅ Sets up database migrations

### Add API Key & Migrate

```bash
# Add to .env file
echo "TENANT_API_KEY=sk_tenant_live_$(openssl rand -hex 32)" >> .env

# Run migrations
php artisan migrate
```

### Access Admin Dashboard

Visit: `http://your-domain.com/admin/dashboard`

---

## 📋 Requirements

- **PHP**: 8.1+
- **Laravel**: 10.0+ or 11.0+
- **Database**: MySQL 5.7+ or 8.0+
- **Cache**: Redis (recommended)

---

## 🎯 Features

### 🏢 Multi-Tenancy Core
- **Isolated Databases** - Each tenant gets its own MySQL database
- **Custom Domains** - Full domain management per tenant
- **Zero Configuration** - Works out of the box
- **Extends stancl/tenancy** - Built on the most popular Laravel tenancy package

### 🎛️ Admin Dashboard
- **Modern UI** - Metronic-based responsive admin interface
- **Real-time Monitoring** - Live stats, performance metrics, system health
- **Tenant Management** - Create, edit, suspend, activate, delete tenants
- **Migration Control** - Per-tenant database migration management
- **Status Management** - Active, suspended, blocked, inactive states

### 🔌 RESTful API
- **Complete CRUD** - Full tenant management via API
- **Secure Authentication** - API key and Bearer token support
- **Rate Limiting** - Built-in API protection
- **External Integration** - Perfect for external applications and services

### 🔧 Advanced Features
- **Auto-Discovery** - Laravel package auto-discovery support
- **Performance Monitoring** - Database sizes, cache statistics, active users
- **Error Handling** - Comprehensive error pages for blocked/suspended tenants
- **Queue Support** - Background job processing for bulk operations

---

## 📦 Installation Guide

### Step 1: Install Package

```bash
composer require artflow-studio/tenancy
```

**What happens automatically:**
- Installs `stancl/tenancy` with the latest version (`*` dependency)
- Registers service provider via Laravel auto-discovery
- Auto-publishes critical configuration files
- Registers middleware (`tenant`) globally

### Step 2: Environment Configuration

Add these variables to your `.env` file:

```env
# Tenant API Security (Required)
TENANT_API_KEY=sk_tenant_live_your_secure_api_key_here

# Tenant Database Configuration
TENANT_DB_HOST=127.0.0.1
TENANT_DB_PORT=3306
TENANT_DB_USERNAME=root
TENANT_DB_PASSWORD=

# Redis Configuration (Recommended)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Step 3: Database Configuration

Add tenant database connection to `config/database.php`:

```php
'connections' => [
    // ... existing connections
    
    'tenant' => [
        'driver' => 'mysql',
        'host' => env('TENANT_DB_HOST', '127.0.0.1'),
        'port' => env('TENANT_DB_PORT', '3306'),
        'username' => env('TENANT_DB_USERNAME', 'root'),
        'password' => env('TENANT_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

This creates:
- `tenants` table (custom enhanced structure)
- `domains` table (for domain management)
- Required indexes and foreign keys

---

## 🔌 API Endpoints Reference

### Core CRUD Operations
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/tenants` | GET | List all tenants with pagination | `?page=1&per_page=15&search=term&status=active&sort=name&order=asc` |
| `/tenancy/tenants/create` | POST | Create new tenant | `name, domain, status, database_name, notes, run_migrations` |
| `/tenancy/tenants/{uuid}` | GET | Get tenant details | UUID in URL path |
| `/tenancy/tenants/{uuid}` | PUT | Update tenant information | `name, status, notes` |
| `/tenancy/tenants/{uuid}` | DELETE | Delete tenant and database | UUID in URL path |

### Tenant Management
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/tenants/{uuid}/status` | PUT | Update tenant status | `status: active\|suspended\|blocked\|inactive` |
| `/tenancy/tenants/{uuid}/block` | POST | Block tenant access | UUID in URL path |
| `/tenancy/tenants/{uuid}/reset` | POST | Reset tenant database | `confirm: true` |
| `/tenancy/bulk-status-update` | PUT | Update multiple tenant statuses | `tenant_uuids: [], status: string` |

### Domain Management
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/tenants/{uuid}/domains` | GET | Get tenant domains | UUID in URL path |
| `/tenancy/tenants/{uuid}/domains/create` | POST | Add domain to tenant | `domain: string, is_primary: boolean` |
| `/tenancy/tenants/{uuid}/domains/{domainId}` | DELETE | Remove domain from tenant | UUID and domainId in URL path |

### Database Operations
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/tenants/{uuid}/migrate` | POST | Run migrations for tenant | `fresh: boolean, seed: boolean` |
| `/tenancy/tenants/{uuid}/seed` | POST | Seed tenant database | `class: string (optional)` |
| `/tenancy/migrate-all-tenants` | POST | Migrate all tenant databases | `fresh: boolean, seed: boolean` |
| `/tenancy/seed-all-tenants` | POST | Seed all tenant databases | `class: string (optional)` |

### System Monitoring
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/dashboard` | GET | System dashboard data | None |
| `/tenancy/stats` | GET | System statistics | None |
| `/tenancy/live-stats` | GET | Real-time statistics | None |
| `/tenancy/health` | GET | System health status | None |
| `/tenancy/performance` | GET | Performance metrics | `period: hour\|day\|week\|month` |
| `/tenancy/connection-stats` | GET | Database connection stats | None |
| `/tenancy/active-users` | GET | Active users across tenants | None |

### System Operations
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/clear-cache` | POST | Clear system cache | `keys: [] (optional)` |
| `/tenancy/clear-all-caches` | POST | Clear all caches | None |
| `/tenancy/system-info` | GET | System information | None |
| `/tenancy/maintenance/on` | POST | Enable maintenance mode | `message: string (optional)` |
| `/tenancy/maintenance/off` | POST | Disable maintenance mode | None |

### Backup & Restore
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/tenants/{uuid}/backup` | POST | Backup tenant database | `include_data: boolean, compression: boolean` |
| `/tenancy/tenants/{uuid}/restore` | POST | Restore tenant from backup | `backup_file: file, confirm: true` |
| `/tenancy/tenants/{uuid}/export` | POST | Export tenant data | `format: json\|csv\|sql, tables: []` |
| `/tenancy/import-tenant` | POST | Import tenant data | `import_file: file, name: string, domain: string` |

### Analytics & Reports
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/analytics/overview` | GET | Analytics overview | `period: day\|week\|month\|year` |
| `/tenancy/analytics/usage` | GET | Usage analytics | `tenant_uuid: string (optional)` |
| `/tenancy/analytics/performance` | GET | Performance analytics | `metric: cpu\|memory\|disk\|queries` |
| `/tenancy/analytics/growth` | GET | Growth analytics | `period: day\|week\|month\|year` |
| `/tenancy/reports/tenants` | GET | Tenants report | `format: json\|csv\|pdf, filters: {}` |
| `/tenancy/reports/system` | GET | System report | `format: json\|csv\|pdf, sections: []` |

### Webhooks
| Endpoint | Method | Purpose | Parameters |
|----------|--------|---------|------------|
| `/tenancy/webhooks/tenant-created` | POST | Tenant creation webhook | Webhook payload |
| `/tenancy/webhooks/tenant-updated` | POST | Tenant update webhook | Webhook payload |
| `/tenancy/webhooks/tenant-deleted` | POST | Tenant deletion webhook | Webhook payload |

---

## 🛠️ Artisan Commands Reference

### Primary Tenant Management Command

The package provides a comprehensive `tenant:manage` command with multiple actions:

```bash
php artisan tenant:manage {action} [options]
```

### Available Actions
| Command | Purpose |
|---------|---------|
| `tenant:manage create` | Create new tenant interactively |
| `tenant:manage list` | List all tenants in table format |
| `tenant:manage delete` | Delete tenant and database |
| `tenant:manage activate` | Activate suspended tenant |
| `tenant:manage deactivate` | Deactivate active tenant |
| `tenant:manage migrate` | Run migrations for specific tenant |
| `tenant:manage migrate-all` | Run migrations for all tenants |
| `tenant:manage seed` | Seed specific tenant database |
| `tenant:manage seed-all` | Seed all tenant databases |
| `tenant:manage status` | Show detailed tenant status |
| `tenant:manage health` | Check system health |

### Command Options
| Option | Description |
|--------|-------------|
| `--tenant=UUID` | Target specific tenant by UUID |
| `--name=NAME` | Set tenant name (create) |
| `--domain=DOMAIN` | Set tenant domain (create) |
| `--database=NAME` | Custom database name (create) |
| `--status=STATUS` | Set tenant status (create) |
| `--notes=TEXT` | Add tenant notes (create) |
| `--force` | Skip confirmation prompts |
| `--seed` | Run seeders after migration |
| `--fresh` | Drop tables before migrating |

### Usage Examples

```bash
# Create new tenant
php artisan tenant:manage create --name="Acme Corp" --domain="acme.local"

# List all tenants
php artisan tenant:manage list

# Migrate specific tenant
php artisan tenant:manage migrate --tenant=abc-123-def

# Migrate all tenants with fresh install
php artisan tenant:manage migrate-all --fresh --seed

# Check tenant status
php artisan tenant:manage status --tenant=abc-123-def

# System health check
php artisan tenant:manage health

# Delete tenant (with confirmation)
php artisan tenant:manage delete --tenant=abc-123-def

# Force delete without confirmation
php artisan tenant:manage delete --tenant=abc-123-def --force
```

---

## 🏗️ How It Extends stancl/tenancy

This package builds upon `stancl/tenancy` by adding:

### Enhanced Models
```php
// Our enhanced Tenant model vs stancl/tenancy
ArtflowStudio\Tenancy\Models\Tenant extends Stancl\Tenancy\Database\Models\Tenant
```

**Additional Features:**
- Status management (active, suspended, blocked, inactive)
- Enhanced domain relationships
- Database size tracking
- Migration status monitoring
- User activity tracking

### Custom Middleware
```php
// Our unified middleware vs stancl/tenancy's separate middleware
'tenant' => ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware::class
```

**Enhanced Features:**
- Combined tenant identification and database switching
- Status-based access control (blocks suspended/inactive tenants)
- Error page rendering for blocked tenants
- Performance optimizations

### Advanced Services
```php
// Our TenantService extends functionality
ArtflowStudio\Tenancy\Services\TenantService
```

**Additional Capabilities:**
- Bulk operations (migrate all, clear caches)
- Advanced monitoring and statistics
- Database management (create, migrate, seed, reset)
- Performance metrics collection

### Admin Interface
**What stancl/tenancy doesn't provide:**
- Complete admin dashboard
- Visual tenant management
- Real-time monitoring
- Migration control interface
- API endpoints for external access

---

## 🎛️ Package Structure

```
packages/artflow-studio/tenancy/
├── 📁 config/
│   └── tenancy.php              # Enhanced tenancy configuration
├── 📁 database/
│   └── migrations/
│       ├── create_tenants_table.php
│       └── create_domains_table.php
├── 📁 resources/
│   └── views/
│       ├── admin/
│       │   ├── dashboard.blade.php    # Main admin dashboard
│       │   ├── create.blade.php       # Create tenant form
│       │   └── show.blade.php         # Tenant details
│       ├── errors/
│       │   ├── tenant-blocked.blade.php
│       │   ├── tenant-suspended.blade.php
│       │   └── tenant-inactive.blade.php
│       └── layouts/
├── 📁 routes/
│   └── tenancy.php              # All package routes
├── 📁 src/
│   ├── 📁 Commands/
│   │   └── TenantCommand.php    # Enhanced tenant management
│   ├── 📁 Http/
│   │   ├── Controllers/
│   │   │   ├── TenantApiController.php    # API endpoints
│   │   │   └── TenantViewController.php   # Web interface
│   │   └── Middleware/
│   │       └── TenantMiddleware.php       # Unified tenancy middleware
│   ├── 📁 Models/
│   │   ├── Tenant.php           # Enhanced tenant model
│   │   └── Domain.php           # Enhanced domain model
│   ├── 📁 Services/
│   │   └── TenantService.php    # Core business logic
│   └── TenancyServiceProvider.php       # Auto-discovery provider
├── composer.json                # Package definition
└── README.md                   # This documentation
```

---

## 🎮 Usage Examples

### Creating Tenants

#### Via Admin Dashboard
1. Navigate to `/admin/dashboard`
2. Click "Create New Tenant"
3. Fill the form and submit

#### Via API
```bash
curl -X POST "http://your-domain.com/tenancy/tenants/create" \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Acme Corporation",
    "domain": "acme.yourdomain.com",
    "status": "active",
    "run_migrations": true,
    "notes": "New enterprise client"
  }'
```

#### Via Artisan Command
```bash
php artisan tenant:create "Acme Corporation" acme.yourdomain.com --migrate
```

### Managing Tenants

#### Tenant Status Management
```php
// In your controller
use ArtflowStudio\Tenancy\Services\TenantService;

$tenantService = app(TenantService::class);

// Suspend a tenant
$tenantService->updateStatus($tenantUuid, 'suspended');

// Activate a tenant
$tenantService->updateStatus($tenantUuid, 'active');

// Block a tenant (shows error page)
$tenantService->updateStatus($tenantUuid, 'blocked');
```

#### Database Operations
```php
// Migrate specific tenant
$tenantService->migrateTenant($tenantUuid);

// Seed tenant database
$tenantService->seedTenant($tenantUuid);

// Reset tenant database (DANGEROUS)
$tenantService->resetTenantDatabase($tenantUuid);

// Migrate all tenants
$tenantService->migrateAllTenants();
```

### Using Tenant Middleware

```php
// In your routes/web.php
Route::middleware(['tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
         ->name('tenant.dashboard');
    
    Route::resource('customers', CustomerController::class);
    Route::resource('orders', OrderController::class);
});
```

### Tenant Context in Controllers

```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // Current tenant is automatically set by middleware
        $tenant = tenant();
        
        // All database queries now use tenant database
        $customers = \App\Models\Customer::all();
        $orders = \App\Models\Order::count();
        
        return view('tenant.dashboard', compact('tenant', 'customers', 'orders'));
    }
}
```

---

## 🔌 API Reference

### Authentication

All API endpoints require authentication via the `X-API-Key` header with proper middleware enforcement:

**API Key Authentication (Required):**
```bash
curl -X GET "http://your-domain.com/tenancy/tenants" \
  -H "X-API-Key: sk_tenant_live_your_secure_api_key_here"
```

**Environment Variables:**
```env
# Required API key for production and development
TENANT_API_KEY=sk_tenant_live_your_secure_api_key_here
```

**Security Features:**
- ✅ **Middleware-enforced authentication** - All API routes protected by `tenancy.api` middleware
- ✅ **Rate limiting** - Built-in throttling via `throttle:api`
- ✅ **Development mode** - Localhost allowed without API key if none configured
- ✅ **Production mode** - API key always required in production environments
- ✅ **Consistent error responses** - Standardized 401 responses for unauthorized access

**Error Responses:**
```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "Invalid or missing API key. Please include X-API-Key header.",
  "code": 401,
  "timestamp": "2025-08-01T14:30:00Z"
}
```

**Security Notes:**
- API key validation happens at the middleware level before reaching controllers
- No API key bypass in production environments
- Development environments (localhost) can work without API key for testing
- All routes under `/tenancy/*` are automatically protected

### Tenant Management Endpoints

#### List Tenants
```bash
GET /tenancy/tenants
```

**Query Parameters:**
- `page` (int): Page number for pagination (default: 1)
- `per_page` (int): Items per page (default: 15, max: 100)
- `search` (string): Search by tenant name or domain
- `status` (string): Filter by status (active, suspended, blocked, inactive)
- `sort` (string): Sort field (name, created_at, status)
- `order` (string): Sort order (asc, desc)

**Example Request:**
```bash
GET /tenancy/tenants?page=1&per_page=20&search=acme&status=active&sort=created_at&order=desc
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Acme Corporation",
        "database_name": "tenant_acme_abc123",
        "status": "active",
        "domains": [
          {
            "id": 1,
            "domain": "acme.yourdomain.com",
            "tenant_id": 1
          }
        ],
        "created_at": "2025-01-01T00:00:00.000000Z",
        "updated_at": "2025-01-01T00:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 1
  },
  "timestamp": "2025-07-31T14:30:00Z"
}
```

#### Create Tenant
```bash
POST /tenancy/tenants/create
```

**Request Body:**
```json
{
  "name": "Acme Corporation",
  "domain": "acme.yourdomain.com",
  "status": "active",
  "database_name": "custom_db_name",
  "notes": "Customer notes here",
  "run_migrations": true
}
```

**Required Fields:**
- `name` (string): Tenant display name
- `domain` (string): Primary domain for tenant

**Optional Fields:**
- `status` (string): active|suspended|blocked|inactive (default: active)
- `database_name` (string): Custom database name (auto-generated if not provided)
- `notes` (string): Additional notes
- `run_migrations` (boolean): Run migrations after creation (default: false)

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Acme Corporation",
      "database_name": "tenant_acme_abc123",
      "status": "active"
    },
    "domain": {
      "id": 1,
      "domain": "acme.yourdomain.com",
      "tenant_id": 1
    },
    "migration_status": "completed"
  },
  "message": "Tenant created successfully",
  "timestamp": "2025-07-31T14:30:00Z"
}
```

#### Get Tenant Details
```bash
GET /tenancy/tenants/{uuid}
```

#### Update Tenant
```bash
PUT /tenancy/tenants/{uuid}
```

#### Delete Tenant
```bash
DELETE /tenancy/tenants/{uuid}
```

#### Update Tenant Status
```bash
PUT /tenancy/tenants/{uuid}/status
```

**Request Body:**
```json
{
  "status": "suspended",
  "reason": "Payment overdue",
  "notify": true
}
```

**Available Statuses:**
- `active`: Tenant fully operational
- `suspended`: Temporary access restriction
- `blocked`: Permanent access restriction
- `inactive`: Tenant disabled but data preserved

#### Add Domain to Tenant
```bash
POST /tenancy/tenants/{uuid}/domains/create
```

**Request Body:**
```json
{
  "domain": "subdomain.yourdomain.com",
  "is_primary": false,
  "ssl_enabled": true
}
```

#### Migrate Tenant Database
```bash
POST /tenancy/tenants/{uuid}/migrate
```

**Request Body:**
```json
{
  "fresh": false,
  "seed": true,
  "timeout": 300
}
```

### Database Management Endpoints

#### Migrate Tenant Database
```bash
POST /tenancy/tenants/{uuid}/migrate
```

#### Seed Tenant Database
```bash
POST /tenancy/tenants/{uuid}/seed
```

#### Reset Tenant Database
```bash
POST /tenancy/tenants/{uuid}/reset
```

**⚠️ Warning:** This will delete all data in the tenant database.

### System Endpoints

#### System Statistics
```bash
GET /tenancy/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_tenants": 25,
    "active_tenants": 22,
    "suspended_tenants": 2,
    "blocked_tenants": 1,
    "total_domains": 28,
    "total_database_size_mb": 1024.5,
    "cache_keys": 1500,
    "system_uptime": "5 days, 3 hours"
  }
}
```

#### Migrate All Tenants
```bash
POST /tenancy/migrate-all
```

#### Clear All Caches
```bash
POST /tenancy/cache/clear-all
```

#### Live Statistics (Real-time)
```bash
GET /tenancy/live-stats
```

---

## 🎨 Customization

### Custom Views

Publish views to customize the admin interface:

```bash
php artisan vendor:publish --tag=tenancy-views
```

Views will be published to: `resources/views/vendor/tenancy/`

### Custom Routes

Publish routes to modify endpoints:

```bash
php artisan vendor:publish --tag=tenancy-routes
```

Routes will be published to: `routes/tenancy.php`

### Custom Configuration

The package automatically publishes configuration. Modify `config/tenancy.php`:

```php
<?php

return [
    'tenant_model' => \ArtflowStudio\Tenancy\Models\Tenant::class,
    'domain_model' => \ArtflowStudio\Tenancy\Models\Domain::class,
    
    'database' => [
        'prefix' => 'tenant_',
        'suffix' => '_db',
        'connection' => 'tenant',
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
    
    'api' => [
        'rate_limit' => [
            'enabled' => true,
            'attempts' => 60,
            'decay' => 1,
        ],
    ],
];
```

### Extending Models

```php
<?php

namespace App\Models;

use ArtflowStudio\Tenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    protected $fillable = [
        'name',
        'status',
        'custom_field',  // Add your custom fields
    ];
    
    public function customRelation()
    {
        return $this->hasMany(CustomModel::class);
    }
}
```

Update configuration:
```php
// config/tenancy.php
'tenant_model' => \App\Models\Tenant::class,
```

---

## 🔧 Performance Optimization

### Redis Caching
```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),
```

### Queue Configuration

For better performance with bulk operations:

```bash
# Start queue worker
php artisan queue:work redis --sleep=3 --tries=3 --timeout=60
```

---

## 🚨 Troubleshooting

### Common Issues

#### 1. Package Not Auto-Discovered
```bash
# Clear composer cache and reinstall
composer clear-cache
composer install --optimize-autoloader
```

#### 2. Config Not Auto-Published
```bash
# Manually publish config
php artisan vendor:publish --tag=tenancy-config
```

#### 3. Migrations Not Running
```bash
# Check if migrations exist
php artisan migrate:status

# Run specific package migrations
php artisan migrate --path=vendor/artflow-studio/tenancy/database/migrations
```

#### 4. Tenant Database Connection Failed
```bash
# Check tenant database config in .env
TENANT_DB_HOST=127.0.0.1
TENANT_DB_USERNAME=root
TENANT_DB_PASSWORD=your_password

# Test connection
php artisan tinker
>>> DB::connection('tenant')->getPdo();
```

#### 5. API Authentication Failed
```bash
# Check API key in .env
TENANT_API_KEY=sk_tenant_live_your_key_here

# Test API endpoint
curl -H "X-API-Key: your_key" http://your-domain.com/tenancy/tenants
```

#### 6. Routes Not Working
```bash
# Clear route cache
php artisan route:clear
php artisan cache:clear

# Check if routes are registered
php artisan route:list | grep tenancy
```

### Debug Mode

Enable detailed error reporting:

```env
APP_DEBUG=true
LOG_LEVEL=debug
DB_LOGGING=true
```

### Clear All Caches

If you encounter unexpected behavior:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

---

## 🤝 Contributing

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `./vendor/bin/phpunit`
4. Follow PSR-12 coding standards

### Reporting Issues

Please use GitHub Issues to report bugs or request features:
- Provide detailed steps to reproduce
- Include error messages and stack traces
- Specify Laravel and PHP versions

---

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🙏 Credits

- Built on top of [stancl/tenancy](https://github.com/stancl/tenancy)
- UI components from [Metronic](https://keenthemes.com/metronic)
- Developed by [Artflow Studio](https://artflow-studio.com)

---

## 📈 Changelog

### v0.3.0 - 2025-08-01

**Current Release**
- ✅ Fixed API key authentication with proper middleware
- ✅ Enhanced security with `tenancy.api` middleware
- ✅ Proper API rate limiting and throttling
- ✅ Localhost development mode support
- ✅ Production-ready API key enforcement
- ✅ Comprehensive error responses for unauthorized access
- ✅ Auto-registered API authentication middleware

### v0.2.0 - 2025-08-01

**Previous Release**
- ✅ Complete multi-tenant Laravel package
- ✅ Admin dashboard with Metronic UI
- ✅ Full RESTful API with 30+ endpoints
- ✅ Comprehensive Artisan commands
- ✅ Auto-discovery and zero-config setup
- ✅ Enhanced tenant and domain models
- ✅ Unified middleware for tenancy
- ✅ Real-time monitoring and statistics
- ✅ Production-ready error handling
- ✅ Backup and restore functionality
- ✅ Analytics and reporting

---

**Need Help?** 

- 📖 Read the docs above
- 🐛 [Report issues](https://github.com/artflow-studio/tenancy/issues)
- 📧 Email: support@artflow-studio.com

**Happy multi-tenanting!** 🎉
