# Command Reference

This document provides a comprehensive reference for all available commands in the AF-MultiTenancy package.

## Core Commands

### Tenant Creation
```bash
php artisan tenant:create
```
**Purpose**: Create new tenants with interactive mode selection
- Interactive wizard for tenant data collection
- Localhost and FastPanel creation modes
- Database privilege checking
- Automatic domain setup

### Tenant Management
```bash
php artisan tenant:manage [action]
```
**Purpose**: Manage existing tenants (creation removed - use tenant:create)
- `list` - List all tenants
- `activate` - Activate a tenant
- `deactivate` - Deactivate a tenant
- `enable-homepage` - Enable homepage for tenant
- `disable-homepage` - Disable homepage for tenant
- `status` - Show detailed tenant status
- `health` - Check system health

### Database Operations
```bash
php artisan tenant:db [operation]
```
**Purpose**: Database operations for tenants
- `migrate` - Run migrations for tenant(s)
- `seed` - Run seeders for tenant(s)
- `rollback` - Rollback migrations for tenant(s)
- `fresh` - Fresh migration for tenant(s)
- `status` - Show migration status

## Testing Commands

### Comprehensive Testing
```bash
php artisan tenancy:test
```
**Purpose**: Run comprehensive tenancy system tests

### Authentication Testing
Located in `Commands/Testing/Auth/`:

```bash
# Test tenant authentication flow
php artisan af-tenancy:test-auth [domain]

# Test authentication context
php artisan af-tenancy:test-login [domain] [email]

# Debug authentication flow
php artisan af-tenancy:debug-auth [domain]

# Test Sanctum integration
php artisan tenancy:test-sanctum [--tenant=uuid]
```

### Database Testing
Located in `Commands/Testing/Database/`:

```bash
# Test tenant isolation
php artisan tenancy:test-isolation [--tenants=5] [--operations=100] [--detailed]

# Fix tenant database issues
php artisan tenancy:fix-tenant-databases [--force]

# Test cached domain lookup
php artisan tenancy:test-cached-lookup [--domain=example.com] [--benchmark] [--warm-cache] [--clear-cache]
```

### Performance Testing
Located in `Commands/Testing/Performance/`:

```bash
# Basic performance testing
php artisan tenancy:test-performance [--tenants=10] [--operations=100] [--detailed]

# Enhanced performance testing
php artisan tenancy:test-performance-enhanced [--requests=100] [--concurrent=5] [--detailed]

# Stress testing
php artisan tenancy:stress-test [--tenants=10] [--operations=1000] [--concurrent=5]
```

### Redis Testing
Located in `Commands/Testing/Redis/`:

```bash
# Test Redis functionality
php artisan tenancy:test-redis [--detailed]

# Redis stress testing
php artisan tenancy:redis-stress-test [--operations=1000] [--concurrent=10]

# Install Redis support
php artisan tenancy:install-redis [--force]

# Enable Redis for tenancy
php artisan tenancy:enable-redis [--restart-services]

# Configure Redis settings
php artisan tenancy:configure-redis [--interactive]
```

### System Testing
Located in `Commands/Testing/System/`:

```bash
# Test system components
php artisan tenancy:test-system [--detailed]

# Check server compatibility
php artisan tenant:server-check [--detailed] [--fix-issues]

# Validate tenancy system
php artisan tenancy:validate [--fix] [--detailed]

# Test middleware functionality
php artisan af-tenancy:test-middleware
```

## Installation & Maintenance Commands

### Installation
```bash
php artisan tenancy:install
```
**Purpose**: Install tenancy system with guided setup

### Maintenance
```bash
# Warm up caches
php artisan tenancy:warmup-cache

# System health check
php artisan tenancy:health-check

# Tenant maintenance mode
php artisan tenancy:maintenance [on|off] [--tenant=uuid]
```

## Command Usage Examples

### Creating a New Tenant
```bash
# Interactive mode
php artisan tenant:create

# With FastPanel integration
php artisan tenant:create --mode=fastpanel

# Localhost mode
php artisan tenant:create --mode=localhost
```

### Managing Tenants
```bash
# List all tenants
php artisan tenant:manage list

# Check tenant status
php artisan tenant:manage status --tenant=uuid-here

# Activate a tenant
php artisan tenant:manage activate --tenant=uuid-here
```

### Database Operations
```bash
# Migrate specific tenant
php artisan tenant:db migrate --tenant=uuid-here

# Migrate all tenants
php artisan tenant:db migrate --all

# Seed specific tenant
php artisan tenant:db seed --tenant=uuid-here
```

### Testing
```bash
# Run comprehensive tests
php artisan tenancy:test

# Test authentication for specific domain
php artisan af-tenancy:test-auth tenancy1.local

# Performance test with custom parameters
php artisan tenancy:test-performance --tenants=5 --operations=50 --detailed
```

## Command Organization

Commands are organized into logical folders:

- **Core/**: Essential tenant creation commands
- **Tenancy/**: Tenant management commands  
- **Database/**: Database-related operations
- **Testing/Auth/**: Authentication testing
- **Testing/Database/**: Database testing
- **Testing/Performance/**: Performance testing
- **Testing/Redis/**: Redis testing
- **Testing/System/**: System testing
- **Installation/**: Installation commands
- **Maintenance/**: Maintenance commands

## Best Practices

1. **Always use tenant:create for new tenants** - Don't use the deprecated tenant:manage create
2. **Use tenant:db for database operations** - Centralized database management
3. **Test thoroughly** - Use the comprehensive test suite before production
4. **Check system health** - Regular health checks with tenant:manage health
5. **Monitor performance** - Use performance testing commands regularly

## Troubleshooting

### Common Issues

1. **Database permission errors**: Ensure your database user has CREATE/DROP privileges
2. **Tenant not found**: Check tenant UUID and domain configuration
3. **Cache issues**: Clear tenant cache with appropriate testing commands
4. **Redis connection**: Verify Redis configuration and connectivity

### Debug Commands

Use these commands for debugging:
- `php artisan af-tenancy:debug-auth [domain]` - Debug authentication flow
- `php artisan tenancy:validate --detailed` - Detailed system validation
- `php artisan tenant:server-check --detailed` - Server compatibility check
- `php artisan tenancy:test-system --detailed` - System component testing
