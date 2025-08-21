# Command Structure Refactoring Recommendation

## Current Multi-Action Commands Analysis

Based on the analysis of your tenancy package, you currently have **3 main multi-action commands**:

### 1. TenantCommand (9 actions)
**File**: `src/Commands/Tenancy/TenantCommand.php`
**Signature**: `tenant:manage {action?}`
**Actions**:
- `create` - Create a new tenant
- `list` - List all tenants  
- `delete` - Delete a tenant
- `activate` - Activate a tenant
- `deactivate` - Deactivate a tenant
- `enable-homepage` - Enable homepage for tenant
- `disable-homepage` - Disable homepage for tenant
- `status` - Show tenant status
- `health` - Check system health

### 2. TenantDatabaseCommand (9 actions)
**File**: `src/Commands/Database/TenantDatabaseCommand.php`
**Signature**: `tenant:db {operation?}`
**Actions**:
- `migrate` - Run migrations for tenant database
- `migrate:fresh` - Drop all tables and re-run migrations
- `migrate:rollback` - Rollback migrations
- `migrate:status` - Show migration status
- `seed` - Run database seeders
- `fresh-seed` - Fresh migrate + seed in one command
- `reset` - Rollback all migrations
- `refresh` - Rollback and re-run migrations
- `sync` - Sync migrations/seeders from shared to tenant directories

### 3. FastPanelCommand (various actions)
**File**: `src/Commands/Tenancy/FastPanelCommand.php`
**Signature**: Multiple FastPanel operations

## Recommended Single-Command-Per-File Structure

### Phase 1: Keep High-Value Multi-Action Commands
Some commands benefit from having multiple related actions:

**Keep As-Is:**
- `tenant:db` - Database operations are tightly related and benefit from shared context
- `tenant:manage list|delete|activate|deactivate|status` - Simple CRUD operations

### Phase 2: Extract Complex Actions to Single Commands

**Split TenantCommand:**
1. `tenant:create` → ✅ **IMPLEMENTED** (Enhanced with FastPanel integration, privilege checking, user selection)
2. `tenant:create-fastpanel` → ✅ **Already Created** (New FastPanel integration)
3. `tenant:health` → Extract to dedicated health check command

**Split Database Operations (Optional):**
1. `tenant:migrate` → Single migration command
2. `tenant:seed` → Single seeding command  
3. `tenant:migrate-fresh` → Fresh migration command

### Phase 3: New FastPanel Commands Structure

✅ **Already Implemented:**

#### FastPanel Commands (New)
```
src/Commands/FastPanel/
├── CreateTenantCommand.php     → tenant:create-fastpanel
├── ListUsersCommand.php        → fastpanel:users
├── ListDatabasesCommand.php    → fastpanel:databases
└── SyncDatabaseCommand.php     → fastpanel:sync-database
```

## Implementation Status

### ✅ Completed
1. **FastPanelCreateTenantCommand** - `tenant:create-fastpanel`
   - Supports both FastPanel and localhost modes
   - Interactive user/site selection
   - Database creation with proper ownership
   - Encrypted credential storage

2. **Enhanced Core CreateTenantCommand** - ✅ **NEW: `tenant:create`**
   - Interactive wizard with mode selection (localhost/FastPanel)
   - Automatic database privilege checking and user selection
   - Full FastPanel integration with user/site assignment
   - Enhanced error handling and user experience
   - Deprecation warning added to old `tenant:manage create`

3. **FastPanel Management Commands**:
   - `fastpanel:users` - List users and database ownership
   - `fastpanel:databases` - List databases with ownership info
   - `fastpanel:sync-database` - Sync manually created databases

4. **Service Provider Registration** - Commands auto-registered

5. **Documentation** - Comprehensive FastPanel integration guide

### 🔄 Recommended Next Steps

#### Option A: Gradual Migration (Recommended)
1. **Keep existing multi-action commands** for backward compatibility
2. **Add deprecation warnings** to old commands pointing to new ones
3. **Update documentation** to reference new commands
4. **Remove old commands** in next major version

#### Option B: Immediate Restructure
1. **Extract remaining actions** from TenantCommand:
   ```php
   // Extract these to single commands:
   tenant:create         (instead of tenant:manage create)
   tenant:health         (instead of tenant:manage health)
   tenant:list           (instead of tenant:manage list)
   tenant:delete         (instead of tenant:manage delete)
   tenant:activate       (instead of tenant:manage activate)
   tenant:deactivate     (instead of tenant:manage deactivate)
   ```

