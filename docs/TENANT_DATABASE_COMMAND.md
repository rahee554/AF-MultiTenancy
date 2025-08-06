# Tenant Database Command Documentation

## Overview
The `tenant:db` command provides comprehensive database management for individual tenants or all tenants at once. This command separates database operations from general tenant management for better organization and more specialized functionality.

## Command Signature
```bash
php artisan tenant:db {operation?} [options]
```

## Available Operations

| Operation | Description | Example |
|-----------|-------------|---------|
| `migrate` | Run pending migrations | `tenant:db migrate` |
| `migrate:fresh` | Drop all tables and re-run migrations | `tenant:db migrate:fresh` |
| `migrate:rollback` | Rollback migrations | `tenant:db migrate:rollback` |
| `migrate:status` | Show migration status | `tenant:db migrate:status` |
| `seed` | Run database seeders | `tenant:db seed` |
| `fresh-seed` | Fresh migrate + seed in one command | `tenant:db fresh-seed` |
| `reset` | Rollback all migrations | `tenant:db reset` |
| `refresh` | Rollback and re-run migrations | `tenant:db refresh` |

## Available Options

| Option | Description | Example |
|--------|-------------|---------|
| `--tenant=UUID` | Target specific tenant by UUID or name | `--tenant=uuid-123` |
| `--class=ClassName` | Specify seeder class name | `--class=UserSeeder` |
| `--step=N` | Number of steps to rollback | `--step=3` |
| `--force` | Force operation without confirmation | `--force` |
| `--seed` | Run seeders after migration | `--seed` |
| `--all` | Run operation for all active tenants | `--all` |
| `--status=active` | Filter tenants by status | `--status=inactive` |
| `--pretend` | Show what would be migrated (dry run) | `--pretend` |

## Usage Examples

### Interactive Mode
```bash
# Start interactive mode - command will guide you through options
php artisan tenant:db
```

### Direct Operations
```bash
# Run migrations for specific tenant
php artisan tenant:db migrate --tenant=tenant-uuid-123

# Run fresh migration with seeding
php artisan tenant:db migrate:fresh --seed --tenant=tenant-name

# Run specific seeder
php artisan tenant:db seed --class=UserSeeder --tenant=tenant-uuid

# Run migrations for all active tenants
php artisan tenant:db migrate --all

# Rollback 3 steps
php artisan tenant:db migrate:rollback --step=3 --tenant=tenant-uuid
```

### Bulk Operations
```bash
# Run operation for all active tenants
php artisan tenant:db migrate --all

# Run for all inactive tenants
php artisan tenant:db migrate:status --all --status=inactive

# Fresh migrate + seed all tenants
php artisan tenant:db fresh-seed --all --force
```

## Tenant Selection Methods

The command offers multiple ways to select tenants:

### 1. Command Line Option
```bash
php artisan tenant:db migrate --tenant=tenant-uuid-123
```

### 2. Interactive List Selection
When no tenant is specified, the command shows an interactive list:
```
📋 Available tenants (Status: active):
┌───┬─────────────────────────────┬──────────────────────────────────┬────────────────────┬────────┬──────────────┐
│ # │ Name                        │ Domain                           │ Database           │ Status │ UUID (short) │
├───┼─────────────────────────────┼──────────────────────────────────┼────────────────────┼────────┼──────────────┤
│ 1 │ Acme Corporation            │ acme.yoursite.com                │ tenant_acme        │ active │ a1b2c3d4...  │
│ 2 │ Demo Company                │ demo.yoursite.com                │ tenant_demo        │ active │ e5f6g7h8...  │
└───┴─────────────────────────────┴──────────────────────────────────┴────────────────────┴────────┴──────────────┘

Select a tenant:
  [1] Acme Corporation (acme.yoursite.com)
  [2] Demo Company (demo.yoursite.com)
```

