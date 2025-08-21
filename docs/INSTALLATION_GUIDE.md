# 🚀 ArtflowStudio Tenancy Package - Installation & Setup Guide

## Quick Setup Checklist

### ✅ **Step 1: Environment Configuration**

Add the following to your `.env` file:

```env
# 🔐 Database Root Credentials (REQUIRED for tenant creation)
DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=your_mysql_root_password

# 🏢 Tenant Database Configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql
TENANT_DB_CHARSET=utf8mb4
TENANT_DB_COLLATION=utf8mb4_unicode_ci

# 🏠 Tenant Features
TENANT_HOMEPAGE_ENABLED=1
TENANT_AUTO_MIGRATE=0
TENANT_AUTO_SEED=0

# ⚡ Performance & Caching
TENANT_CACHE_DRIVER=database
TENANT_CACHE_PREFIX=tenant_
TENANT_CACHE_TTL=3600

# 📊 Monitoring (Optional)
TENANT_MONITORING_ENABLED=1
TENANT_MONITORING_RETENTION_DAYS=30
```

### ✅ **Step 2: Install & Configure**

```bash
# Install the package
composer require artflow-studio/tenancy

# Publish configurations and run migrations
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider"
php artisan migrate

# Test your setup
php artisan tenancy:validate
```

### ✅ **Step 3: Create Your First Tenant**

```bash
php artisan tenant:create
```

**Follow the interactive wizard:**
1. Enter tenant name and domain
2. Choose creation mode (localhost/FastPanel)
3. Configure database settings
4. Run migrations and seeders

### ✅ **Step 4: Verify Everything Works**

```bash
# Check tenant was created
php artisan tenant:list

# Test database connections
php artisan tenancy:test-connections

# Verify FastPanel integration (if used)
/usr/local/fastpanel2/fastpanel databases list
```

---

## 🔧 Configuration Details

### Database Root Credentials

**Why needed?**
- Creates tenant databases automatically
- Grants MySQL privileges to database users
- Ensures phpMyAdmin/FastPanel auto-login access
- Enables seamless FastPanel integration

**Security:**
- Credentials used only during tenant creation
- Temporary connection switching for privilege operations
- All tenant credentials encrypted when stored

### Database Naming

- **Auto-generated**: `tenant_1a2b3c4d5e6f7g8h` (UUID-based)
- **Custom names**: `mycompany` → `tenant_mycompany` (prefix applied automatically)
- **Prefix configuration**: Controlled by `TENANT_DB_PREFIX` environment variable

### FastPanel Integration

**Requirements:**
- FastPanel CLI available at `/usr/local/fastpanel2/fastpanel`
- MySQL root credentials in environment
- User access to FastPanel management

**Features:**
- Automatic database creation via FastPanel
- Database user assignment and privilege management
- Panel owner assignment and site linking
- phpMyAdmin auto-login compatibility

---

## 🧪 Testing & Validation

### Basic Health Check
```bash
php artisan tenancy:validate
```

### Database Connection Testing
```bash
php artisan tenancy:test-connections
```

### Performance Testing
```bash
php artisan tenancy:test-performance-enhanced --concurrent-users=5
```

### Create Test Tenants
```bash
php artisan tenancy:create-test-tenants
```

---

## 🛠️ Troubleshooting

### Common Issues

**1. "Access denied for user" during tenant creation**
```
Solution: Add DB_ROOT_USERNAME and DB_ROOT_PASSWORD to .env
Alternative: GRANT CREATE ON *.* TO 'your_app_user'@'localhost';
```

**2. "Custom database name without prefix"**
```
Solution: System automatically applies TENANT_DB_PREFIX
Example: Input 'mydb' becomes 'tenant_mydb'
```

**3. "Database not visible in phpMyAdmin"**
```
Solution: Check if privileges were granted
Verify: SHOW GRANTS FOR 'username'@'localhost';
```

**4. "FastPanel CLI not found"**
```
Solution: Ensure FastPanel installed at /usr/local/fastpanel2/fastpanel
Check: ls -la /usr/local/fastpanel2/fastpanel
```

### Debug Commands

```bash
# View detailed logs
tail -f storage/logs/laravel.log

# Test specific tenant connection
php artisan tenant:db your-tenant-domain.com

# Validate tenant isolation
php artisan tenancy:test-isolation --tenants=3
```

---

## 📚 Quick Reference

### Essential Commands

| Command | Purpose |
|---------|---------|
| `tenant:create` | Create new tenant with wizard |
| `tenant:list` | List all tenants |
| `tenant:manage` | Interactive tenant management |
| `tenancy:validate` | System health check |
| `tenancy:test-connections` | Test all database connections |

### Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_ROOT_USERNAME` | - | MySQL root user (required) |
| `DB_ROOT_PASSWORD` | - | MySQL root password (required) |
| `TENANT_DB_PREFIX` | `tenant_` | Database name prefix |
| `TENANT_HOMEPAGE_ENABLED` | `1` | Enable tenant homepages |
| `TENANT_CACHE_DRIVER` | `database` | Cache driver for tenants |

---

## ✅ Success Indicators

After setup, you should see:

- ✅ `php artisan tenancy:validate` passes all checks
- ✅ Tenant databases created with proper prefixes
- ✅ MySQL privileges granted to database users
- ✅ FastPanel databases visible and accessible
- ✅ phpMyAdmin auto-login shows tenant databases
- ✅ Migrations and seeders run successfully

---

**🎉 You're all set! Your multi-tenant application is ready for production.**

For advanced configuration and API usage, see the main [README.md](README.md) documentation.

---

**Support**: Check logs, verify environment variables, test with debug mode enabled  
**Version**: Compatible with Laravel 10.x, 11.x | PHP 8.1+  
**Last Updated**: August 21, 2025