2. **Keep database operations grouped** (they work well together):
   ```php
   tenant:db migrate
   tenant:db seed
   tenant:db fresh
   ```

## Command Organization Structure

### Recommended Directory Structure
```
src/Commands/
├── Core/
│   ├── CreateTenantCommand.php      → tenant:create ✅ IMPLEMENTED
│   ├── ListTenantsCommand.php       → tenant:list
│   ├── DeleteTenantCommand.php      → tenant:delete
│   └── HealthCheckCommand.php       → tenant:health
├── Database/
│   ├── TenantDatabaseCommand.php    → tenant:db (multi-action, keep)
│   ├── MigrateTenantCommand.php     → tenant:migrate (alternative)
│   └── SeedTenantCommand.php        → tenant:seed (alternative)
├── FastPanel/                       → ✅ Already implemented
│   ├── CreateTenantCommand.php      → tenant:create-fastpanel ✅ IMPLEMENTED
│   ├── ListUsersCommand.php         → fastpanel:users ✅ IMPLEMENTED
│   ├── ListDatabasesCommand.php     → fastpanel:databases ✅ IMPLEMENTED
│   └── SyncDatabaseCommand.php      → fastpanel:sync-database ✅ IMPLEMENTED
└── Testing/                         → Keep existing
    ├── TestSystemCommand.php        → tenant:test-system
    └── ComprehensiveTestCommand.php → tenant:test-comprehensive
```

## Benefits of Single-Command-Per-File

### ✅ Advantages
1. **Easier Maintenance** - Each file focuses on one responsibility
2. **Better Testing** - Individual commands easier to unit test
3. **Clearer Documentation** - Help text focused on single purpose
4. **Reduced Complexity** - No action routing logic needed
5. **Better IDE Support** - Autocomplete and navigation improved

### ⚠️ Considerations
1. **More Files** - Increases file count in Commands directory
2. **Duplication** - Some shared logic may be duplicated
3. **Discovery** - More commands to discover and remember

## Migration Strategy

### Step 1: Create Base Command Class
```php
abstract class BaseTenantCommand extends Command
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    // Shared methods for tenant selection, validation, etc.
}
```

### Step 2: Extract Single Commands
Create individual command files inheriting from `BaseTenantCommand`

### Step 3: Add Deprecation Warnings
```php
// In old TenantCommand
public function handle()
{
    $this->warn('⚠️  tenant:manage is deprecated. Use individual commands:');
    $this->line('  tenant:create instead of tenant:manage create');
    $this->line('  tenant:list instead of tenant:manage list');
    // ... continue with existing logic
}
```

### Step 4: Update Documentation
- Update README with new command structure
- Add migration guide for users
- Update CI/CD scripts to use new commands

## Testing Results

The new FastPanel integration and enhanced tenant creation have been tested and work correctly:

```bash
✅ tenant:create "Enhanced Test" "enhanced.test.local" --force
   - Interactive mode selection (localhost/FastPanel)
   - Automatic privilege checking (detects root user)
   - Creates database: tenant_enhanced_test  
   - Creates user: tenant_enhanced_test_user
   - Runs migrations and seeders
   - Stores encrypted credentials
   - Complete success output with summary table

✅ tenant:manage create deprecation warning
   - Shows clear deprecation message
   - Lists benefits of new command
   - Offers choice to redirect or continue
   - Maintains backward compatibility

✅ FastPanel mode integration
   - Checks FastPanel CLI availability
   - Lists FastPanel users for selection
   - Handles site assignment and database linking
   - Graceful fallback to localhost if FastPanel unavailable
```

## Recommendation

**Proceed with Option A (Gradual Migration)**:

1. ✅ Keep the new FastPanel commands (already working)
2. 🔄 Extract `tenant:create` from `tenant:manage` 
3. 🔄 Extract `tenant:health` as standalone command
4. ✅ Keep `tenant:db` as multi-action (works well)
5. 🔄 Add deprecation warnings to `tenant:manage`
6. 📚 Update documentation to promote new commands

This approach maintains backward compatibility while moving toward better organization.