### 3. Search by Name
```bash
# Command will search for tenants matching the name
🔍 Found 2 matching tenants:
  [0] Acme Corporation (acme.yoursite.com)
  [1] Acme Industries (industries.yoursite.com)
```

### 4. Direct UUID Entry
```bash
Enter tenant UUID: a1b2c3d4-e5f6-7g8h-9i0j-k1l2m3n4o5p6
```

## Operation Details

### Migration Operations

#### `migrate`
Runs pending migrations for the tenant database.
```bash
php artisan tenant:db migrate --tenant=uuid-123

# With seeding after migration
php artisan tenant:db migrate --seed --tenant=uuid-123

# Dry run (see what would be migrated)
php artisan tenant:db migrate --pretend --tenant=uuid-123
```

#### `migrate:fresh`
Drops all tables and re-runs all migrations. **DESTRUCTIVE OPERATION**.
```bash
php artisan tenant:db migrate:fresh --tenant=uuid-123

# With confirmation prompt
php artisan tenant:db migrate:fresh --tenant=uuid-123

# Force without confirmation (dangerous)
php artisan tenant:db migrate:fresh --force --tenant=uuid-123
```

#### `migrate:rollback`
Rolls back migrations by specified number of steps.
```bash
# Rollback last migration batch
php artisan tenant:db migrate:rollback --tenant=uuid-123

# Rollback specific number of steps
php artisan tenant:db migrate:rollback --step=3 --tenant=uuid-123
```

#### `migrate:status`
Shows the status of all migrations (ran/pending).
```bash
php artisan tenant:db migrate:status --tenant=uuid-123
```

### Seeding Operations

#### `seed`
Runs database seeders for the tenant.
```bash
# Run DatabaseSeeder (default)
php artisan tenant:db seed --tenant=uuid-123

# Run specific seeder class
php artisan tenant:db seed --class=UserSeeder --tenant=uuid-123

# Interactive seeder selection
php artisan tenant:db seed --tenant=uuid-123
# Enter seeder class name (leave empty for DatabaseSeeder): ProductSeeder
```

#### `fresh-seed`
Combines `migrate:fresh` and `seed` in one operation.
```bash
php artisan tenant:db fresh-seed --tenant=uuid-123
php artisan tenant:db fresh-seed --class=UserSeeder --tenant=uuid-123
```

### Maintenance Operations

#### `reset`
Rolls back all migrations. **DESTRUCTIVE OPERATION**.
```bash
php artisan tenant:db reset --tenant=uuid-123 --force
```

#### `refresh`
Rolls back all migrations and re-runs them.
```bash
php artisan tenant:db refresh --tenant=uuid-123
php artisan tenant:db refresh --seed --tenant=uuid-123
```

## Bulk Operations

### All Active Tenants
```bash
# Run migrations for all active tenants
php artisan tenant:db migrate --all

# Run seeders for all active tenants
php artisan tenant:db seed --all --class=UserSeeder

# Fresh migrate all tenants (with confirmation)
php artisan tenant:db migrate:fresh --all
```

### Filtered by Status
```bash
# Run operations for inactive tenants
php artisan tenant:db migrate:status --all --status=inactive

# Run for blocked tenants
php artisan tenant:db migrate --all --status=blocked
```

### Bulk Operation Results
```bash
📊 Operation Summary:
   ✅ Successful: 5
   ❌ Failed: 1

❌ Errors encountered:
   • Failed for Demo Company: Migration file not found
```

## Safety Features

### Confirmation Prompts
Destructive operations require confirmation:
```bash
⚠️  This will DROP ALL TABLES and re-run migrations!
Are you sure you want to continue? (yes/no) [no]:
```

### Force Option
Skip confirmations with `--force`:
```bash
php artisan tenant:db migrate:fresh --force --all
```

### Pretend Mode
See what would happen without executing:
```bash
php artisan tenant:db migrate --pretend --tenant=uuid-123
```

## Error Handling

### Individual Tenant Errors
```bash
❌ Operation failed: SQLSTATE[42S01]: Base table or view already exists
💡 Check logs for more details
```

