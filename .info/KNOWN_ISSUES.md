# Known Issues and Solutions

## Current Active Issues

### 1. MySQL Dump Binary Missing (HIGH PRIORITY)

**Problem**: 
```
❌ MySQL dump failed: '"mysqldump"' is not recognized as an internal or external command
```

**Root Cause**: 
Windows systems don't have MySQL binaries in PATH by default.

**Solutions**:

#### Option A: Install MySQL Client Tools
1. Download MySQL Community Server or MySQL Client tools
2. Add MySQL bin directory to Windows PATH
3. Verify with: `mysqldump --version`

#### Option B: Configure Custom Path
Set environment variable in `.env`:
```env
TENANT_BACKUP_MYSQLDUMP_PATH="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe"
TENANT_BACKUP_MYSQL_PATH="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
```

#### Option C: Use XAMPP/WAMP Tools
If using XAMPP/WAMP, add their MySQL binaries:
```env
TENANT_BACKUP_MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
TENANT_BACKUP_MYSQL_PATH="C:\xampp\mysql\bin\mysql.exe"
```

**Code Location**: `src/Services/TenantBackupService.php`

---

### 2. Middleware Registration Mismatch (MEDIUM PRIORITY)

**Problem**:
```
❌ tenant → Stancl\Tenancy\Middleware\InitializeTenancyByDomain 
    (expected: ArtflowStudio\Tenancy\Http\Middleware\SimpleTenantMiddleware)
❌ SimpleTenantMiddleware - NOT FOUND
```

**Root Cause**: 
Validation command expects `SimpleTenantMiddleware` but actual class is `TenantMiddleware`.

**Solution**:
Update validation command to check for correct middleware:
- Look for `TenantMiddleware` instead of `SimpleTenantMiddleware`
- Update middleware alias registration
- Fix validation expectations

**Code Location**: 
- `src/Commands/Testing/System/ValidateTenancySystemCommand.php`
- `src/Http/Middleware/TenantMiddleware.php`

---

### 3. Dependency Injection Issue (MEDIUM PRIORITY)

**Problem**:
```
Target [Stancl\Tenancy\Contracts\TenantDatabaseManager] is not instantiable
```

**Root Cause**: 
Service provider binding issue for stancl/tenancy contract.

**Solution**:
Add proper service bindings in `TenancyServiceProvider`:
```php
$this->app->bind(
    \Stancl\Tenancy\Contracts\TenantDatabaseManager::class,
    \Stancl\Tenancy\Database\DatabaseManager::class
);
```

**Code Location**: `src/TenancyServiceProvider.php`

---

### 4. Performance Testing Progress Bars (LOW PRIORITY)

**Problem**: 
Concurrent user testing (50 users) takes 190+ seconds with no progress indication.

**Current Behavior**:
```
🧪 Running Deep Concurrent CRUD Isolation Test on users table...
Simulating 50 concurrent users per tenant, 30 operations each...
[Long wait with no feedback]
✅ Deep concurrent CRUD isolation PASSED (190.9s)
```

**Solution**: 
Add progress bars and status updates for long-running operations.

**Implementation**:
```php
$progressBar = $this->output->createProgressBar($totalOperations);
$progressBar->setFormat('verbose');
$progressBar->start();

// Update progress during operations
$progressBar->advance();
$progressBar->setMessage('Processing tenant ' . $tenant->name);
```

**Code Location**: `src/Commands/Testing/Performance/TestPerformanceCommand.php`

---

### 5. Tenant Domain Resolution Issues (MEDIUM PRIORITY)

**Problem**:
```
❌ No tenant found for domain: tenancy1.local
```

**Root Cause**: 
Domain not properly configured or DNS resolution issues.

**Solutions**:

#### Check Domain Configuration:
```bash
php artisan tenant:list --detailed
```

#### Add to hosts file (Windows):
```
# C:\Windows\System32\drivers\etc\hosts
127.0.0.1 tenancy1.local
127.0.0.1 tenancy2.local
```

#### Verify domain records:
```bash
php artisan tenancy:validate
```

---

### 6. Middleware Group Registration (LOW PRIORITY)

**Problem**:
```
❌ tenant group not found
❌ smart.tenant → NOT REGISTERED
❌ tenancy.api → NOT REGISTERED
```

**Solution**:
Update `bootstrap/app.php` to register middleware groups:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('tenant', [
        ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware::class,
    ]);
    
    $middleware->alias([
        'smart.tenant' => ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware::class,
        'tenancy.api' => ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware::class,
    ]);
})
```

## Troubleshooting Guide

### Database Connectivity Issues

**Symptoms**: 
- Connection timeouts
- "Database not found" errors
- Migration failures

**Diagnosis**:
```bash
php artisan tenancy:validate
php artisan tenancy:fix-tenant-databases
```

**Common Fixes**:
1. Run database fix command: `php artisan tenancy:fix-tenant-databases`
2. Check database permissions
3. Verify connection configurations
4. Ensure MySQL service is running

### Performance Issues

**Symptoms**:
- Slow tenant operations
- Memory exhaustion
- Timeout errors

**Diagnosis**:
```bash
php artisan tenancy:test-performance --detailed
```

**Optimizations**:
1. Enable Redis caching
2. Optimize database indexes
3. Use background job processing
4. Increase PHP memory limits

### Cache Issues

**Symptoms**:
- Stale tenant data
- Cache key conflicts
- Performance degradation

**Solutions**:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan tenancy:switch-cache redis
```

### Migration Issues

**Symptoms**:
- "Table already exists" errors
- Missing migration files
- Inconsistent tenant schemas

**Solutions**:
```bash
php artisan tenancy:migrate --fresh
php artisan tenancy:validate --fix
```

## Environment-Specific Issues

### Windows Development

**Common Issues**:
1. Path separator conflicts (`\` vs `/`)
2. MySQL binary availability
3. Permission issues
4. Long path name limitations

**Solutions**:
- Use forward slashes in configuration
- Install MySQL tools or configure paths
- Run PowerShell as Administrator
- Enable long path support in Windows

### Production Deployment

**Checklist**:
- [ ] MySQL binaries available
- [ ] Proper file permissions set
- [ ] Redis configured (if using caching)
- [ ] Background job queue configured
- [ ] Backup storage properly configured
- [ ] Domain DNS properly configured

### Docker Environment

**Additional Considerations**:
- MySQL container connectivity
- Volume mounts for backup storage
- Container networking for domain resolution
- Environment variable passing

## Monitoring and Prevention

### Health Checks
Set up regular health checks:
```bash
# Add to cron
0 */6 * * * php /path/to/artisan tenancy:validate --fix
0 2 * * * php /path/to/artisan tenancy:cleanup-orphaned
```

### Backup Validation
Regular backup testing:
```bash
# Weekly backup validation
0 1 * * 0 php /path/to/artisan tenancy:test-backup-restore
```

### Performance Monitoring
Monthly performance audits:
```bash
php artisan tenancy:test-performance --detailed > performance-report.txt
```

## Getting Help

### Debug Information Collection
When reporting issues, collect:
```bash
php artisan tenancy:validate > debug-info.txt
php --version >> debug-info.txt
composer show | grep tenancy >> debug-info.txt
```

### Log File Locations
- Laravel logs: `storage/logs/laravel.log`
- Tenant-specific logs: Check tenant context
- Backup logs: Stored in backup metadata

### Version Information
```bash
composer show artflow-studio/tenancy
composer show stancl/tenancy
php artisan --version
```
