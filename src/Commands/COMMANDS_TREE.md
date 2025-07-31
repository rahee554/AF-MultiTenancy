# ArtFlow Tenancy Commands Tree Structure

This document outlines the current command structure and proposes a refactored, more granular approach where each command serves a specific purpose.

## Current Command Structure

```
src/Commands/
├── Backup/
│   ├── BackupManagementCommand.php          [tenant:backup-manager]
│   └── TenantBackupCommand.php              [tenant:backup]
├── Core/
│   ├── CreateTenantCommand.php              [tenant:create]
│   └── SwitchCacheDriverCommand.php         [tenancy:cache-driver]
├── Database/
│   ├── CheckPrivilegesCommand.php           [tenant:check-privileges]
│   ├── ComprehensiveDatabaseTest.php        [af-tenancy:test-database]
│   ├── DebugTenantConnectionCommand.php     [af-tenancy:debug-connection]
│   ├── DiagnoseDatabaseCommand.php          [tenancy:diagnose]
│   ├── TenantConnectionPoolCommand.php      [tenancy:connection-pool]
│   ├── TenantConnectionTestCommand.php      [tenancy:test-connections]
│   └── TenantDatabaseCommand.php            [tenant:db]
├── Diagnostics/
│   └── TenancyPerformanceDiagnosticCommand.php [tenancy:diagnose-performance]
├── FastPanel/
│   └── (FastPanel integration commands)
├── Installation/
│   └── InstallTenancyCommand.php            [af-tenancy:install]
├── Maintenance/
│   ├── EnhancedHealthCheckCommand.php       [tenancy:health-check]
│   ├── HealthCheckCommand.php               [tenancy:health]
│   └── TenantMaintenanceModeCommand.php     [tenants:maintenance]
├── Tenancy/
│   └── TenantCommand.php                    [tenant:manage]
└── Testing/
    ├── ComprehensiveTenancyTestCommand.php  [tenancy:test]
    ├── CreateTestTenantsCommand.php         [tenancy:create-test-tenants]
    ├── MasterTestCommand.php               [tenancy:test-all]
    ├── TenantTestManagerCommand.php         [tenancy:test-tenants]
    ├── Performance/
    │   ├── EnhancedTestPerformanceCommand.php [tenancy:test-performance-enhanced]
    │   ├── TestPerformanceCommand.php       [tenancy:test-performance]
    │   └── TenantStressTestCommand.php      [tenancy:stress-test]
    ├── Redis/
    │   ├── ConfigureRedisCommand.php        [tenancy:configure-redis]
    │   ├── EnableRedisCommand.php           [tenancy:enable-redis]
    │   ├── InstallRedisCommand.php          [tenancy:install-redis]
    │   ├── RedisStressTestCommand.php       [tenancy:redis-stress-test]
    │   └── TestRedisCommand.php             [tenancy:test-redis]
    └── System/
        ├── ServerCompatibilityCommand.php   [tenant:server-check]
        ├── TestMiddlewareCommand.php        [af-tenancy:test-middleware]
        ├── TestSystemCommand.php           [tenancy:test-system]
        └── ValidateTenancySystemCommand.php [tenancy:validate]
```

## Current Issues & Overlaps

### 🔄 Multi-Purpose Commands (Need to be Split)
1. **`tenant:backup-manager`** - Does backup, restore, list, cleanup (should be 4 separate commands)
2. **`tenant:db`** - Does migrate, seed, rollback, etc. (should be separate commands)
3. **`tenant:manage`** - Generic management (should be specific operations)
4. **`tenancy:test-all`** - Runs all tests (master command is OK, but individual tests should be accessible)
5. **`tenants:maintenance`** - Enable/disable/status/list (should be separate commands)

### 🔍 Health Check Duplicates
- `tenancy:health` (basic)
- `tenancy:health-check` (enhanced)
- Should merge into one comprehensive command

### 🧪 Testing Command Overlaps
- `tenancy:test` vs `tenancy:test-all` vs `tenancy:test-system`
- Multiple performance test commands with similar functions

## Proposed Refactored Structure

