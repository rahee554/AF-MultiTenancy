# 🛠️ CLI Commands Reference

**Artflow Studio Tenancy Package v0.4.6 - Complete Command Line Interface**

---

## 📦 Installation Commands

### Interactive Package Installation
```bash
# Install the package with guided setup
php artisan tenancy:install

# Force reinstall (overwrite existing files)
php artisan tenancy:install --force
```

---

## 🏢 Tenant Management Commands

### Main Tenant Management Command

All tenant operations use the unified `tenant:manage` command:

```bash
php artisan tenant:manage {action} [options]
```

### Available Actions

#### **Create Tenant**
```bash
# Interactive creation
php artisan tenant:manage create

# With parameters
php artisan tenant:manage create --name="Acme Corp" --domain="acme.local"

# With custom database and status
php artisan tenant:manage create \
  --name="Test Company" \
  --domain="test.local" \
  --database="custom_db" \
  --status="active" \
  --notes="Test tenant"

# Create and run migrations + seeders
php artisan tenant:manage create \
  --name="Demo Corp" \
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
