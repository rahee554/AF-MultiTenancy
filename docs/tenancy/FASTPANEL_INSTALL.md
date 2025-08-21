# FastPanel 2 Integration Guide

This guide covers the configuration and usage of Artflow Studio Tenancy package with FastPanel 2 on Ubuntu servers.

## Prerequisites

- Ubuntu server with FastPanel 2 already installed
- Root or administrative access to FastPanel
- Laravel application with Artflow Studio Tenancy package installed
- PHP 8.1+ and MySQL/MariaDB

## Configuration Steps

### 1. FastPanel API Access

1. Access FastPanel admin panel: `https://your-server:8443`
2. Login with root credentials
3. Navigate to **Settings** > **API**
4. Enable API access
5. Generate API token
6. Note down the API URL and token

### 2. Laravel Application Setup

#### Install Artflow Studio Tenancy Package

```bash
# Install via Composer
composer require artflow-studio/tenancy

# Publish configuration
php artisan vendor:publish --provider="ArtflowStudio\Tenancy\TenancyServiceProvider"

# Run migrations
php artisan migrate
```

#### Environment Configuration

Add FastPanel configuration to your `.env` file:

```env
# FastPanel 2 Configuration
FASTPANEL_URL=https://your-server:8443
FASTPANEL_API_TOKEN=your-api-token-here
FASTPANEL_ENABLED=true
FASTPANEL_CLI_PATH=/usr/local/fastpanel2/fastpanel

# Database Configuration for Tenancy
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_central_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Optional: Root credentials for database operations
DB_ROOT_USER=root
DB_ROOT_PASSWORD=your_root_password

# Tenancy Configuration
TENANCY_DATABASE_PREFIX=tenant_
TENANCY_CACHE_STORE=file
TENANCY_QUEUE_CONNECTION=sync
```

## 4. Command Reference

### Basic Commands

```bash
# Check server compatibility
php artisan tenant:server-check                    # All checks
php artisan tenant:server-check --fastpanel        # FastPanel specific
php artisan tenant:server-check --production       # Production readiness

# Check database privileges
php artisan tenant:check-privileges                # Current connection
php artisan tenant:check-privileges --user=myuser  # Specific user

# System health check (enhanced with privileges, file permissions)
php artisan tenant:manage health

# Create tenant with automatic privilege checking
php artisan tenant:manage create

# Link tenant to FastPanel
php artisan tenant:link-fastpanel                  # Interactive mode
php artisan tenant:link-fastpanel --tenant=uuid    # Specific tenant
```

### FastPanel Integration

```bash
# Link tenant to FastPanel 2 environment
php artisan tenant:link-fastpanel

# The command will:
# 1. List FastPanel users (via CLI: sudo /usr/local/fastpanel2/fastpanel --json users list)
# 2. Show websites for selected user
# 3. Display database options
# 4. Create database through FastPanel CLI for proper metadata
# 5. Store FastPanel configuration in tenant data
# 6. Create symlinks for file management
```

### Database Management Best Practices

```bash
# Recommended: Create database through FastPanel CLI
sudo /usr/local/fastpanel2/fastpanel databases create --name tenant_example_db --user example_user --password secure_password

# If database created outside FastPanel, sync metadata
sudo /usr/local/fastpanel2/fastpanel databases sync

# Verify database assignments
sudo /usr/local/fastpanel2/fastpanel --json databases list | jq .
sudo /usr/local/fastpanel2/fastpanel --json users list | jq .
```

#### Using FastPanel CLI for Database Creation

FastPanel stores metadata in `fastpanel2.db` (SQLite) with key tables:
- `db_servers`: Database server configurations
- `database_user`: Database users
- `databases_users`: Database user assignments

**Recommended approach for consistent metadata:**

```bash
# Create database through FastPanel CLI (recommended)
sudo /usr/local/fastpanel2/fastpanel databases create --name tenant_example_db --user example_user --password secure_password

# If database created outside panel, sync to import metadata
sudo /usr/local/fastpanel2/fastpanel databases sync

# Verify database assignments after creation
sudo /usr/local/fastpanel2/fastpanel --json databases list | jq .

# List users for verification
sudo /usr/local/fastpanel2/fastpanel --json users list | jq .
```

**Important Notes:**
- After syncing external databases, verify owner/assignments in FastPanel admin
- Owner might be set to panel admin and require manual association
- Always verify mappings after database creation

#### Create Dedicated Users

It's recommended to create dedicated users for your tenant applications:

1. **Access FastPanel Admin Panel**
   ```
   https://your-server:8443
   ```

2. **Create New User**
   - Navigate to **Users** > **Add User**
   - Set username (e.g., `tenant-admin`)
   - Set secure password
   - Assign appropriate permissions

3. **Database Privileges**
   
   Ensure the database user has the following privileges:
   ```sql
   GRANT CREATE, DROP, ALTER, SELECT, INSERT, UPDATE, DELETE 
   ON *.* TO 'your-db-user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### 4. Website Configuration

#### Create Website in FastPanel

1. **Add New Website**
   - Domain: `your-tenant-domain.com`
   - Document Root: `/var/www/your-tenant-domain.com`
   - PHP Version: 8.1+ (recommended 8.2)
   - Enable SSL if needed

2. **Configure Database**
   - Create database for the website
   - Assign database user with appropriate privileges
   - Note database credentials

3. **Set Up Document Root**
   ```bash
   # Ensure proper ownership
   chown -R www-data:www-data /var/www/your-tenant-domain.com
   chmod -R 755 /var/www/your-tenant-domain.com
   ```

### 5. Laravel Tenancy Integration

#### Register FastPanel Command

Add the FastPanel command to your `app/Console/Kernel.php`:

```php
protected $commands = [
    \ArtflowStudio\Tenancy\Commands\Tenancy\FastPanelCommand::class,
];
```

#### Link Tenant to FastPanel

Use the provided command to link tenants:

```bash
# Interactive mode
php artisan tenant:link-fastpanel

