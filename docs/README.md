# Artflow Studio Tenancy Package Documentation

![Tenancy Package](https://img.shields.io/badge/Laravel-Multi--Tenant-brightgreen)
![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple)

Complete documentation for the artflow-studio/tenancy package - a comprehensive multi-tenant solution for Laravel applications.

## 📋 Documentation Index

### Getting Started
- [Installation Guide](./installation/) - Setup and initial configuration
- [Quick Start Guide](./quickstart.md) - Get up and running in 5 minutes
- [Architecture Overview](./architecture/) - Understanding the tenancy system

### Database Configuration
- [**Database Configuration Guide**](./database/README.md) - 🎯 **Main database setup guide**
- [Database Template Configuration](./database/database-template.md) - Connection templates
- [PDO Configuration](./database/pdo-configuration.md) - Critical PDO settings
- [MySQL Optimization](./database/mysql-configuration.md) - Server-level configuration
- [Concurrent Connections](./database/concurrent-connections.md) - Connection pooling

### Commands & Tools
- [Command Reference](./commands/) - All available artisan commands
- [Diagnostic Tools](./diagnostics/) - Performance and health monitoring
- [Interactive Setup](./setup/) - Step-by-step configuration wizards

### Performance & Optimization
- [Performance Guide](./performance/) - Optimization strategies
- [Connection Pooling](./database/concurrent-connections.md) - Advanced connection management
- [Stress Testing](./testing/stress-testing.md) - Load testing procedures
- [Monitoring](./monitoring/) - Health checks and metrics

### Security
- [Security Best Practices](./security/) - Multi-tenant security considerations
- [Database Isolation](./security/database-isolation.md) - Tenant data protection
- [Access Control](./security/access-control.md) - User and role management

### Development
- [API Reference](./api/) - Package API documentation
- [Customization](./customization/) - Extending the package
- [Contributing](./contributing.md) - Development guidelines

## 🚀 Quick Start Commands

### Essential Setup Commands
```bash
# 1. Check and configure database privileges
php artisan tenant:check-privileges --interactive

# 2. Run performance diagnostics
php artisan tenancy:diagnose-performance --check-mysql --detailed

# 3. Test connection pooling
php artisan tenancy:connection-pool status

# 4. Run stress test
php artisan tenancy:stress-test --users=10 --operations=50
```

### Interactive Configuration Wizard
```bash
php artisan tenant:check-privileges --interactive
```

**Options Available:**
- `check-privileges` - Verify database permissions
- `check-pdo-config` - Configure PDO settings
- `add-tenant-template` - Add optimized tenant connection template
- `show-current-config` - Display current configuration
- `test-connection` - Test database connectivity

## 📦 Package Structure

```
artflow-studio/tenancy/
├── src/
│   ├── Commands/                    # Artisan commands
│   │   ├── CheckPrivilegesCommand.php
│   │   ├── TenancyPerformanceDiagnosticCommand.php
│   │   └── TenantConnectionPoolCommand.php
│   ├── Services/                    # Core services
│   │   └── Database/
│   │       └── TenantConnectionPoolManager.php
│   └── TenancyServiceProvider.php   # Service provider
├── docs/                           # Documentation
│   ├── database/                   # Database guides
│   ├── commands/                   # Command documentation
│   ├── performance/                # Performance guides
│   └── security/                   # Security documentation
├── config/                         # Configuration templates
│   └── mysql-tenancy.cnf          # MySQL optimization template
└── stubs/                          # Code generation templates
```

## 🔧 Configuration Overview

### Database Configuration (config/database.php)
The package enhances your database configuration with:

- **Optimized PDO settings** for multi-tenancy
- **Tenant template connections** for dynamic database switching
- **Connection pooling** for improved performance
- **Security configurations** to prevent data leakage

### Tenancy Configuration (config/tenancy.php)
Central configuration for:

- **Database connections** and templates
- **Tenant identification** strategies
- **Performance optimizations**
- **Security settings**

## 🎯 Key Features

### 🏗️ Advanced Database Management
- **Connection Pooling**: Efficient connection reuse with health monitoring
- **PDO Optimization**: Tenant-safe PDO configurations
- **Template Connections**: Optimized connection templates for tenants
- **Auto-Configuration**: Interactive setup wizards

### 🔍 Diagnostic & Monitoring Tools
- **Performance Diagnostics**: Comprehensive system health checks
- **Connection Pool Monitoring**: Real-time pool status and statistics
- **Stress Testing**: Load testing tools for performance validation
- **Health Checks**: Automated monitoring and alerting

### 🛡️ Security & Isolation
- **Tenant Isolation**: Guaranteed data separation between tenants
- **Privilege Management**: Database permission validation
- **Connection Security**: Secure connection configuration
- **Access Control**: Role-based access management

### ⚡ Performance Optimization
- **Concurrent Connections**: Multi-tenant connection management
- **Query Optimization**: Database query performance tuning
- **Resource Management**: Efficient resource allocation
- **Caching Strategies**: Intelligent caching for multi-tenant data

## 📊 Performance Metrics

### Connection Pool Statistics
```bash
php artisan tenancy:connection-pool status --detailed
```

**Key Metrics:**
- Active connections
- Pool utilization
- Connection health
- Response times

### Diagnostic Results
```bash
php artisan tenancy:diagnose-performance --detailed
```

**Checks Include:**
- PDO configuration validation
- MySQL server optimization
- Connection pool health
- Performance bottlenecks

## 🚨 Important Notes

### Critical Multi-Tenant Settings

#### ❌ Never Use Persistent Connections
```php
// WRONG - Can cause tenant data leakage
'options' => [
    PDO::ATTR_PERSISTENT => true, // ❌ NEVER!
]
```

#### ✅ Always Use These Settings
```php
// CORRECT - Safe for multi-tenancy
'options' => [
    PDO::ATTR_PERSISTENT => false,        // ✅ Critical
    PDO::ATTR_EMULATE_PREPARES => false,  // ✅ Security
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // ✅ Error handling
]
```

### MySQL Configuration Requirements
```ini
# Minimum required settings
max_connections = 500
wait_timeout = 300
interactive_timeout = 300
innodb_buffer_pool_size = 1G
```

## 🔗 Useful Links

- [Laravel Multi-Tenancy Best Practices](https://laravel.com/docs/database#multiple-database-connections)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [PDO Configuration Reference](https://www.php.net/manual/en/pdo.configuration.php)

## 📞 Support & Contributing

### Getting Help
1. Check the [FAQ](./faq.md)
2. Review [troubleshooting guides](./troubleshooting/)
3. Run diagnostic commands
4. Check the [issues tracker](https://github.com/artflow-studio/tenancy/issues)

### Contributing
1. Read the [contributing guide](./contributing.md)
2. Follow the [coding standards](./coding-standards.md)
3. Submit pull requests with tests
4. Update documentation as needed

---

**Last Updated**: December 2024  
**Package Version**: 1.0.0  
**Laravel Compatibility**: 11.x, 12.x  
**PHP Requirements**: 8.2+
