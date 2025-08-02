# 🚀 Artflow Studio Tenancy - Installation Guide

Complete installation guide for the high-performance Laravel multi-tenancy package.

## 📋 Quick Installation

### Method 1: One-Command Installation (Recommended)

```bash
# Install the package
composer require artflow-studio/tenancy

# Run the complete installation
php artisan artflow:tenancy --install
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

# 3. Publish documentation and stubs
php artisan vendor:publish --tag=tenancy-docs
php artisan vendor:publish --tag=tenancy-stubs

# 4. Run migrations
php artisan migrate
php artisan tenants:migrate

# 5. Clear caches
php artisan config:clear
```

## ⚙️ Environment Configuration

Add these to your `.env` file:

```env
# Performance Optimization
TENANCY_CACHED_LOOKUP=true
TENANCY_CACHE_TTL=3600
TENANCY_CACHE_STORE=redis
DB_PERSISTENT=true
DB_CONNECTION_TIMEOUT=5

# API Configuration
ARTFLOW_TENANCY_API_KEY=your-secure-api-key-here

# Redis (Recommended)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
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

All endpoints require `X-API-Key` header:

```bash
# Health check
curl -H "X-API-Key: your-api-key" http://yourapp.com/api/tenancy/health

# Create tenant
curl -X POST -H "X-API-Key: your-api-key" \
     -H "Content-Type: application/json" \
     -d '{"name":"Acme Corp","domain":"acme.example.com"}' \
     http://yourapp.com/api/tenancy/tenants

# List tenants  
curl -H "X-API-Key: your-api-key" http://yourapp.com/api/tenancy/tenants
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
php artisan artflow:tenancy --install

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
