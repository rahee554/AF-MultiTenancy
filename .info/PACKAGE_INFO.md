# Package Information - Quick Reference

## What is this package?

**artflow-studio/tenancy** is an enterprise-grade Laravel multi-tenancy package that extends **stancl/tenancy** with:

- 🎯 **44+ Enhanced Artisan Commands** for complete tenant management
- 🔧 **Interactive CLI Tools** with numbered selection ([0], [1], [2])
- 💾 **Advanced Backup System** with MySQL dump integration
- 🧪 **Comprehensive Testing Suite** for performance and isolation
- 🎨 **Livewire 3 Integration** for web-based management
- ⚡ **Performance Optimization** with Redis caching and monitoring

## Quick Start - Key Commands

### Essential Commands
```bash
# Install and setup the package
php artisan tenancy:install

# Create a new tenant
php artisan tenant:create

# List tenants with numbered selection
php artisan tenant:list

# Interactive backup management
php artisan tenancy:backup-manager

# Test system performance
php artisan tenancy:test-performance

# Validate system health
php artisan tenancy:validate
```

### Emergency/Troubleshooting Commands
```bash
# Fix database connectivity issues
php artisan tenancy:fix-tenant-databases

# Validate and auto-fix system issues
php artisan tenancy:validate --fix

# Clear all caches
php artisan cache:clear; php artisan config:clear; php artisan route:clear
```

## Package Structure at a Glance

```
artflow-studio/tenancy/
├── .info/                    # 📚 THIS DIRECTORY - Package documentation
├── src/
│   ├── Commands/            # 🎯 44+ Artisan commands
│   ├── Services/            # 🔧 Core business logic
│   ├── Models/              # 📊 Enhanced Tenant & Domain models
│   ├── Http/Middleware/     # 🛡️ Enhanced middleware stack
│   └── TenancyServiceProvider.php
├── config/                  # ⚙️ Configuration files
├── database/migrations/     # 📝 Database schema
├── resources/views/         # 🎨 Blade templates
└── routes/                  # 🛣️ Package routes
```

## Key Features Overview

### 1. **Enhanced Tenant Model**
- Extends stancl/tenancy with custom fields: `name`, `status`, `database`, `has_homepage`
- Status management: `active`, `inactive`, `blocked`
- Custom database naming and automatic creation
- Last access tracking and homepage detection

### 2. **User Experience Improvements**
- **Numbered Selection**: All tenant operations use [0], [1], [2] instead of UUIDs
- **Interactive Wizards**: Step-by-step guidance for complex operations
- **Progress Bars**: Real-time feedback for long-running operations
- **Safety Confirmations**: Destructive operations require explicit confirmation

### 3. **Advanced Backup System**
- MySQL dump integration with `mysqldump` and `mysql` binaries
- Compression support (gzip) and structure-only backups
- Tenant-specific backup directories with metadata storage
- Interactive backup manager with restore wizard

### 4. **Comprehensive Testing**
- **Performance Testing**: Concurrent user simulation (up to 50 users)
- **Isolation Testing**: Cross-tenant data leakage detection
- **Stress Testing**: System limit and stability validation
- **Database Testing**: Connection validation and healing

### 5. **Smart Middleware Stack**
- **Universal Middleware**: Works for both central and tenant domains
- **Enhanced Security**: Status checking and access control
- **Session Scoping**: Proper tenant isolation for Livewire
- **Automatic Healing**: Database connectivity repair

## Common Use Cases

### Development Workflow
1. **Setup**: `php artisan tenancy:install`
2. **Create Tenants**: `php artisan tenant:create`
3. **Test System**: `php artisan tenancy:test-performance`
4. **Backup Data**: `php artisan tenancy:backup-manager`

### Production Management
1. **Health Checks**: `php artisan tenancy:validate`
2. **Performance Monitoring**: `php artisan tenancy:test-performance --detailed`
3. **Database Maintenance**: `php artisan tenancy:fix-tenant-databases`
4. **Backup Management**: `php artisan tenancy:backup-manager`