# With tenant UUID
php artisan tenant:link-fastpanel --tenant=your-tenant-uuid

# Force mode (no confirmation)
php artisan tenant:link-fastpanel --tenant=your-tenant-uuid --force
```

### 6. Database Privilege Management

The package automatically checks database privileges before creating tenants. If the configured user lacks CREATE DATABASE privileges, it will:

1. List available database users
2. Allow selection of privileged user (root, admin, etc.)
3. Temporarily switch connections for tenant creation
4. Restore original connection after creation

#### Manual Privilege Check

```bash
# Check current user privileges
php artisan tenant:check-privileges

# Test tenant creation with privilege validation
php artisan tenant:create "Test Tenant" example.com --check-privileges
```

### 7. Directory Structure

After successful integration, your structure should look like:

```
/var/www/your-tenant-domain.com/
├── public/           # Website public files
├── tenant/           # Symlink to tenant storage
├── .env             # Environment configuration
├── index.php        # Entry point
└── storage/         # Website storage
```

Tenant-specific files are stored in:
```
/storage/app/tenants/{tenant-uuid}/
```

### 8. SSL Configuration

#### Enable SSL for Tenant Domains

1. **Via FastPanel Interface**
   - Navigate to website settings
   - Enable SSL/TLS
   - Choose certificate source (Let's Encrypt, custom, etc.)

2. **Via Command Line (Let's Encrypt)**
   ```bash
   # Install Certbot if not available
   apt install certbot python3-certbot-nginx

   # Obtain certificate
   certbot --nginx -d your-tenant-domain.com
   ```

### 9. Troubleshooting

#### Common Issues

**1. API Connection Failed**
```bash
# Check FastPanel service status
systemctl status fastpanel

# Verify API token
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://your-server:8443/api/ping
```

**2. Database Privilege Issues**
```bash
# Check user privileges
mysql -u root -p -e "SHOW GRANTS FOR 'your-db-user'@'localhost';"

# Grant additional privileges
mysql -u root -p -e "GRANT CREATE ON *.* TO 'your-db-user'@'localhost';"
```

**3. File Permission Issues**
```bash
# Fix ownership
chown -R www-data:www-data /var/www/your-domain/

# Fix permissions
find /var/www/your-domain/ -type f -exec chmod 644 {} \;
find /var/www/your-domain/ -type d -exec chmod 755 {} \;
```

**4. Symlink Creation Failed**
```bash
# Ensure target directory exists
mkdir -p /storage/app/tenants/

# Create symlink manually
ln -s /storage/app/tenants/tenant-uuid /var/www/domain/tenant
```

### 10. Security Considerations

#### Best Practices

1. **Use Dedicated Database Users**
   - Create separate users for each tenant
   - Limit privileges to necessary operations only
   - Use strong passwords

2. **File System Isolation**
   - Use symlinks for tenant-specific files
   - Implement proper file permissions
   - Consider chroot jails for enhanced security

3. **Network Security**
   - Enable FastPanel firewall
   - Restrict API access to trusted IPs
   - Use SSL/TLS for all connections

4. **Regular Updates**
   - Keep FastPanel updated
   - Update PHP and database versions
   - Monitor security advisories

### 11. Performance Optimization

#### Recommended Settings

1. **PHP Configuration**
   ```ini
   # php.ini optimizations
   memory_limit = 256M
   max_execution_time = 300
   upload_max_filesize = 64M
   post_max_size = 64M
   opcache.enable = 1
   opcache.memory_consumption = 128
   ```

2. **Database Optimization**
   ```sql
   # MySQL optimizations
   SET GLOBAL innodb_buffer_pool_size = 1G;
   SET GLOBAL query_cache_size = 64M;
   SET GLOBAL max_connections = 200;
   ```

3. **Caching Configuration**
   ```env
   # Laravel caching
   CACHE_DRIVER=redis
   SESSION_DRIVER=redis
   QUEUE_CONNECTION=redis
   ```

### 12. Backup and Recovery

#### Backup Strategy

1. **Database Backups**
   ```bash
   # Automated backup script
   #!/bin/bash
   DATE=$(date +%Y%m%d_%H%M%S)
   mysqldump --all-databases > /backup/mysql_$DATE.sql
   gzip /backup/mysql_$DATE.sql
   ```

2. **File Backups**
   ```bash
   # Backup tenant files
   tar -czf /backup/tenants_$DATE.tar.gz /storage/app/tenants/
   
   # Backup website files
   rsync -av /var/www/ /backup/websites/
   ```

### 13. Monitoring and Logging

#### Log Files

- FastPanel logs: `/var/log/fastpanel/`
- PHP logs: `/var/log/php/`
- Nginx logs: `/var/log/nginx/`
- Laravel logs: `/storage/logs/`

#### Monitoring Commands

```bash
# Check tenant status
php artisan tenant:list

# Monitor system resources
php artisan tenant:system-monitoring

# Check database connections
php artisan tenant:check-connections
```

## Support

For additional support:

- FastPanel Documentation: https://fastpanel.direct/docs
- Artflow Studio Support: [Your support contact]
- Community Forum: [Your forum link]

## License

This integration guide is provided under the same license as the Artflow Studio Tenancy package.
