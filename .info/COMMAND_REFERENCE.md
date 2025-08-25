# Command Reference Guide

## Complete Command Inventory (44 Commands)

### Installation Commands

#### `tenancy:install`
**Purpose**: Interactive package installation and setup
**Features**:
- Guided configuration setup
- Database connection testing
- Migration publishing and execution
- Initial tenant creation
- Middleware registration assistance

```bash
php artisan tenancy:install
```

### Core Tenant Management

#### `tenant:create`
**Purpose**: Create new tenants with full configuration
**Features**:
- Interactive tenant creation wizard
- Custom database naming
- Domain assignment
- Status setting
- Homepage configuration

```bash
php artisan tenant:create
php artisan tenant:create --name="Acme Corp" --domain="acme.local"
```

#### `tenant:list`
**Purpose**: List all tenants with numbered selection interface
**Features**:
- Numbered indices [0], [1], [2] for easy selection
- Status indicators
- Database information
- Last access tracking
- Domain information

```bash
php artisan tenant:list
php artisan tenant:list --detailed
```

#### `tenant:activate` / `tenant:deactivate`
**Purpose**: Change tenant status
**Features**:
- Numbered tenant selection
- Status validation
- Confirmation prompts
- Batch operations support

```bash
php artisan tenant:activate
php artisan tenant:deactivate
```

#### `tenant:status`
**Purpose**: Show detailed tenant status information
**Features**:
- Complete tenant information
- Database connectivity status
- Domain configuration
- Migration status

```bash
php artisan tenant:status
```

### Database Management

#### `tenancy:migrate`
**Purpose**: Run migrations on tenant databases
**Features**:
- Single or multiple tenant selection
- Fresh migration option
- Rollback support
- Progress tracking

```bash
php artisan tenancy:migrate
php artisan tenancy:migrate --tenant=uuid
php artisan tenancy:migrate --fresh
```

#### `tenancy:seed`
**Purpose**: Seed tenant databases with data
**Features**:
- Tenant-specific seeders
- Batch seeding
- Custom seeder selection
- Progress reporting

```bash
php artisan tenancy:seed
php artisan tenancy:seed --class=UserSeeder
```

#### `tenancy:fix-tenant-databases`
**Purpose**: Repair and heal tenant database connectivity
**Features**:
- Auto-create missing databases
- Fix connection issues
- Migration status repair
- Comprehensive validation

```bash
php artisan tenancy:fix-tenant-databases
```

### Backup Management

#### `tenancy:backup-manager`
**Purpose**: Interactive backup management system
**Features**:
- Full backup wizard interface
- Numbered tenant selection ([0], [1], [2])
- Multiple backup types (full, structure-only)
- Compression options
- Restore wizard with safety checks
- Backup listing and cleanup

```bash
php artisan tenancy:backup-manager
```

**Interactive Options**:
- 📦 Create Backup (single, multiple, all tenants)
- 🔄 Restore from Backup (with destructive operation warnings)
- 📋 List Backups (numbered selection for restoration)
- 🧹 Cleanup Old Backups (retention policy management)
- ⚙️ Backup Settings (configuration management)

#### Individual Backup Commands

```bash
php artisan tenancy:backup-tenant        # Create single tenant backup
php artisan tenancy:backup-all          # Backup all tenants
php artisan tenancy:restore-tenant      # Restore tenant from backup
php artisan tenancy:list-backups       # List available backups
php artisan tenancy:cleanup-backups    # Remove old backups
```

### Testing and Validation

#### `tenancy:test-performance`
**Purpose**: Comprehensive performance testing suite
**Features**:
- Concurrent user simulation (up to 50 users)
- CRUD operation isolation testing
- Database performance benchmarking
- Memory usage monitoring
- Progress bars for long operations

```bash
php artisan tenancy:test-performance
php artisan tenancy:test-performance --concurrent-users=25
php artisan tenancy:test-performance --detailed
```

#### `tenancy:stress-test`
**Purpose**: Stress testing for system limits
**Features**:
- High-load tenant creation
- Concurrent operation stress testing
- Memory and CPU monitoring
- System stability validation

```bash
php artisan tenancy:stress-test
php artisan tenancy:stress-test --intensity=high
```

