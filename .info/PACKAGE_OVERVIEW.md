# ArtFlow Studio Tenancy Package Overview

## Package Identity
- **Name**: artflow-studio/tenancy
- **Version**: Latest (developing)
- **Type**: Enterprise-grade Laravel multi-tenancy package
- **Base**: Built on top of stancl/tenancy v3.9.1+
- **License**: MIT

## Core Purpose
A comprehensive Laravel multi-tenancy solution that extends stancl/tenancy with:
- Enhanced CLI tools and management commands
- Livewire 3 integration
- Comprehensive monitoring and testing
- Enterprise-grade backup and restore
- Advanced tenant management features
- Performance testing and optimization

## Architecture Overview

### Foundation Layer (stancl/tenancy)
- Database isolation per tenant
- Domain-based tenant identification
- Core tenancy context switching
- Base tenant and domain models

### Enhancement Layer (artflow-studio/tenancy)
- Extended tenant model with additional fields (name, status, database, etc.)
- 44+ custom Artisan commands for tenant management
- Advanced backup/restore system with MySQL integration
- Performance testing and isolation verification
- Livewire components for web-based management
- Enhanced middleware stack
- Comprehensive testing and validation tools

## Key Features

### 1. Enhanced Tenant Model
- Custom fields: name, status, database, has_homepage, last_accessed_at, settings
- Status management (active, inactive, blocked)
- Custom database naming support
- Homepage detection and management

### 2. Command Suite (44+ Commands)
- **Installation**: Guided setup and configuration
- **Tenant Management**: Create, list, activate, deactivate, status
- **Database Operations**: Migration, seeding, backup, restore
- **Testing**: Performance, isolation, stress testing
- **Maintenance**: Cleanup, optimization, validation
- **Backup**: Full backup management system

### 3. Advanced Backup System
- MySQL dump integration with mysqldump/mysql binaries
- Compression support (gzip)
- Structure-only or full backups
- Tenant-specific backup directories
- Automatic cleanup of old backups
- Comprehensive restore with safety checks

### 4. Performance Testing
- Concurrent user simulation (up to 50 users)
- CRUD operation isolation testing
- Database performance benchmarking
- Stress testing capabilities
- Memory and execution time monitoring

### 5. Enhanced Middleware Stack
- TenantMiddleware: Status checking and last access tracking
- TenantAuthMiddleware: Authentication enhancements
- HomepageRedirectMiddleware: Smart homepage handling
- UniversalWebMiddleware: Cross-domain functionality
- Smart domain resolution middleware

## Directory Structure

```
artflow-studio/tenancy/
├── .info/                          # Package documentation (this directory)
├── config/
│   ├── artflow-tenancy.php         # Main configuration
│   └── tenancy.php                 # Stancl integration config
├── src/
│   ├── Bootstrappers/              # Tenancy bootstrapping
│   ├── Commands/                   # 44+ Artisan commands
│   │   ├── Backup/                 # Backup management
│   │   ├── Core/                   # Core tenant operations
│   │   ├── Database/               # Database operations
│   │   ├── Installation/           # Package installation
│   │   ├── Maintenance/            # Maintenance tasks
│   │   ├── Tenancy/                # Tenant management
│   │   └── Testing/                # Testing and validation
│   ├── Events/                     # Event classes
│   ├── Http/
│   │   ├── Controllers/            # Web controllers
│   │   ├── Livewire/              # Livewire components
│   │   └── Middleware/            # Enhanced middleware
│   ├── Models/
│   │   ├── Tenant.php             # Extended tenant model
│   │   └── Domain.php             # Domain model
│   ├── Providers/                 # Service providers
│   ├── Services/                  # Core services
│   │   ├── TenantService.php      # Main tenant operations
│   │   ├── TenantBackupService.php # Backup/restore
│   │   ├── CachedTenantResolver.php # Performance optimization
│   │   └── Others...              # Additional services
│   └── TenancyServiceProvider.php # Main service provider
├── database/
│   └── migrations/                # Package migrations
├── resources/
│   └── views/                     # Blade templates
├── routes/
│   └── af-tenancy.php            # Package routes
└── tests/                        # Test suite
```

## Integration Points

### With Laravel Application
- Service provider registration in config/app.php
- Middleware registration in bootstrap/app.php
- Configuration publishing to config/ directory
- Migration publishing to database/migrations/

### With stancl/tenancy
- Extends base Tenant and Domain models
- Integrates with stancl's bootstrappers
- Uses stancl's tenant context switching
- Leverages stancl's middleware foundation

## Usage Patterns

### CLI Operations
All tenant operations available through Artisan commands:
```bash
php artisan tenancy:install         # Initial setup
php artisan tenant:create           # Create new tenant
php artisan tenant:list            # List with numbered selection
php artisan tenancy:backup-manager # Interactive backup management
php artisan tenancy:test-performance # Performance testing
```

### Programmatic Usage
```php
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Models\Tenant;

$tenantService = app(TenantService::class);
$tenant = $tenantService->createTenant('Acme Corp', 'acme.example.com');
```

### Livewire Integration
Web-based tenant management through Livewire components with real-time updates.

## Dependencies
- Laravel 10.0+ (Laravel 12 compatible)
- stancl/tenancy ^3.9.1
- MySQL 5.7+ or 8.0+
- Redis (optional, for caching)
- mysqldump and mysql binaries (for backups)

## Common Issues and Solutions

### MySQL Dump Issues
- Requires mysqldump binary in PATH or configured path
- Windows users need MySQL installed or path configured
- Configuration via TENANT_BACKUP_MYSQLDUMP_PATH environment variable

### Middleware Registration
- SimpleTenantMiddleware should be TenantMiddleware
- Middleware groups need proper registration in bootstrap/app.php
- Some middleware depends on stancl/tenancy initialization first

### Database Connectivity
- Auto-creation of tenant databases when missing
- Connection validation and healing
- Migration sync issues resolved with tenancy:fix-tenant-databases

## Performance Considerations
- Cached tenant resolution for improved performance
- Redis caching support for tenant data
- Connection pooling and optimization
- Background job support for heavy operations
