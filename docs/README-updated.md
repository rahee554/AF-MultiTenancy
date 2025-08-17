# 🏢 ArtFlow Studio Tenancy

[![Latest Version on Packagist](https://img.shields.io/packagist/v/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)
[![Total Downloads](https://img.shields.io/packagist/dt/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)
[![PHP Version Require](https://poser.pugx.org/artflow-studio/tenancy/require/php)](https://packagist.org/packages/artflow-studio/tenancy)

**Enterprise-grade Laravel multi-tenancy package built on stancl/tenancy with enhanced CLI tools, real-time monitoring, and comprehensive admin interface.**

## ✨ Features

- 🏗️ **Built on stancl/tenancy v3.9+** - Rock-solid foundation with proven multi-tenancy
- 🔥 **30+ CLI Commands** - Comprehensive management, testing, and monitoring tools
- 📊 **Real-time Monitoring** - System stats, tenant analytics, and performance metrics
- 🎯 **Admin Interface** - Complete web UI for tenant management
- 🚀 **REST API** - Full tenant CRUD and system monitoring API
- 🛡️ **Enhanced Security** - Advanced middleware, authentication, and authorization
- ⚡ **Performance Optimized** - Multi-layer caching, connection pooling, stress testing
- 🧪 **Comprehensive Testing** - Isolation, performance, stress, and system validation
- 🎨 **Livewire 3 Compatible** - Proper session scoping and component isolation

## 🚀 Quick Start

### Installation

```bash
# Install the package
composer require artflow-studio/tenancy

# Run one-command setup
php artisan af-tenancy:install

# Verify installation
php artisan tenancy:test-system
```

### Create Your First Tenant

```bash
# Interactive tenant creation
php artisan tenant:manage create

# Direct creation
php artisan tenant:manage create \
  --name="Acme Corporation" \
  --domain="acme.local" \
  --migrate \
  --seed
```

### Access Admin Interface

Visit `/tenancy` on your central domain to access the comprehensive admin interface for managing tenants, monitoring performance, and viewing analytics.

## 📁 Package Structure

```
artflow-studio/tenancy/
├── src/
│   ├── Commands/              # 30+ CLI commands organized by category
│   │   ├── Database/          # Database operations (migrate, fix, test)
│   │   ├── Tenancy/           # Tenant management (create, install, health)
│   │   └── Testing/           # Testing suite (performance, isolation, stress)
│   ├── Http/
│   │   ├── Controllers/       # API and web controllers
│   │   │   ├── TenantApiController.php        # REST API
│   │   │   ├── TenantViewController.php       # Web interface
│   │   │   └── RealTimeMonitoringController.php # Analytics
│   │   └── Middleware/        # Enhanced middleware stack
│   ├── Models/                # Enhanced Tenant and Domain models
│   └── Services/              # Core business logic
├── routes/af-tenancy.php      # Admin and API routes
├── resources/views/           # Admin interface views
├── config/                    # Configuration files
└── docs/                      # Comprehensive documentation
```

## 🛠️ Available Commands

### Tenant Management
```bash
# Unified tenant management
php artisan tenant:manage {action}

# Database operations
php artisan tenant:db {operation}

# Create test tenants
php artisan tenancy:create-test-tenants --count=5
```

### Testing & Validation
```bash
# System validation
php artisan tenancy:validate
php artisan tenancy:test-system

# Performance testing
php artisan tenancy:test-performance-enhanced
php artisan tenancy:stress-test

# Isolation testing
php artisan tenancy:test-isolation
```

### Monitoring & Health
```bash
# Health checks
php artisan tenancy:health

# Database diagnostics
php artisan tenancy:diagnose
php artisan tenancy:fix-databases
```

## 🎯 Admin Interface

The package includes a comprehensive admin interface accessible at `/tenancy`:

- **Dashboard** - System overview and key metrics
- **Tenant Management** - CRUD operations with advanced filtering
- **Real-time Monitoring** - Live system and tenant statistics
- **Database Operations** - Migration, seeding, and maintenance tools
- **Performance Analytics** - Charts, graphs, and usage reports
- **Health Monitoring** - System status and alerts

## 🔌 API Endpoints

Complete REST API for programmatic access:

```bash
# Tenant CRUD
GET    /api/tenancy/tenants
POST   /api/tenancy/tenants
GET    /api/tenancy/tenants/{id}
PUT    /api/tenancy/tenants/{id}
DELETE /api/tenancy/tenants/{id}

# System monitoring
GET    /api/tenancy/health
GET    /api/tenancy/stats
GET    /api/tenancy/monitor/system
GET    /api/tenancy/monitor/tenants/{id?}
```

## 📊 Monitoring & Analytics

### Real-time System Stats
- Active/inactive tenant counts
- Database connection metrics
- Memory and CPU usage
- Request performance metrics

### Tenant Analytics
- Individual tenant statistics
- Usage patterns and trends
- Performance bottlenecks
- Resource utilization graphs

### Performance Monitoring
- Query execution times
- Memory consumption patterns
- Connection pooling efficiency
- Cache hit/miss ratios

## ⚡ Performance Features

- **Multi-layer Caching** - Memory, Redis, and database caching
- **Connection Pooling** - Optimized database connections
- **Smart Domain Resolution** - Efficient tenant routing
- **Asset Optimization** - Bypass tenancy for static files
- **Stress Testing** - Validate system under load

## 🧪 Testing Suite

Comprehensive testing capabilities:

- **System Validation** - Complete package health check
- **Performance Testing** - Load and response time analysis
- **Stress Testing** - High-intensity load simulation
- **Isolation Testing** - Verify tenant data separation
- **Database Testing** - Connection and integrity validation

## 📚 Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [Architecture Overview](docs/ARCHITECTURE.md)
- [Commands Reference](docs/COMMANDS.md)
- [API Documentation](docs/API.md)
- [Middleware Guide](docs/MIDDLEWARE_QUICK_REFERENCE.md)
- [Features Overview](docs/FEATURES.md)

## 🔧 Configuration

The package provides two main configuration files:

- `config/tenancy.php` - Core stancl/tenancy configuration
- `config/artflow-tenancy.php` - Package enhancements and features

## 🛡️ Security Features

- API key authentication
- Tenant isolation validation
- Secure middleware stack
- Input sanitization
- SQL injection prevention
- Connection security

## 🎯 Requirements

- PHP 8.0+
- Laravel 10.0+
- stancl/tenancy 3.9.1+
- MySQL 8.0+ or MariaDB 10.4+

## 📈 Roadmap (v0.7.2.4)

See [TODO.md](docs/TODO.md) for detailed development priorities and upcoming features.

## 🤝 Contributing

Contributions are welcome! Please see our contributing guidelines for details.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🏢 About ArtFlow Studio

ArtFlow Studio specializes in enterprise Laravel applications and SaaS solutions. Visit [artflow-studio.com](https://artflow-studio.com) for more information.
