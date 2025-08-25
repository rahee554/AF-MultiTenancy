# Technical Architecture Deep Dive

## Core Architecture Patterns

### 1. Service Layer Pattern
The package uses a comprehensive service layer to encapsulate business logic:

#### TenantService (Primary Service)
**File**: `src/Services/TenantService.php`
**Purpose**: Central tenant management operations
**Key Methods**:
- `createTenant()` - Creates tenant with database and domain
- `deleteTenant()` - Safe tenant deletion with confirmations
- `listTenants()` - Enhanced listing with numbered indices
- `findTenant()` - Multi-method tenant lookup (UUID, name, index)
- `activateTenant()` / `deactivateTenant()` - Status management
- `createPhysicalDatabase()` - Database creation and validation

#### TenantBackupService
**File**: `src/Services/TenantBackupService.php`
**Purpose**: Comprehensive backup and restore operations
**Key Methods**:
- `createBackup()` - MySQL dump with compression
- `restoreBackup()` - Safe restore with validation
- `listTenantBackups()` - Backup inventory management
- `cleanupOldBackups()` - Automatic cleanup based on retention policies

#### CachedTenantResolver
**File**: `src/Services/CachedTenantResolver.php`
**Purpose**: Performance optimization for tenant resolution
**Key Features**:
- Implements `Stancl\Tenancy\Contracts\TenantResolver`
- Redis caching for tenant lookups
- Fallback to database when cache misses
- Interface compatibility with stancl/tenancy

### 2. Command Pattern Implementation

All commands follow a consistent pattern:
```php
class CommandExample extends Command
{
    protected $signature = 'tenancy:example {argument} {--option}';
    protected $description = 'Description of command functionality';
    
    public function handle(): int
    {
        // Validation
        // User interaction (numbered selection where applicable)
        // Business logic execution
        // Result reporting
        return Command::SUCCESS;
    }
}
```

**Numbered Selection Pattern**:
Most commands use a consistent numbered selection UI:
```
Available Tenants:
┌─────┬──────────────────────────────────────┬─────────────────┬────────┐
│ [#] │ ID                                   │ Name            │ Status │
├─────┼──────────────────────────────────────┼─────────────────┼────────┤
│ [0] │ 0d532abf-b552-4bb4-b27a-b6b08337c3eb │ Test Company    │ active │
│ [1] │ 1a2b3c4d-5e6f-7890-abcd-ef1234567890 │ Another Tenant  │ active │
└─────┴──────────────────────────────────────┴─────────────────┴────────┘
```

### 3. Model Enhancement Pattern

The package extends stancl/tenancy models while maintaining compatibility:

```php
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;
    
    // Additional fields beyond stancl's base model
    protected $fillable = [
        'id', 'data',           // stancl base fields
        'name', 'database',     // artflow additions
        'status', 'has_homepage', 'last_accessed_at', 'settings'
    ];
    
    // Enhanced functionality
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name', 'database', 'status', 'has_homepage', 'last_accessed_at', 'settings'
        ]);
    }
}
```

### 4. Middleware Architecture

**Layered Middleware Approach**:
1. **stancl/tenancy base middleware** - Core tenant identification and context switching
2. **artflow-studio enhancement middleware** - Additional functionality

```php
// TenantMiddleware enhances stancl's InitializeTenancyByDomain
class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $currentTenant = tenant(); // Already set by stancl
        
        if ($currentTenant instanceof Tenant) {
            // Status checking (our enhancement)
            if ($currentTenant->status === 'blocked') {
                abort(403, 'Tenant blocked');
            }
            
            // Last access tracking (our enhancement)
            $currentTenant->updateLastAccess();
        }
        
        return $next($request);
    }
}
```

## Data Flow Architecture

### 1. Tenant Creation Flow
```
User Input → TenantService::createTenant()
    ↓
Generate UUID → Create Physical Database
    ↓
Create Tenant Record → Create Domain Record
    ↓
Run Migrations → Seed Data → Return Success
```

