# 🛠️ CLI Commands Reference

**ArtFlow Studio Tenancy Package v2.0 - Complete Command Line Interface**

Compatible with: Laravel 10+ & 11+, stancl/tenancy v3+, Livewire 3+

## � Command Categories

1. [Installation Commands](#installation-commands)
2. [Tenant Management](#tenant-management)
3. [Database Operations](#database-operations)
4. [Testing & Validation](#testing--validation)
5. [System Monitoring](#system-monitoring)
6. [Development Tools](#development-tools)

---

## 📦 Installation Commands

### Package Installation
```bash
# Install the package with guided setup
php artisan af-tenancy:install

# Force reinstall (overwrite existing files)
php artisan af-tenancy:install --force

# Quick install for testing environments
php artisan af-tenancy:quick-install
```

---

## 🏢 Tenant Management

### Main Tenant Management Command

The unified command for all tenant operations:

```bash
php artisan tenant:manage {action} [options]
```

### Available Actions

#### **Create Tenant**
```bash
# Interactive creation with prompts
php artisan tenant:manage create

# Create with specific parameters
php artisan tenant:manage create \
  --name="Acme Corporation" \
  --domain="acme.local" \
  --status="active" \
  --notes="New client onboarding"

# Create with custom database name
php artisan tenant:manage create \
  --name="Custom Corp" \
  --domain="custom.local" \
  --database="tenant_custom_db"

# Create and run migrations immediately
php artisan tenant:manage create \
  --name="Demo Company" \
  --domain="demo.local" \
  --migrate \
  --seed

# Create with fresh migrations (drops existing tables)
php artisan tenant:manage create \
  --name="Fresh Start" \
  --domain="fresh.local" \
  --migrate \
  --fresh \
  --seed
```

#### **List Tenants**
```bash
# List all tenants in table format
php artisan tenant:manage list

# Output includes:
# ID | UUID | Name | Domain | Database | Status | Created At
```

#### **Tenant Status Management**
```bash
# Activate a suspended tenant
php artisan tenant:manage activate --tenant=550e8400-e29b-41d4

# Deactivate an active tenant
php artisan tenant:manage deactivate --tenant=550e8400-e29b-41d4

# Check detailed tenant status
php artisan tenant:manage status --tenant=550e8400-e29b-41d4

# Bulk activate all inactive tenants
php artisan tenant:manage activate --all
```

#### **Homepage Management**
```bash
# Enable homepage for specific tenant
php artisan tenant:manage enable-homepage --tenant=550e8400-e29b-41d4

# Disable homepage for specific tenant
php artisan tenant:manage disable-homepage --tenant=550e8400-e29b-41d4

# Interactive homepage management (prompts for tenant)
php artisan tenant:manage enable-homepage
php artisan tenant:manage disable-homepage
```

#### **Delete Tenant**
```bash
# Delete with confirmation prompt
php artisan tenant:manage delete --tenant=550e8400-e29b-41d4

# Force delete without confirmation
php artisan tenant:manage delete --tenant=550e8400-e29b-41d4 --force

# Delete tenant and all associated data
php artisan tenant:manage delete --tenant=550e8400-e29b-41d4 --purge
```

---

## 🗄️ Database Operations

### Migration Management
```bash
# Migrate specific tenant database
php artisan tenant:manage migrate --tenant=550e8400-e29b-41d4

# Migrate all tenants
php artisan tenant:manage migrate-all

# Fresh migration (drops all tables first)
php artisan tenant:manage migrate --tenant=550e8400-e29b-41d4 --fresh

# Migrate with seeders
php artisan tenant:manage migrate --tenant=550e8400-e29b-41d4 --seed

# Rollback tenant migrations
php artisan tenant:manage migrate --tenant=550e8400-e29b-41d4 --rollback
```

### Seeding Operations
```bash
# Seed specific tenant database
php artisan tenant:manage seed --tenant=550e8400-e29b-41d4

# Seed all tenant databases
php artisan tenant:manage seed-all

# Seed with specific seeder class
php artisan tenant:manage seed --tenant=550e8400-e29b-41d4 --class=UserSeeder
```

### Database Utilities
```bash
# Diagnose database issues
php artisan tenancy:diagnose-database

# Fix tenant database connections
php artisan tenancy:fix-tenant-databases

# Warm up database connections
php artisan tenancy:warmup-cache
```

---

## 🧪 Testing & Validation

### System Testing
```bash
# Comprehensive system test
php artisan tenancy:test-system

# Quick system validation
php artisan tenancy:validate

# Test tenant connections
php artisan tenancy:test-connections

# Test middleware functionality
php artisan tenancy:test-middleware
```

### Performance Testing
```bash
# Standard performance test
php artisan tenancy:test-performance

# Enhanced performance test with metrics
php artisan tenancy:test-performance-enhanced

# Performance test with specific parameters
php artisan tenancy:test-performance \
  --concurrent=10 \
  --duration=60 \
  --tenants=5

# Skip deep tests for faster execution
php artisan tenancy:test-performance-enhanced --skip-deep-tests
```

### Isolation Testing
```bash
# Test data isolation between tenants
php artisan tenancy:test-isolation

# Test with specific parameters
php artisan tenancy:test-isolation \
  --tenants=3 \
  --operations=10

# Comprehensive isolation test
php artisan tenancy:test-isolation --comprehensive
```

### Stress Testing
```bash
# High-intensity load testing
php artisan tenancy:stress-test

# Stress test with custom parameters
php artisan tenancy:stress-test \
  --users=50 \
  --duration=300 \
  --tenants=10

# Memory stress test
php artisan tenancy:stress-test --memory-intensive
```

### Comprehensive Testing
```bash
# Run all available tests
php artisan tenancy:comprehensive-test

# Quick comprehensive test
php artisan tenancy:comprehensive-test --quick

# Generate detailed test report
php artisan tenancy:comprehensive-test --report --output=reports/
```

---

## 📊 System Monitoring

### Health Checks
```bash
# Basic system health check
php artisan tenancy:health

# Detailed health check with metrics
php artisan tenancy:health --detailed

# Health check with email alerts
php artisan tenancy:health --alert-email=admin@example.com
```

### Real-time Monitoring
```bash
# Live system monitoring
php artisan tenancy:monitor --live

# Monitor specific metrics
php artisan tenancy:monitor --metric=memory,cpu,connections

# Monitor with refresh interval
php artisan tenancy:monitor --live --interval=5
```

### Statistics & Reports
```bash
# System statistics overview
php artisan tenancy:stats

# Detailed system report
php artisan tenancy:report

# Generate JSON report
php artisan tenancy:report --format=json --output=reports/system-report.json

# Performance metrics report
php artisan tenancy:report --performance --interval=24h
```

---

## 🛠️ Development Tools

### Debug & Diagnostics
```bash
# Debug tenant connection
php artisan tenancy:debug-tenant-connection --tenant=550e8400-e29b-41d4

# Check route configuration
php artisan tenancy:check-route-config

# Validate entire tenancy system
php artisan tenancy:validate-system
```

### Test Data Generation
```bash
# Create test tenants for development
php artisan tenancy:create-test-tenants

# Create specific number of test tenants
php artisan tenancy:create-test-tenants --count=5

# Create test tenants with sample data
php artisan tenancy:create-test-tenants --count=3 --with-data
```

### Cache Management
```bash
# Warm up tenant caches
php artisan tenancy:warmup-cache

# Clear all tenant caches
php artisan tenancy:clear-cache

# Clear cache for specific tenant
php artisan tenancy:clear-cache --tenant=550e8400-e29b-41d4
```

---

## 📝 Command Options Reference

### Global Options
- `--tenant=UUID` - Target specific tenant by UUID
- `--force` - Skip confirmation prompts
- `--quiet` - Suppress output messages
- `--verbose` - Show detailed output
- `--no-interaction` - Run non-interactively

### Tenant Creation Options
- `--name=NAME` - Set tenant name
- `--domain=DOMAIN` - Set primary domain
- `--database=NAME` - Custom database name
- `--status=STATUS` - Set status (active, suspended, blocked, inactive)
- `--notes=TEXT` - Add tenant notes
- `--migrate` - Run migrations after creation
- `--seed` - Run seeders after migration
- `--fresh` - Drop existing tables before migration

### Migration Options
- `--fresh` - Drop all tables before migrating
- `--seed` - Run seeders after migration
- `--rollback` - Rollback migrations
- `--step=N` - Number of migration batches to rollback

### Testing Options
- `--concurrent=N` - Number of concurrent users (default: 10)
- `--duration=SECONDS` - Test duration (default: 60)
- `--tenants=N` - Number of test tenants (default: 3)
- `--skip-deep-tests` - Skip resource-intensive tests
- `--report` - Generate detailed report
- `--output=PATH` - Specify output directory

### Monitoring Options
- `--live` - Enable live monitoring
- `--interval=SECONDS` - Refresh interval for live monitoring
- `--metric=METRICS` - Specific metrics to monitor (comma-separated)
- `--alert-email=EMAIL` - Email for health check alerts

---

## 💡 Usage Examples

### Complete Tenant Setup
```bash
# Create production tenant with all features
php artisan tenant:manage create \
  --name="ACME Corporation" \
  --domain="acme.example.com" \
  --status="active" \
  --notes="Production client - Premium plan" \
  --migrate \
  --seed
```

### Development Workflow
```bash
# Create test environment
php artisan tenancy:create-test-tenants --count=3 --with-data

# Run comprehensive tests
php artisan tenancy:comprehensive-test --quick

# Monitor system performance
php artisan tenancy:monitor --live --interval=10
```

### Production Maintenance
```bash
# Daily health check
php artisan tenancy:health --detailed --alert-email=ops@example.com

# Performance monitoring
php artisan tenancy:test-performance-enhanced --concurrent=20

# System diagnostics
php artisan tenancy:validate-system
```

### Troubleshooting
```bash
# Diagnose connection issues
php artisan tenancy:debug-tenant-connection --tenant=TENANT_UUID

# Fix database problems
php artisan tenancy:fix-tenant-databases

# Clear all caches
php artisan tenancy:clear-cache
```

---

## 🚨 Important Notes

### Command Execution Order
1. Always run system validation before major operations
2. Use `--force` flag carefully in production
3. Test migrations on staging before production
4. Monitor system health after bulk operations

### Production Considerations
- Run tests during maintenance windows
- Use `--quiet` flag in automated scripts
- Always backup before destructive operations
- Monitor resource usage during stress tests

### Development Tips
- Use test tenants for development (`tenancy:create-test-tenants`)
- Enable verbose output for debugging (`--verbose`)
- Generate reports for performance analysis
- Use live monitoring during development

This comprehensive CLI interface provides everything needed for efficient multi-tenant application management with proper monitoring, testing, and maintenance capabilities. \
  --domain="demo.local" \
  --seed --fresh
```

#### **List Tenants**
```bash
# List all tenants
php artisan tenant:manage list

# The output shows:
# - ID, UUID (truncated), Name, Domain, Database, Status, Created date
```

#### **Tenant Status Management**
```bash
# Activate tenant
php artisan tenant:manage activate --tenant=uuid-here

# Deactivate tenant
php artisan tenant:manage deactivate --tenant=uuid-here

# Check tenant status
php artisan tenant:manage status --tenant=uuid-here
```

#### **Homepage Management**
```bash
# Enable homepage for a tenant
php artisan tenant:manage enable-homepage --tenant=uuid-here

# Disable homepage for a tenant  
php artisan tenant:manage disable-homepage --tenant=uuid-here

# Interactive homepage management (will prompt for tenant selection)
php artisan tenant:manage enable-homepage

php artisan tenant:manage disable-homepage
```

#### **Delete Tenant**
```bash
# Delete with confirmation
php artisan tenant:manage delete --tenant=uuid-here

# Force delete without confirmation
php artisan tenant:manage delete --tenant=uuid-here --force
```

---

## 🗄️ Database Operations

### Migrations
```bash
# Migrate single tenant
php artisan tenant:manage migrate --tenant=uuid-here

# Migrate single tenant with fresh start
php artisan tenant:manage migrate --tenant=uuid-here --fresh

# Migrate all active tenants
php artisan tenant:manage migrate-all

# Migrate all with fresh start
php artisan tenant:manage migrate-all --fresh
```

### Seeding
```bash
# Seed single tenant
php artisan tenant:manage seed --tenant=uuid-here

# Seed all active tenants
php artisan tenant:manage seed-all
```

---

## 🧪 Testing & Development Commands

### Create Test Tenants
```bash
# Create 5 test tenants (default)
php artisan tenancy:create-test-tenants

# Create custom number of test tenants
php artisan tenancy:create-test-tenants --count=10

# Create with custom domain prefix
php artisan tenancy:create-test-tenants --domain-prefix=demo --count=5

# Create with sample data
php artisan tenancy:create-test-tenants --with-data

# Create for load testing
php artisan tenancy:create-test-tenants --count=20 --load-test
```

**Test Tenants Created:**
- `test1.local` to `test5.local` (or custom count)
- Each with isolated database
- Ready for performance testing

### Performance Testing
```bash
# Basic performance test
php artisan tenancy:test-performance

# Test with specific parameters
php artisan tenancy:test-performance \
  --concurrent-users=50 \
  --duration=60 \
  --requests-per-user=20
```

**Performance Metrics:**
- Response times (average, median, 95th percentile)
- Memory usage per request
- Database connection times
- Success/failure rates
- Requests per second

---

## 🔍 System Health & Monitoring

### Health Checks
```bash
# System health check
php artisan tenant:manage health

# The health check verifies:
# - Database connectivity
# - Tenant databases accessibility
# - Configuration validity
# - System resources
```

---

## 📋 Command Summary Table

| Command | Purpose | Options |
|---------|---------|---------|
| `tenancy:install` | Install package with guided setup | `--force` |
| `tenant:manage create` | Create new tenant | `--name`, `--domain`, `--database`, `--status`, `--notes`, `--seed`, `--fresh` |
| `tenant:manage list` | List all tenants | None |
| `tenant:manage delete` | Delete tenant | `--tenant`, `--force` |
| `tenant:manage activate` | Activate tenant | `--tenant` |
| `tenant:manage deactivate` | Deactivate tenant | `--tenant` |
| `tenant:manage migrate` | Migrate single tenant | `--tenant`, `--fresh`, `--seed` |
| `tenant:manage migrate-all` | Migrate all tenants | `--fresh` |
| `tenant:manage seed` | Seed single tenant | `--tenant` |
| `tenant:manage seed-all` | Seed all tenants | None |
| `tenant:manage status` | Show tenant status | `--tenant` |
| `tenant:manage enable-homepage` | Enable tenant homepage | `--tenant` |
| `tenant:manage disable-homepage` | Disable tenant homepage | `--tenant` |
| `tenant:manage health` | System health check | None |
| `tenancy:create-test-tenants` | Create test tenants | `--count`, `--domain-prefix`, `--with-data`, `--load-test` |
| `tenancy:test-performance` | Performance testing | `--concurrent-users`, `--duration`, `--requests-per-user` |

---

## 🛠️ Command Examples

### Complete Tenant Setup Workflow
```bash
# 1. Install package
php artisan tenancy:install

# 2. Create your first tenant
php artisan tenant:manage create \
  --name="My Company" \
  --domain="company.local" \
  --seed

# 3. Create test tenants for development
php artisan tenancy:create-test-tenants --count=3 --with-data

# 4. Test performance
php artisan tenancy:test-performance --concurrent-users=10

# 5. Check system health
php artisan tenant:manage health
```

### Development Testing Setup
```bash
# Create test environment
php artisan tenancy:create-test-tenants --count=5 --with-data

# Add to /etc/hosts:
# 127.0.0.1 test1.local
# 127.0.0.1 test2.local
# 127.0.0.1 test3.local
# 127.0.0.1 test4.local
# 127.0.0.1 test5.local

# Test performance
php artisan tenancy:test-performance --concurrent-users=20
```

### Production Management
```bash
# List all tenants
php artisan tenant:manage list

# Migrate all tenants (production safe)
php artisan tenant:manage migrate-all

# Check system health
php artisan tenant:manage health

# Activate/deactivate specific tenant
php artisan tenant:manage activate --tenant=abc-123-def
php artisan tenant:manage deactivate --tenant=abc-123-def
```

---

## ⚠️ Important Notes

### Database Name Handling
- **stancl/tenancy** automatically manages database names
- **Don't use `database_name` column** - it's for legacy compatibility only
- Databases are created automatically with format: `tenant_{uuid}`
- Use the `--database` option only for custom naming when needed

### Tenant Identification
- All commands use **UUID** for tenant identification
- Get UUIDs from `tenant:manage list` command
- UUIDs are shown in truncated format in list view
- Use full UUID for operations

### Safety Features
- Delete operations require confirmation (use `--force` to skip)
- Fresh migrations require explicit `--fresh` flag
- Maximum 50 test tenants can be created at once
- Health checks validate system state before operations

---

## 🆘 Troubleshooting

### Command Not Found
```bash
# Clear Laravel cache
php artisan cache:clear
php artisan config:clear

# Re-register commands
composer dump-autoload
```

### Migration Errors
```bash
# Check tenant status first
php artisan tenant:manage status --tenant=uuid-here

# Try fresh migration
php artisan tenant:manage migrate --tenant=uuid-here --fresh
```

### Performance Issues
```bash
# Check system health
php artisan tenant:manage health

# Run performance test to identify issues
php artisan tenancy:test-performance --concurrent-users=5
```
