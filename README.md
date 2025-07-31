# Artflow Studio Tenancy Package

A comprehensive multi-tenant Laravel package with admin dashboard, domain management, and powerful tenant administration tools.

## Features

- 🏢 **Multi-Tenant Architecture**: Complete isolation with separate databases per tenant
- 🎛️ **Admin Dashboard**: Modern, feature-rich dashboard for tenant management
- 🌐 **Domain Management**: Easy domain addition/removal for tenants
- 📊 **Real-time Statistics**: Live monitoring of tenant metrics and system health
- 🔧 **Database Management**: Automated migrations and seeding per tenant
- 🚦 **Status Management**: Active, inactive, suspended, and blocked tenant states
- 🎨 **Modern UI**: Built with Metronic design system
- 📱 **Responsive Design**: Works perfectly on all devices
- ⚡ **Performance Optimized**: Efficient database queries and caching
- 🔒 **Security**: Built-in protection and validation

## Installation

### Step 1: Install the Package

```bash
composer require artflow-studio/tenancy
```

### Step 2: Install Stancl/Tenancy (if not already installed)

```bash
composer require stancl/tenancy
```

### Step 3: Publish Package Assets

```bash
# Publish configuration
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider" --tag="tenancy-config"

# Publish views (optional, for customization)
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider" --tag="tenancy-views"

# Publish migrations
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider" --tag="tenancy-migrations"
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

### Step 5: Configure Your Environment

Add to your `.env` file:

```env
# Tenant Database Configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql
TENANT_AUTO_MIGRATE=false
TENANT_AUTO_SEED=false

# Domain Configuration
APP_DOMAIN=yourdomain.com
```

## Configuration

The package uses a comprehensive configuration file located at `config/tenancy.php`. Key settings include:

- **Database Configuration**: Prefix, connection, charset
- **Domain Management**: Central domains configuration
- **Cache Settings**: TTL and prefix configuration
- **Admin Dashboard**: Routes and middleware
- **Migration/Seeder**: Auto-execution settings

## Usage

### Creating Tenants

#### Via Artisan Command

```bash
php artisan tenant:manage create --name="Company ABC" --domain="company.local"
```

#### Via Admin Dashboard

Navigate to `/admin/dashboard` and use the modern interface to:
- Create new tenants with auto-generated database names
- Configure migration and seeding options
- Set initial status and notes

#### Programmatically

```php
use ArtflowStudio\Tenancy\Services\TenantService;

$tenantService = app(TenantService::class);
$tenant = $tenantService->createTenant(
    name: 'Company ABC',
    domain: 'company.local',
    status: 'active'
);
```

### Managing Tenants

#### List All Tenants

```bash
php artisan tenant:manage list
```

#### Migrate Specific Tenant

```bash
php artisan tenant:manage migrate-tenant {uuid}
```

#### Update Tenant Status

```bash
php artisan tenant:manage update-status {uuid} suspended
```

### Admin Dashboard Features

Access the admin dashboard at `/admin/dashboard` to:

- **View System Statistics**: Total tenants, active users, database sizes
- **Monitor Performance**: Real-time connection stats and cache metrics
- **Manage Tenants**: Create, edit, activate, suspend, or block tenants
- **Database Operations**: Run migrations, seeders, or reset databases
- **Domain Management**: Add/remove domains for each tenant
- **Live Monitoring**: Auto-refreshing statistics and health checks

### Advanced Features

#### Tenant-Specific Actions

```php
// Get tenant statistics
$stats = $tenantService->getTenantStats($tenant);

// Migrate tenant database
$tenantService->migrateTenant($tenant);

// Seed tenant database  
$tenantService->seedTenant($tenant);

// Delete tenant and database
$tenantService->deleteTenant($tenant);
```

#### Bulk Operations

```php
// Migrate all tenants
$tenantService->migrateAllTenants();

// Clear all tenant caches
$tenantService->clearAllCaches();
```

## Views and Customization

### Default Views

The package includes:
- `admin.tenants.dashboard` - Main admin dashboard
- `admin.tenants.show` - Individual tenant details
- `admin.tenants.create` - Create new tenant form

### Customizing Views

Publish the views and modify them:

```bash
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider" --tag="tenancy-views"
```

Views will be published to `resources/views/vendor/tenancy/`

## Middleware

The package provides `TenantMiddleware` that:
- Resolves tenants by domain
- Switches database connections
- Handles tenant status (blocked, suspended, etc.)
- Shows appropriate error pages

## Routes

### Admin Routes (Central Domain)

- `GET /admin/dashboard` - Main dashboard
- `GET /admin/tenants` - Tenant list  
- `GET /admin/tenants/create` - Create tenant form
- `GET /admin/tenants/{uuid}` - Tenant details
- `POST /admin/tenants/{uuid}/migrate` - Run migrations
- `POST /admin/tenants/{uuid}/seed` - Run seeders
- And many more...

### API Endpoints

- `GET /admin/stats` - System statistics
- `GET /admin/live-stats` - Real-time statistics
- `GET /admin/health` - System health check
- `GET /admin/performance` - Performance metrics

## Database Structure

### Tenants Table

- `id` - Primary key
- `uuid` - Unique identifier
- `name` - Tenant name
- `database_name` - Database name
- `status` - Current status (active, inactive, suspended, blocked)
- `notes` - Optional notes
- `created_at` / `updated_at` - Timestamps

### Domains Table

- `id` - Primary key
- `domain` - Domain name (unique)
- `tenant_id` - Foreign key to tenants
- `created_at` / `updated_at` - Timestamps

## Error Handling

The package includes custom error pages for:
- **Blocked Tenants**: Professional block notice
- **Suspended Tenants**: Suspension information
- **Inactive Tenants**: Inactive status message

## Requirements

- PHP ^8.1
- Laravel ^10.0|^11.0
- MySQL/PostgreSQL
- stancl/tenancy ^3.9
- spatie/laravel-permission ^5.0|^6.0

## Security

The package includes:
- CSRF protection on all forms
- Input validation and sanitization
- SQL injection prevention
- Proper authorization checks
- Secure database operations

## Performance

- **Efficient Queries**: Optimized database queries with proper indexing
- **Caching**: Built-in caching for statistics and frequently accessed data
- **Connection Pooling**: Efficient database connection management
- **Background Processing**: Support for queued operations

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

For support, please contact [info@artflow-studio.com](mailto:info@artflow-studio.com)

---

Made with ❤️ by Artflow Studio
