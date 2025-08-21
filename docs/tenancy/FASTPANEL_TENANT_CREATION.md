# FastPanel Tenant Creation & Database Management

This guide explains how to create tenants with FastPanel integration, including database creation, user assignment, and site linking.

## Command Structure Refactoring Summary

### Previous Multi-Action Commands
- `TenantCommand` (9 actions): create, list, delete, activate, deactivate, enable-homepage, disable-homepage, status, health
- `TenantDatabaseCommand` (9 actions): migrate, migrate:fresh, migrate:rollback, seed, migrate:status, fresh-seed, reset, refresh, sync
- `FastPanelCommand` (various FastPanel operations)

### New Single-Purpose Commands
- `tenant:create-fastpanel` - Create tenant with FastPanel or localhost mode
- `fastpanel:users` - List FastPanel users and database ownership
- `fastpanel:databases` - List FastPanel databases with ownership info  
- `fastpanel:sync-database` - Sync manually created database with FastPanel

## FastPanel Integration Commands

### 1. Create Tenant with FastPanel Integration

```bash
php artisan tenant:create-fastpanel "Tenant Name" "tenant.domain.com" [options]
```

#### Options:
- `--mode=fastpanel|localhost` - Creation mode (default: fastpanel)
- `--panel-user=ID` - FastPanel user ID for database ownership
- `--site-id=ID` - FastPanel site ID to link database
- `--db-name=name` - Custom database name (will be prefixed)
- `--db-username=user` - Custom database username
- `--db-password=pass` - Custom database password  
- `--server-id=1` - FastPanel database server ID (default: 1)
- `--homepage` - Enable homepage for tenant
- `--status=active` - Tenant status (default: active)
- `--notes="text"` - Tenant notes
- `--force` - Force creation without confirmation

#### FastPanel Mode (Production):
```bash
# Interactive mode - will prompt for user and site selection
php artisan tenant:create-fastpanel "Al Emaan" "alemaan.pk"

# Specify panel user and site
php artisan tenant:create-fastpanel "Al Emaan" "alemaan.pk" \
  --panel-user=1 \
  --site-id=5 \
  --db-name="alemaan_pk" \
  --homepage
```

#### Localhost Mode (Development):
```bash
php artisan tenant:create-fastpanel "Test Tenant" "test.local" \
  --mode=localhost \
  --db-name="test_tenant"
```

### 2. List FastPanel Users & Database Ownership

```bash
# Table format
php artisan fastpanel:users

# JSON format
php artisan fastpanel:users --format=json
```

**Output Example:**
```
👤 User: fastuser (ID: 1)
   📧 Email: fast@example.com
   💾 Databases (7):
      • whatsblaze → whatsblaze.com
      • inspiretech → inspiretech.pk
      • al_emaan_pk

👤 User: admin_user (ID: 2)  
   📧 Email: admin@example.com
   💾 Databases (11):
      • universaltradingbot
      • pakforexacademy → pakforex.com
```

### 3. List FastPanel Databases

```bash
# All databases
php artisan fastpanel:databases

# Filter by user
php artisan fastpanel:databases --user=1

# Show only unassigned databases
php artisan fastpanel:databases --unassigned

# JSON output
php artisan fastpanel:databases --format=json
```

### 4. Sync Manually Created Database

```bash
# Sync database with FastPanel metadata
php artisan fastpanel:sync-database tenant_alemaan_pk

# Sync and assign to user
php artisan fastpanel:sync-database tenant_alemaan_pk --assign-user=1

# Sync, assign user, and link to site
php artisan fastpanel:sync-database tenant_alemaan_pk \
  --assign-user=1 \
  --link-site=5

# Dry run (show what would be done)
php artisan fastpanel:sync-database tenant_alemaan_pk \
  --assign-user=1 \
  --link-site=5 \
  --dry-run
```

## FastPanel Integration Workflow

### How It Works

1. **FastPanel User Selection**
   - Lists available FastPanel panel users
   - Interactive selection or specify `--panel-user=ID`
   - Each user can own multiple databases

2. **Site Linking (Optional)**
   - Lists sites owned by selected panel user
   - Links database to specific website/domain
   - Enables auto-login and management through FastPanel UI

3. **Database Creation Methods**
   - **FastPanel CLI** (preferred): Creates DB + user + panel metadata
   - **MySQL Direct** (fallback): Creates DB, then syncs with panel

4. **User & Permission Management**
   - Creates MySQL database user with limited privileges
   - Stores encrypted credentials in Laravel tenant record
   - Maps FastPanel panel user → MySQL database → tenant record

### FastPanel Metadata Storage

FastPanel stores metadata in SQLite: `/usr/local/fastpanel2/app/db/fastpanel2.db`

**Key Tables:**
- `db_servers` - Database server entries
- `database_user` - MySQL users created by FastPanel  
- `database_plan` - Plan → database linking
- `datbases_users` (note spelling) - Panel user → database mapping

### Database Privilege Strategy