#### `tenancy:validate`
**Purpose**: System validation and health checks
**Features**:
- Middleware registration validation
- Database connectivity checks
- Migration status verification
- Automatic fixing with --fix flag

```bash
php artisan tenancy:validate
php artisan tenancy:validate --fix
```

#### `tenancy:test-isolation`
**Purpose**: Test tenant data isolation
**Features**:
- Cross-tenant data leakage detection
- Concurrent operation isolation
- Database-level isolation verification

```bash
php artisan tenancy:test-isolation
```

### Maintenance Commands

#### `tenancy:cleanup-orphaned`
**Purpose**: Remove orphaned tenant data
**Features**:
- Orphaned database detection
- Unused domain cleanup
- Backup file cleanup
- Safe deletion with confirmations

```bash
php artisan tenancy:cleanup-orphaned
php artisan tenancy:cleanup-orphaned --force
```

#### `tenancy:maintenance-mode`
**Purpose**: Enable/disable maintenance mode for tenants
**Features**:
- Single or multiple tenant maintenance
- Custom maintenance messages
- Scheduled maintenance windows

```bash
php artisan tenancy:maintenance-mode --enable
php artisan tenancy:maintenance-mode --disable
```

### FastPanel Integration

#### `fp:create-admin`
**Purpose**: Create FastPanel admin accounts
**Features**:
- Admin user creation
- Role assignment
- Password generation

```bash
php artisan fp:create-admin
```

#### `fp:reset-admin`
**Purpose**: Reset FastPanel admin credentials
**Features**:
- Password reset
- Admin account recovery
- Security validation

```bash
php artisan fp:reset-admin
```

### Advanced Commands

#### `tenancy:switch-cache`
**Purpose**: Switch between cache drivers for tenants
**Features**:
- Redis/Database cache switching
- Cache validation
- Performance impact analysis

```bash
php artisan tenancy:switch-cache redis
php artisan tenancy:switch-cache database
```

#### `tenancy:find-unused-files`
**Purpose**: Locate unused files in tenant storage
**Features**:
- Storage analysis
- Unused file detection
- Cleanup recommendations

```bash
php artisan tenancy:find-unused-files
```

## User Experience Enhancements

### Numbered Selection System
All commands that require tenant selection use a consistent numbered interface:

```
Available Tenants:
┌─────┬──────────────────────────────────────┬─────────────────┬────────┐
│ [#] │ ID                                   │ Name            │ Status │
├─────┼──────────────────────────────────────┼─────────────────┼────────┤
│ [0] │ 0d532abf-b552-4bb4-b27a-b6b08337c3eb │ Test Company    │ active │
│ [1] │ 1a2b3c4d-5e6f-7890-abcd-ef1234567890 │ Another Tenant  │ active │
└─────┴──────────────────────────────────────┴─────────────────┴────────┘

Select tenant [0-1, 'all', or 'exit']: 0
```

### Multi-Selection Support
Commands support multiple tenant selection:
- `0,1,3` - Select specific tenants
- `all` - Select all tenants
- `0-5` - Select range of tenants

### Progress Indicators
Long-running operations show progress:
```
🔄 Processing tenants...
██████████████████████████████████████████████████ 100%
✅ Completed successfully! (2.5s)
```

### Interactive Wizards
Many commands provide step-by-step wizards:
- Backup Manager: Full interactive backup/restore experience
- Tenant Creation: Guided tenant setup
- Installation: Complete package setup

### Safety Features
- Destructive operations require confirmation
- Preview of changes before execution
- Rollback options where applicable
- Data validation before processing

## Command Categories Summary

| Category | Commands | Purpose |
|----------|----------|---------|
| **Installation** | 1 | Package setup and configuration |
| **Core Management** | 8 | Basic tenant CRUD operations |
| **Database** | 5 | Migration, seeding, database management |
| **Backup** | 6 | Backup and restore operations |
| **Testing** | 8 | Performance, validation, isolation testing |
| **Maintenance** | 4 | Cleanup, maintenance, monitoring |
| **FastPanel** | 3 | FastPanel integration |
| **Advanced** | 9 | Specialized operations and tools |

**Total: 44 Commands** providing comprehensive tenant management capabilities.