### Bulk Operation Errors
```bash
📍 Processing: Acme Corporation
  ❌ Failed

📍 Processing: Demo Company  
  ✅ Success

📊 Operation Summary:
   ✅ Successful: 1
   ❌ Failed: 1

❌ Errors encountered:
   • Error for Acme Corporation: Connection refused
```

## Integration with Existing Commands

### Relationship with `tenant:manage`
- `tenant:manage` - General tenant operations (create, delete, activate)
- `tenant:db` - Specialized database operations (migrate, seed, rollback)

### Migration from `tenant:manage`
If you were using database operations in `tenant:manage`, migrate to `tenant:db`:

```bash
# OLD
php artisan tenant:manage migrate --tenant=uuid-123

# NEW  
php artisan tenant:db migrate --tenant=uuid-123
```

## Advanced Usage

### Chaining Operations
```bash
# Create tenant, then setup database
php artisan tenant:manage create --name="New Company" --domain=new.example.com
php artisan tenant:db migrate --tenant=new-tenant-uuid
php artisan tenant:db seed --class=InitialDataSeeder --tenant=new-tenant-uuid
```

### Scripted Operations
```bash
#!/bin/bash
# Setup script for new tenant

TENANT_UUID=$1
if [ -z "$TENANT_UUID" ]; then
    echo "Usage: $0 <tenant-uuid>"
    exit 1
fi

echo "Setting up database for tenant: $TENANT_UUID"
php artisan tenant:db migrate --tenant=$TENANT_UUID --force
php artisan tenant:db seed --class=DefaultDataSeeder --tenant=$TENANT_UUID --force
echo "Setup complete!"
```

### Development Workflow
```bash
# Development cycle
php artisan tenant:db migrate:fresh --tenant=dev-tenant --seed
# ... make changes to seeders/migrations ...
php artisan tenant:db migrate:fresh --tenant=dev-tenant --seed
```

## Performance Considerations

### Large Number of Tenants
For operations on many tenants:
```bash
# Use status filtering to process tenants in batches
php artisan tenant:db migrate --all --status=active

# Process specific groups
php artisan tenant:db migrate --all --status=pending_migration
```

### Database Connection Pooling
The command properly initializes and cleans up tenant contexts to prevent connection leaks.

## Troubleshooting

### Common Issues

1. **Tenant Not Found**
   ```bash
   ❌ Tenant not found: uuid-123
   💡 Try searching by name or UUID
   ```
   Solution: Use exact UUID or search by name.

2. **Migration Failures**
   ```bash
   ❌ Migrations failed
   ```
   Solution: Check tenant database exists and migrations are valid.

3. **Seeder Class Not Found**
   ```bash
   ❌ Seeding failed: Class 'UserSeeder' not found
   ```
   Solution: Ensure seeder exists and is properly namespaced.

### Debug Mode
Add `-v` flag for verbose output:
```bash
php artisan tenant:db migrate --tenant=uuid-123 -v
```

## Best Practices

1. **Always backup before destructive operations**
2. **Use `--pretend` for testing migrations**
3. **Test operations on a single tenant before using `--all`**
4. **Use specific seeder classes rather than DatabaseSeeder for targeted seeding**
5. **Monitor bulk operations for failed tenants**

## Command Comparison

| Task | Old Command | New Command |
|------|-------------|-------------|
| Run migrations | `tenant:manage migrate` | `tenant:db migrate` |
| Seed database | `tenant:manage seed` | `tenant:db seed` |
| Fresh migration | Not available | `tenant:db migrate:fresh` |
| Rollback | Not available | `tenant:db migrate:rollback` |
| Migration status | Not available | `tenant:db migrate:status` |
| Bulk operations | Limited | Full support with `--all` |
| Interactive selection | Basic | Advanced with search |

The new `tenant:db` command provides much more comprehensive and user-friendly database management for your multi-tenant application!
