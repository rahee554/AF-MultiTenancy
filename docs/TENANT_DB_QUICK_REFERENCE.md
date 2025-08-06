# 🗄️ Tenant Database Commands - Quick Reference

## New Command: `tenant:db`
Dedicated database operations for tenants - better than mixing with `tenant:manage`

## 🚀 Quick Start
```bash
# Interactive mode - easiest way to start
php artisan tenant:db

# Direct operations
php artisan tenant:db migrate --tenant=uuid-123
php artisan tenant:db seed --class=UserSeeder --tenant=uuid-123
```

## 📋 All Operations

| Operation | What It Does | Usage |
|-----------|--------------|-------|
| `migrate` | Run pending migrations | `tenant:db migrate` |
| `migrate:fresh` | 🔥 Drop all + re-migrate | `tenant:db migrate:fresh` |
| `migrate:rollback` | Undo migrations | `tenant:db migrate:rollback --step=3` |
| `migrate:status` | Show migration status | `tenant:db migrate:status` |
| `seed` | Run seeders | `tenant:db seed --class=UserSeeder` |
| `fresh-seed` | Fresh + seed in one go | `tenant:db fresh-seed` |
| `reset` | 🔥 Rollback everything | `tenant:db reset` |
| `refresh` | Rollback + re-migrate | `tenant:db refresh` |

## 🎯 Tenant Selection (Super Easy!)

### Method 1: Command Line
```bash
php artisan tenant:db migrate --tenant=uuid-or-name
```

### Method 2: Interactive List
```bash
php artisan tenant:db migrate
# Shows nice table of all tenants, pick one by number
```

### Method 3: Search by Name
```bash
# Command asks: "Enter tenant name to search"
# Type: "acme" → finds "Acme Corporation", "Acme Industries"
```

### Method 4: UUID Entry
```bash
# Command asks: "Enter tenant UUID"
# Paste full UUID
```

## ⚡ Common Usage Patterns

### For Single Tenant
```bash
# Basic migration
php artisan tenant:db migrate --tenant=my-tenant-uuid

# Fresh start with data
php artisan tenant:db fresh-seed --tenant=my-tenant-uuid

# Specific seeder
php artisan tenant:db seed --class=ProductSeeder --tenant=my-tenant-uuid

# Check what would happen (dry run)
php artisan tenant:db migrate --pretend --tenant=my-tenant-uuid
```

### For All Tenants
```bash
# Migrate all active tenants
php artisan tenant:db migrate --all

# Seed all with specific seeder
php artisan tenant:db seed --all --class=UpdateSeeder

# Fresh migrate all (with confirmation)
php artisan tenant:db migrate:fresh --all

# Skip confirmation (dangerous!)
php artisan tenant:db migrate:fresh --all --force
```

### For Filtered Tenants
```bash
# Only inactive tenants
php artisan tenant:db migrate --all --status=inactive

# Only blocked tenants  
php artisan tenant:db migrate:status --all --status=blocked
```

## 🛡️ Safety Features

### Confirmation for Destructive Operations
```bash
php artisan tenant:db migrate:fresh --tenant=uuid
# ⚠️  This will DROP ALL TABLES and re-run migrations!
# Are you sure you want to continue? (yes/no) [no]:
```

### Force Skip Confirmations
```bash
php artisan tenant:db migrate:fresh --force --tenant=uuid
```

### Dry Run (See What Would Happen)
```bash
php artisan tenant:db migrate --pretend --tenant=uuid
```

## 🔧 Advanced Options

| Option | Purpose | Example |
|--------|---------|---------|
| `--class=Name` | Specific seeder class | `--class=UserSeeder` |
| `--step=N` | Rollback N steps | `--step=3` |
| `--seed` | Seed after migrate | `--seed` |
| `--all` | All active tenants | `--all` |
| `--status=X` | Filter by status | `--status=inactive` |
| `--force` | Skip confirmations | `--force` |
| `--pretend` | Dry run mode | `--pretend` |

## 📊 Bulk Operation Results
```bash
📊 Operation Summary:
   ✅ Successful: 4
   ❌ Failed: 1

❌ Errors encountered:
   • Failed for Demo Company: Migration file not found
```

## 🆚 Old vs New Commands

| Task | ❌ Old Way | ✅ New Way |
|------|-----------|----------|
| Migrate tenant | `tenant:manage migrate --tenant=x` | `tenant:db migrate --tenant=x` |
| Seed tenant | `tenant:manage seed --tenant=x` | `tenant:db seed --tenant=x` |
| Fresh migrate | Not available | `tenant:db migrate:fresh --tenant=x` |
| Rollback | Not available | `tenant:db migrate:rollback --tenant=x` |
| All tenants | Limited support | `tenant:db migrate --all` |
| Specific seeder | Not available | `tenant:db seed --class=UserSeeder` |
| Interactive selection | Basic | Advanced with search |

## 🎬 Real Usage Examples

### Development Workflow
```bash
# Set up new tenant for development
php artisan tenant:manage create --name="Dev Tenant" --domain=dev.local
php artisan tenant:db migrate --tenant=dev-uuid --seed

# Reset and reseed during development
php artisan tenant:db fresh-seed --tenant=dev-uuid --force
```

### Production Deployment
```bash
# Safely migrate all tenants
php artisan tenant:db migrate --all

# Check migration status for all
php artisan tenant:db migrate:status --all
```

### Data Management
```bash
# Add new seed data to specific tenant
php artisan tenant:db seed --class=NewFeatureSeeder --tenant=specific-uuid

# Rollback problematic migration
php artisan tenant:db migrate:rollback --step=1 --tenant=affected-uuid
```

### Troubleshooting
```bash
# Check what migrations would run
php artisan tenant:db migrate --pretend --tenant=problem-uuid

# Reset tenant database completely
php artisan tenant:db reset --tenant=problem-uuid --force
php artisan tenant:db migrate --tenant=problem-uuid --seed
```

## 💡 Pro Tips

1. **Always use `--pretend` first** for new migrations
2. **Use interactive mode** when unsure about tenant UUID/name
3. **Test on single tenant** before using `--all`
4. **Use specific seeder classes** instead of DatabaseSeeder for targeted updates
5. **Use `--force` carefully** - only in scripts or when certain

## 🚨 Danger Zone

These operations are **DESTRUCTIVE** - they delete data:

```bash
tenant:db migrate:fresh  # Drops all tables
tenant:db reset         # Rollback all migrations
tenant:db refresh       # Rollback + re-migrate
```

Always:
- ✅ Backup before destructive operations
- ✅ Test on development tenant first  
- ✅ Use `--pretend` when possible
- ✅ Confirm tenant selection before proceeding

## 📚 Full Documentation
See `TENANT_DATABASE_COMMAND.md` for complete documentation with examples, troubleshooting, and advanced usage patterns.

---
**The `tenant:db` command makes database management for multi-tenant applications much easier and safer!** 🎉
