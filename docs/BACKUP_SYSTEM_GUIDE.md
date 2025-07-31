# Tenant Backup & Restore System

## Overview

The AF-MultiTenancy package includes a comprehensive backup and restore system for tenant databases with the following features:

- **Isolated Storage**: Each tenant has its own backup folder
- **MySQL Integration**: Direct mysqldump/mysql support for full database control
- **Interactive Commands**: User-friendly command-line interface
- **Compression Support**: Automatic gzip compression for space efficiency
- **Retention Management**: Automatic cleanup of old backups
- **Flexible Restore**: Restore from any backup with numbered selection

## Quick Start

### 1. Setup Filesystem Disk

Add the backup disk to your `config/filesystems.php`:

```php
'disks' => [
    // ... existing disks

    'tenant-backups' => [
        'driver' => 'local',
        'root' => storage_path('app/tenant-backups'),
        'permissions' => [
            'file' => ['public' => 0644, 'private' => 0644],
            'dir' => ['public' => 0755, 'private' => 0755],
        ],
        'throw' => true,
    ],
],
```

### 2. Configure Environment Variables

Add these to your `.env` file:

```bash
# Enable backup functionality
TENANT_BACKUP_ENABLED=true
TENANT_BACKUP_COMPRESS=true
TENANT_BACKUP_RETENTION_DAYS=30

# MySQL paths (adjust for your system)
TENANT_BACKUP_MYSQLDUMP_PATH=mysqldump
TENANT_BACKUP_MYSQL_PATH=mysql
```

### 3. Use Interactive Backup Manager

```bash
php artisan tenant:backup-manager
```

This opens an interactive menu with options to:
- 📦 Create Backup
- 🔄 Restore from Backup
- 📋 List Backups
- 🧹 Cleanup Old Backups
- ⚙️ View Settings

## Command Usage

### Create Backups

The interactive backup wizard allows you to:

1. **Select Scope**: Single tenant, multiple tenants, or all tenants
2. **Configure Options**: Compression, structure-only, routines, triggers, events
3. **Review Summary**: Confirm settings before execution
4. **Monitor Progress**: Real-time feedback during backup creation

### Restore from Backups

The restore wizard provides:

1. **Tenant Selection**: Choose which tenant to restore
2. **Backup Listing**: View all available backups with details
3. **Numbered Selection**: Pick backup by number (0 = most recent)
4. **Safety Confirmation**: Type 'CONFIRM' for destructive operations
5. **Progress Monitoring**: Real-time restore status

### List and Manage Backups

View backup information:
- **Single Tenant**: Detailed listing for one tenant
- **All Tenants**: Overview of all tenant backups
- **Summary Statistics**: Total counts and sizes

## Advanced Features

### Backup Options

When creating backups, you can customize:

- **Compression**: Gzip compression (recommended)
- **Structure Only**: Schema without data
- **Include Routines**: Stored procedures and functions
- **Include Triggers**: Database triggers
- **Include Events**: Scheduled events

### Automatic Cleanup

Configure automatic cleanup of old backups:

```bash
# Keep backups for 30 days
TENANT_BACKUP_RETENTION_DAYS=30
```

Use the cleanup command:
```bash
php artisan tenant:backup-manager
# Select "🧹 Cleanup Old Backups"
```

### Storage Structure

Backups are organized by tenant:

```
storage/app/tenant-backups/
├── tenant_1/
│   ├── backup_20231201_120000.sql.gz
│   ├── backup_20231201_180000.sql.gz
│   └── metadata/
├── tenant_2/
│   ├── backup_20231201_120000.sql.gz
│   └── metadata/
```

## Configuration Reference

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `TENANT_BACKUP_ENABLED` | `true` | Enable/disable backup functionality |
| `TENANT_BACKUP_COMPRESS` | `true` | Default compression setting |
| `TENANT_BACKUP_RETENTION_DAYS` | `30` | Days to keep backups |
| `TENANT_BACKUP_DISK` | `tenant-backups` | Filesystem disk for backups |
| `TENANT_BACKUP_MYSQLDUMP_PATH` | `mysqldump` | Path to mysqldump binary |
| `TENANT_BACKUP_MYSQL_PATH` | `mysql` | Path to mysql binary |
| `TENANT_BACKUP_INCLUDE_ROUTINES` | `true` | Include stored procedures |
| `TENANT_BACKUP_INCLUDE_TRIGGERS` | `true` | Include triggers |
| `TENANT_BACKUP_INCLUDE_EVENTS` | `true` | Include events |

### Config File Settings

The `config/artflow-tenancy.php` file includes all backup settings under the `backup` section.

## Troubleshooting

### MySQL Path Issues

If you get "Command not found" errors:

1. **Find MySQL paths**:
   ```bash
   which mysqldump
   which mysql
   ```

2. **Update environment variables**:
   ```bash
   TENANT_BACKUP_MYSQLDUMP_PATH=/usr/bin/mysqldump
   TENANT_BACKUP_MYSQL_PATH=/usr/bin/mysql
   ```

### Permission Issues

Ensure the backup storage directory is writable:

```bash
chmod -R 755 storage/app/tenant-backups
```

### Large Database Backups

For very large databases:

1. **Disable compression** temporarily:
   ```bash
   TENANT_BACKUP_COMPRESS=false
   ```

2. **Use structure-only backups** for testing:
   - Select "Structure only (no data)?" = Yes in the wizard

### Restore Failures

Common restore issues:

1. **Check MySQL credentials** in tenant database configuration
2. **Verify backup file integrity** by checking file size
3. **Ensure target database exists** and is accessible
4. **Check MySQL privileges** for the database user

## Security Considerations

- Backup files contain sensitive database information
- Store backups in secure locations with proper access controls
- Consider encrypting backup files for additional security
- Regularly test restore procedures
- Monitor backup storage usage and implement retention policies

## Integration with Laravel Scheduler

To enable automatic backups, add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily backup of all tenants (if enabled in config)
    if (config('artflow-tenancy.backup.auto_schedule')) {
        $schedule->command('tenant:backup-manager')
            ->daily()
            ->at('02:00');
    }
}
```

## API Usage

You can also use the backup service programmatically:

```php
use ArtflowStudio\Tenancy\Services\TenantBackupService;

$backupService = app(TenantBackupService::class);

// Create backup
$result = $backupService->createBackup($tenant, [
    'compress' => true,
    'structure_only' => false,
]);

// List backups
$backups = $backupService->listTenantBackups($tenant);

// Restore backup
$backupService->restoreBackup($tenant, $backups[0]);
```

This backup system provides enterprise-grade functionality for managing tenant database backups with full MySQL control and isolated storage.
