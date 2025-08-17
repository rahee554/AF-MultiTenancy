# 🚀 ArtFlow Studio Tenancy - Installation Guide

**Complete installation guide for the Laravel multi-tenancy package built on stancl/tenancy**

## 📋 Prerequisites

- Laravel 10+ (or Laravel 11+)
- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.4+
- Composer 2.0+

## 🚀 Quick Installation

### Method 1: One-Command Installation (Recommended)

```bash
# Install the package
composer require artflow-studio/tenancy

# Run the complete installation
php artisan af-tenancy:install
```

**That's it!** The command will automatically:
- ✅ Publish all configuration files
- ✅ Copy migrations to your project
- ✅ Update environment settings
- ✅ Enable cached lookup optimization
- ✅ Run database migrations
- ✅ Clear application caches

### Method 2: Manual Installation

If you prefer manual control:

```bash
# 1. Install package
composer require artflow-studio/tenancy

# 2. Publish configurations
php artisan vendor:publish --tag=tenancy-config
php artisan vendor:publish --tag=artflow-tenancy-config

# 3. Run migrations
php artisan migrate
php artisan tenants:migrate

# 4. Clear caches
php artisan config:clear
```

### Step 3: Test the Installation

```bash
# Comprehensive system test
php artisan tenancy:test-system

# Or run all available tests
php artisan tenancy:comprehensive-test
```

## ⚙️ Environment Configuration

The installation command automatically adds these to your `.env` file:

```env
# Database Configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql
TENANT_DB_CHARSET=utf8mb4
TENANT_DB_COLLATION=utf8mb4_unicode_ci
TENANT_DB_PERSISTENT=true

# Homepage Management
TENANT_HOMEPAGE_ENABLED=true
TENANT_HOMEPAGE_VIEW_PATH=tenants
TENANT_HOMEPAGE_AUTO_CREATE_DIR=true
TENANT_HOMEPAGE_FALLBACK_REDIRECT=/login

# Cache Configuration (Database cache by default)
TENANT_CACHE_DRIVER=database
TENANT_CACHE_PREFIX=tenant_
TENANT_CACHE_TTL=3600
TENANT_CACHE_STATS_TTL=300

# Migration & Seeding
TENANT_AUTO_MIGRATE=false
TENANT_AUTO_SEED=false

# API Configuration
TENANT_API_KEY=your-secure-api-key-here
TENANT_API_NO_AUTH=false
TENANT_API_ALLOW_LOCALHOST=true
TENANT_API_RATE_LIMIT=true
TENANT_API_RATE_LIMIT_ATTEMPTS=60
TENANT_API_RATE_LIMIT_DECAY=1

# Monitoring & Performance
TENANT_MONITORING_ENABLED=true
TENANT_MONITORING_RETENTION_DAYS=30
TENANT_MONITORING_PERFORMANCE=true

# Backup Configuration
TENANT_BACKUP_ENABLED=false
TENANT_BACKUP_DISK=local
TENANT_BACKUP_RETENTION_DAYS=7

# Stancl/Tenancy Cache Configuration
TENANCY_CACHED_LOOKUP=true
TENANCY_CACHE_TTL=3600
TENANCY_CACHE_STORE=database
```

## 🧪 Verify Installation

```bash
# Health check
php artisan tenancy:health

# Performance test
php artisan tenancy:test-performance

# Create test tenants
php artisan tenancy:create-test-tenants --count=3
```

## 🌐 API Usage

All endpoints require `api_key` query parameter:

```bash
# Health check
curl "http://yourapp.com/api/health?api_key=your-api-key"

# Create tenant
curl -X POST "http://yourapp.com/api/tenants?api_key=your-api-key" \
     -H "Content-Type: application/json" \
     -d '{"name":"Acme Corp","domain":"acme.example.com"}'

# List tenants  
curl "http://yourapp.com/api/tenants?api_key=your-api-key"

# Enable homepage for tenant
curl -X POST "http://yourapp.com/api/tenants/{tenant-id}/enable-homepage?api_key=your-api-key"

# Disable homepage for tenant
curl -X POST "http://yourapp.com/api/tenants/{tenant-id}/disable-homepage?api_key=your-api-key"
```

## 📊 Performance Features

- **63+ req/s** with database switching
- **100% tenant isolation** 
- **Cached lookup** for tenant resolution
- **Persistent connections** for database optimization
- **Redis caching** for maximum performance

## 🔧 Commands Available

```bash
# Installation
php artisan af-tenancy:install

# Health & Testing
php artisan tenancy:health
php artisan tenancy:test-performance
php artisan tenancy:test-comprehensive

# Tenant Management
php artisan tenancy:create-test-tenants
php artisan tenant:manage
```

## 📚 Documentation

- `docs/tenancy/PERFORMANCE_ANALYSIS.md` - Performance optimization guide
- `stubs/tenancy/database.optimized.php` - Optimized database configuration template

## 🎯 Next Steps

1. Update your API key in `.env`
2. Configure Redis for optimal performance  
3. Run health checks to verify installation
4. Create your first tenants via API or commands
5. Review performance analysis documentation

Your high-performance tenancy system is ready! 🚀