### 2. Backup Creation Flow
```
User Selection → TenantBackupService::createBackup()
    ↓
Get Tenant DB Info → Generate mysqldump Command
    ↓
Execute Dump → Compress (optional) → Store Metadata
    ↓
Clean Temp Files → Return Backup Info
```

### 3. Performance Testing Flow
```
Command Execution → Tenant Selection
    ↓
Initialize Test Parameters → Create Progress Bars
    ↓
Concurrent User Simulation → CRUD Operations
    ↓
Isolation Verification → Performance Metrics → Report
```

## Database Schema Design

### Enhanced Tenant Table
```sql
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,     -- UUID from stancl
    data JSON,                       -- stancl's data field
    name VARCHAR(255),               -- Human-readable name
    database VARCHAR(255),           -- Custom database name
    status ENUM('active','inactive','blocked'), -- Status management
    has_homepage BOOLEAN DEFAULT FALSE, -- Homepage detection
    last_accessed_at TIMESTAMP NULL, -- Usage tracking
    settings JSON,                   -- Additional tenant settings
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Backup Metadata Storage
The package stores backup metadata in the central database and physical backup files on disk:
```
storage/tenant-backups/
├── tenant_uuid_1/
│   ├── tenant_uuid_1_2025-01-01_12-00-00_full.sql
│   ├── tenant_uuid_1_2025-01-02_12-00-00_structure.sql
│   └── metadata.json
└── tenant_uuid_2/
    └── ...
```

## Error Handling Strategy

### 1. Graceful Degradation
Commands are designed to continue operation even when some tenants have issues:
```php
foreach ($tenants as $tenant) {
    try {
        $result = $this->processTenat($tenant);
        $successCount++;
    } catch (\Exception $e) {
        $this->error("Tenant {$tenant->name}: {$e->getMessage()}");
        $errorCount++;
    }
}
```

### 2. Database Connectivity Healing
The package automatically attempts to fix database connectivity issues:
```php
// TenantService::validateTenantDatabase()
if (!$this->canConnectToDatabase($tenant)) {
    $this->createPhysicalDatabase($tenant->database);
    $this->runMigrations($tenant);
}
```

### 3. Validation and Recovery
Multiple commands provide automatic fixing capabilities:
- `tenancy:validate --fix` - Fixes common tenant issues
- `tenancy:fix-tenant-databases` - Repairs database connectivity
- `tenancy:cleanup-orphaned` - Removes orphaned data

## Performance Optimization Strategies

### 1. Caching Layer
- **TenantContextCache**: Caches tenant resolution results
- **CachedTenantResolver**: Redis-backed tenant lookup caching
- **Connection Pooling**: Optimized database connections

### 2. Batch Operations
Commands support batch operations for multiple tenants:
```php
// Multi-tenant selection with comma-separated indices
$indices = '0,1,3'; // Select tenants [0], [1], and [3]
$tenants = $this->selectMultipleTenants($allTenants, $indices);
```

### 3. Background Processing
Long-running operations support background processing:
- Backup creation can be queued
- Migration operations can run in background
- Performance testing supports concurrent execution

## Integration Patterns

### 1. stancl/tenancy Integration
The package works as an enhancement layer:
```php
// Uses stancl's tenant context
tenancy()->initialize($tenant);
// Adds artflow enhancements
$tenant->updateLastAccess();
```

### 2. Laravel Integration
Seamless integration with Laravel ecosystem:
- Service provider auto-discovery
- Configuration publishing
- Artisan command registration
- Middleware registration

### 3. Livewire Integration
Real-time web interface:
- Tenant management components
- Real-time status updates
- Interactive tenant creation
- Dashboard monitoring

## Security Considerations

### 1. Tenant Isolation
- Strict database isolation
- Middleware-enforced context switching
- Cross-tenant data leakage prevention

### 2. Backup Security
- Isolated backup storage per tenant
- Secure database credential handling
- Backup file access controls

### 3. Command Safety
- Confirmation prompts for destructive operations
- Input validation and sanitization
- Safe tenant deletion with cascading checks