**Option A: Per-Tenant DB Users (Recommended)**
```sql
-- Each tenant gets its own MySQL user
CREATE USER 'tenant_alemaan_user'@'localhost' IDENTIFIED BY 'SecurePass';
GRANT ALL PRIVILEGES ON `tenant_alemaan_pk`.* TO 'tenant_alemaan_user'@'localhost';
```

**Option B: Shared Tenant Admin User**
```sql
-- One admin user for all tenant operations
CREATE USER 'tenant_admin'@'localhost' IDENTIFIED BY 'AdminPass';
GRANT CREATE, DROP, ALTER, INDEX ON *.* TO 'tenant_admin'@'localhost';
```

## Environment Configuration

Add to your `.env`:

```env
# Tenant database settings
TENANT_DB_PREFIX=tenant_
TENANT_DB_USERNAME=tenant_admin
TENANT_DB_PASSWORD=StrongAdminPassword
TENANT_DB_HOST=127.0.0.1
TENANT_DB_PORT=3306
TENANT_DB_CONNECTION=mysql

# FastPanel settings (if using API)
FASTPANEL_API_URL=https://your-panel.com/api
FASTPANEL_API_TOKEN=your_api_token
```

## Manual Database → FastPanel Linking

If you create a database manually in MySQL, follow these steps:

### 1. Verify Database Exists
```bash
sudo mysql -e "SHOW DATABASES LIKE 'tenant_alemaan_pk';"
```

### 2. Check FastPanel Status
```bash
php artisan fastpanel:databases --unassigned
```

### 3. Sync with FastPanel
```bash
# Import database into FastPanel metadata
sudo /usr/local/fastpanel2/fastpanel databases sync

# Verify import
php artisan fastpanel:databases | grep tenant_alemaan_pk
```

### 4. Assign Owner & Link Site
```bash
# Assign to FastPanel user ID 1
php artisan fastpanel:sync-database tenant_alemaan_pk --assign-user=1

# Also link to site ID 5
php artisan fastpanel:sync-database tenant_alemaan_pk \
  --assign-user=1 \
  --link-site=5
```

### 5. Grant MySQL User Access
```sql
-- Grant existing app user access to tenant DB
GRANT ALL PRIVILEGES ON `tenant_alemaan_pk`.* TO 'al_emaan_pk'@'localhost';
FLUSH PRIVILEGES;
```

## Troubleshooting

### Database Creation Fails
```bash
# Check database privileges
php artisan tenant:check-privileges

# Test CREATE privilege
sudo mysql -e "CREATE DATABASE test_create_privilege; DROP DATABASE test_create_privilege;"
```

### FastPanel Not Showing Database
```bash
# Force sync
sudo /usr/local/fastpanel2/fastpanel databases sync

# Check SQLite database directly
sudo sqlite3 /usr/local/fastpanel2/app/db/fastpanel2.db \
  "SELECT id,name,owner_id,site_id FROM db WHERE name='tenant_alemaan_pk';"
```

### User Assignment Issues
```bash
# List database user mappings
sudo sqlite3 /usr/local/fastpanel2/app/db/fastpanel2.db \
  "SELECT user_id,database_id FROM datbases_users;"

# Check database_user table
sudo sqlite3 /usr/local/fastpanel2/app/db/fastpanel2.db \
  "SELECT id,login,owner_id FROM database_user WHERE login='al_emaan_pk';"
```

## Security Best Practices

1. **Use Per-Tenant Database Users**: Each tenant should have its own MySQL user with privileges only on its database.

2. **Encrypt Database Credentials**: Store tenant database passwords encrypted in the tenant record.

3. **Limit Global Privileges**: Avoid granting global CREATE privileges to application users.

4. **Use FastPanel CLI**: Prefer FastPanel CLI over direct SQLite manipulation for metadata consistency.

5. **Backup Before Changes**: Always backup FastPanel SQLite DB before manual modifications.

## Migration from Multi-Action Commands

### Old Command → New Command Mapping

**Tenant Management:**
- `tenant:manage create` → `tenant:create-fastpanel`
- `tenant:manage list` → Keep existing (single action)
- `tenant:manage delete` → Keep existing (single action)
- `tenant:manage status` → Keep existing (single action)
- `tenant:manage health` → Keep existing (single action)

**Database Operations:**
- `tenant:db migrate` → Keep existing (single action)
- `tenant:db seed` → Keep existing (single action)
- `tenant:db migrate:fresh` → Keep existing (single action)

**FastPanel Operations:**
- New: `fastpanel:users`
- New: `fastpanel:databases`  
- New: `fastpanel:sync-database`

### Gradual Migration Strategy

1. **Phase 1**: Add new single-purpose commands alongside existing multi-action commands
2. **Phase 2**: Update documentation to reference new commands
3. **Phase 3**: Add deprecation warnings to old multi-action commands
4. **Phase 4**: Remove multi-action commands in next major version

This approach ensures backward compatibility while moving toward better command organization.