### Troubleshooting Workflow
1. **Identify Issues**: `php artisan tenancy:validate`
2. **Auto-Fix**: `php artisan tenancy:validate --fix`
3. **Manual Repair**: `php artisan tenancy:fix-tenant-databases`
4. **Test Resolution**: `php artisan tenancy:test-performance`

## Integration with stancl/tenancy

This package **extends** stancl/tenancy, not replace it:

- ✅ **Uses** stancl's core tenant resolution and database isolation
- ✅ **Enhances** with additional fields, status management, and UI improvements
- ✅ **Adds** comprehensive CLI tools and backup system
- ✅ **Maintains** full compatibility with stancl/tenancy features

## Configuration Quick Reference

### Environment Variables
```env
# Database
TENANT_DB_PREFIX=tenant_
TENANT_AUTO_CREATE_DB=true

# Backup (IMPORTANT: Set MySQL paths on Windows)
TENANT_BACKUP_MYSQLDUMP_PATH=mysqldump
TENANT_BACKUP_MYSQL_PATH=mysql

# Cache
TENANT_CACHE_DRIVER=redis
TENANT_CACHE_PREFIX=tenant_

# Domain Handling
UNKNOWN_DOMAIN_ACTION=central
```

### Windows MySQL Setup
```env
# If using XAMPP
TENANT_BACKUP_MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
TENANT_BACKUP_MYSQL_PATH="C:\xampp\mysql\bin\mysql.exe"

# If using standalone MySQL
TENANT_BACKUP_MYSQLDUMP_PATH="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe"
TENANT_BACKUP_MYSQL_PATH="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
```

## Recent Updates (v0.6.7+)

### ✨ User Experience Improvements
- **Numbered tenant selection** ([0], [1], [2]) across all commands
- **Enhanced backup manager** with interactive wizards
- **Progress bars** for long-running operations
- **Better error messages** with helpful guidance

### 🔧 Technical Fixes
- **PSR-4 autoloading** issues resolved
- **Interface compatibility** with stancl/tenancy fixed
- **Database connectivity** auto-healing implemented
- **Service provider bindings** added for dependency injection

### 🛡️ Reliability Improvements
- **MySQL binary validation** with helpful error messages
- **Middleware registration** validation fixes
- **Performance testing** with progress indicators
- **Comprehensive validation** with auto-fix capabilities

## Documentation Files in This Directory

1. **PACKAGE_OVERVIEW.md** - Comprehensive package overview and architecture
2. **TECHNICAL_ARCHITECTURE.md** - Deep dive into technical implementation
3. **COMMAND_REFERENCE.md** - Complete command documentation (44 commands)
4. **KNOWN_ISSUES.md** - Current issues and solutions
5. **CONFIGURATION_GUIDE.md** - Complete configuration reference
6. **IMMEDIATE_FIXES.md** - Current fix implementation plan
7. **PACKAGE_INFO.md** - This quick reference file

## Need Help?

### Quick Diagnostics
```bash
# Check system health
php artisan tenancy:validate

# Test basic functionality
php artisan tenant:list

# Verify backup system
php artisan tenancy:backup-manager

# Performance check
php artisan tenancy:test-performance --concurrent-users=5
```

### Common Issues
1. **MySQL dump errors**: Configure binary paths in .env
2. **Middleware registration**: Check bootstrap/app.php middleware setup
3. **Database connectivity**: Run `php artisan tenancy:fix-tenant-databases`
4. **Performance**: Enable Redis caching

### Debug Information
```bash
# Collect debug info
php artisan tenancy:validate > debug-info.txt
php --version >> debug-info.txt
composer show | findstr tenancy >> debug-info.txt
```

This package provides enterprise-grade multi-tenancy for Laravel with an emphasis on developer experience, reliability, and comprehensive tooling.