```
src/Commands/
├── Backup/
│   ├── TenantBackupCreateCommand.php        [tenant:backup:create]
│   ├── TenantBackupRestoreCommand.php       [tenant:backup:restore]
│   ├── TenantBackupListCommand.php          [tenant:backup:list]
│   ├── TenantBackupCleanupCommand.php       [tenant:backup:cleanup]
│   └── TenantBackupManagerCommand.php       [tenant:backup] (interactive menu)
├── Core/
│   ├── TenantCreateCommand.php              [tenant:create]
│   ├── TenantDeleteCommand.php              [tenant:delete]
│   ├── TenantListCommand.php                [tenant:list]
│   ├── TenantShowCommand.php                [tenant:show]
│   └── CacheSwitchDriverCommand.php         [tenancy:cache:switch]
├── Database/
│   ├── TenantDatabaseMigrateCommand.php     [tenant:database:migrate]
│   ├── TenantDatabaseSeedCommand.php        [tenant:database:seed]
│   ├── TenantDatabaseRollbackCommand.php    [tenant:database:rollback]
│   ├── TenantDatabaseStatusCommand.php      [tenant:database:status]
│   ├── TenantConnectionTestCommand.php      [tenant:database:test]
│   ├── TenantConnectionDebugCommand.php     [tenant:database:debug]
│   ├── TenantPrivilegesCheckCommand.php     [tenant:database:privileges]
│   └── TenantDatabaseCommand.php            [tenant:database] (interactive menu)
├── Diagnostics/
│   ├── SystemHealthCheckCommand.php         [tenancy:health]
│   ├── SystemDiagnoseCommand.php            [tenancy:diagnose]
│   ├── PerformanceDiagnoseCommand.php       [tenancy:diagnose:performance]
│   └── SystemValidateCommand.php            [tenancy:validate]
├── Installation/
│   ├── TenancyInstallCommand.php            [tenancy:install]
│   └── TenancyUninstallCommand.php          [tenancy:uninstall]
├── Maintenance/
│   ├── MaintenanceEnableCommand.php         [tenant:maintenance:enable]
│   ├── MaintenanceDisableCommand.php        [tenant:maintenance:disable]
│   ├── MaintenanceStatusCommand.php         [tenant:maintenance:status]
│   ├── MaintenanceListCommand.php           [tenant:maintenance:list]
│   └── MaintenanceCommand.php               [tenant:maintenance] (interactive menu)
├── Redis/
│   ├── RedisInstallCommand.php              [tenancy:redis:install]
│   ├── RedisConfigureCommand.php            [tenancy:redis:configure]
│   ├── RedisEnableCommand.php               [tenancy:redis:enable]
│   ├── RedisTestCommand.php                 [tenancy:redis:test]
│   └── RedisStressTestCommand.php           [tenancy:redis:stress-test]
└── Testing/
    ├── TestMasterCommand.php                [tenancy:test] (runs all tests)
    ├── TestSystemCommand.php                [tenancy:test:system]
    ├── TestMiddlewareCommand.php             [tenancy:test:middleware]
    ├── TestConnectionsCommand.php           [tenancy:test:connections]
    ├── TestPerformanceCommand.php           [tenancy:test:performance]
    ├── TestStressCommand.php                [tenancy:test:stress]
    ├── TestCreateTenantsCommand.php         [tenancy:test:create-tenants]
    └── ServerCompatibilityCommand.php       [tenancy:server:check]
```

## New Command Naming Convention

### 🎯 Specific Purpose Commands
- Each command should do ONE thing well
- Clear, descriptive names
- Consistent naming patterns

### 📚 Command Categories
- `tenant:*` - Direct tenant operations
- `tenancy:*` - System-wide tenancy operations
- `tenant:database:*` - Database-specific operations
- `tenant:backup:*` - Backup-specific operations
- `tenant:maintenance:*` - Maintenance-specific operations
- `tenancy:redis:*` - Redis-specific operations
- `tenancy:test:*` - Testing-specific operations

### 🔄 Interactive Master Commands
Keep master commands for convenience but also provide direct access:
- `tenant:backup` - Interactive backup manager
- `tenant:database` - Interactive database manager
- `tenant:maintenance` - Interactive maintenance manager
- `tenancy:test` - Interactive test runner

## Implementation Benefits

### ✅ **Advantages of Granular Commands**
1. **Clear Purpose**: Each command has a single, clear responsibility
2. **Easy Automation**: Scripts can call specific commands directly
3. **Better Help**: Each command can have focused help documentation
4. **Modular Testing**: Easy to test individual features
5. **User Choice**: Users can use interactive menus OR direct commands
6. **CI/CD Friendly**: Automation scripts prefer specific commands

### 🚀 **Migration Strategy**
1. **Phase 1**: Create new granular commands alongside existing ones
2. **Phase 2**: Update master commands to delegate to new granular commands
3. **Phase 3**: Deprecate old multi-purpose commands (with warnings)
4. **Phase 4**: Remove deprecated commands in next major version

### 🎨 **User Experience**
```bash
# Interactive (for exploration)
php artisan tenant:backup
php artisan tenant:maintenance

# Direct (for automation/scripts)
php artisan tenant:backup:create tenant-id --compress
php artisan tenant:maintenance:enable tenant-id --message="Under maintenance"
```

## Next Steps

1. **Implement new granular commands** starting with most used operations
2. **Update interactive commands** to use numbered options [0][1][2]
3. **Add deprecation warnings** to old multi-purpose commands
4. **Update documentation** to show both interactive and direct usage
5. **Create migration guide** for users switching from old commands

This structure provides both **ease of use** (interactive commands) and **automation capability** (specific commands) while maintaining backward compatibility during the transition.
