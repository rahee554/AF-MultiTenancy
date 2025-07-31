# AF-MultiTenancy Documentation

**Complete Laravel Multi-Tenancy Solution built on stancl/tenancy**

## 📚 Documentation Structure

### 🚀 Getting Started
- **[Installation Guide](installation/INSTALLATION_GUIDE.md)** - Complete setup instructions
- **[Installation Troubleshooting](installation/INSTALLATION_TROUBLESHOOTING.md)** - Common setup issues
- **[Quick Reference](guides/DEVELOPER_QUICK_REFERENCE.md)** - Developer quick start

### ✨ Features
- **[Complete Features Guide](features/COMPLETE_FEATURES_GUIDE.md)** - All package features
- **[Feature Overview](features/FEATURES.md)** - Feature summary
- **[Redis Integration](features/REDIS.md)** - Caching and performance

### 📖 Guides
- **[Integration Guide](guides/COMPLETE_INTEGRATION_GUIDE.md)** - Complete setup walkthrough
- **[Central Domain Guide](guides/CENTRAL_DOMAIN_GUIDE.md)** - Managing central domains
- **[Middleware Guide](guides/MIDDLEWARE_USAGE_GUIDE.md)** - Middleware configuration
- **[Middleware Reference](guides/MIDDLEWARE_QUICK_REFERENCE.md)** - Quick middleware setup

### 🔌 API Reference
- **[API Documentation](api/API.md)** - REST API endpoints
- **[Commands Reference](api/COMMANDS.md)** - CLI commands

### 🛠 Development
- **[Architecture](development/ARCHITECTURE.md)** - System architecture
- **[TODO & Roadmap](development/TODO.md)** - Planned features

## 🎯 Quick Navigation

### For New Users
1. Start with [Installation Guide](installation/INSTALLATION_GUIDE.md)
2. Read [Complete Features Guide](features/COMPLETE_FEATURES_GUIDE.md)
3. Follow [Integration Guide](guides/COMPLETE_INTEGRATION_GUIDE.md)

### For Developers
1. Check [Developer Quick Reference](guides/DEVELOPER_QUICK_REFERENCE.md)
2. Review [Middleware Guide](guides/MIDDLEWARE_USAGE_GUIDE.md)
3. Explore [API Documentation](api/API.md)

### For System Administrators
1. Review [Architecture](development/ARCHITECTURE.md)
2. Configure [Redis Integration](features/REDIS.md)
3. Check [Commands Reference](api/COMMANDS.md)

## 🔧 Universal Middleware

This package uses **Universal Middleware** that works for both central and tenant contexts:

```php
// For web routes (both central and tenant)
Route::middleware(['universal.web'])->group(function () {
    // Your routes here
});

// For authenticated routes (both central and tenant) 
Route::middleware(['universal.auth'])->group(function () {
    // Protected routes here
});
```

The universal middleware automatically:
- ✅ Identifies tenant context
- ✅ Initializes proper database connections
- ✅ Handles authentication for both central and tenant users
- ✅ Manages session scoping
- ✅ Provides maintenance mode support

## 🚀 Key Features

- **🔍 Cached Tenant Lookup** - Fast tenant resolution with Redis
- **🔧 Maintenance Mode** - Per-tenant maintenance with IP whitelisting
- **⚡ Early Identification** - Multi-strategy tenant identification
- **🔐 Sanctum Integration** - Tenant-aware API authentication
- **📊 Real-time Monitoring** - Performance metrics and health checks
- **🛠 30+ CLI Commands** - Comprehensive management tools
- **🌐 REST API** - Full-featured tenant management API
- **📱 Livewire 3 Ready** - Complete Livewire integration

## 🧪 Testing

Run comprehensive tests:
```bash
# Test all features
php artisan tenancy:test-comprehensive --verbose

# Test specific features
php artisan tenancy:test-cached-lookup
php artisan tenancy:test-sanctum
```

## 📋 Quick Commands

```bash
# Create tenant
php artisan tenant:create example.com

# Maintenance mode
php artisan tenants:maintenance enable --tenant=example

# Database operations
php artisan tenant:db migrate --tenant=example

# Performance testing
php artisan tenancy:test-comprehensive --performance
```

## 🆘 Need Help?

1. Check [Installation Troubleshooting](installation/INSTALLATION_TROUBLESHOOTING.md)
2. Review [Developer Quick Reference](guides/DEVELOPER_QUICK_REFERENCE.md)  
3. Run diagnostic commands with `--verbose` flag
4. Check the [TODO & Roadmap](development/TODO.md) for known issues

---

**Version:** 1.0.0 | **Laravel:** 10+ | **PHP:** 8.1+ | **Built on:** stancl/tenancy v3
