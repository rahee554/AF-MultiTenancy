# MySQL Configuration Template for Multi-Tenant Laravel Applications

This configuration file provides optimized MySQL settings for multi-tenant Laravel applications using the artflow-studio/tenancy package.

## File Location
Save this configuration as `my.cnf` (Linux/Mac) or `my.ini` (Windows) in your MySQL configuration directory:
- **Linux**: `/etc/mysql/my.cnf` or `/etc/my.cnf`
- **Windows**: `C:\ProgramData\MySQL\MySQL Server X.X\my.ini`
- **Docker**: Mount as volume to `/etc/mysql/conf.d/tenancy.cnf`

## Configuration

```ini
# MySQL Optimization for Multi-Tenant Laravel Application
# artflow-studio/tenancy package optimizations

[mysqld]
# ==============================================================================
# CONNECTION SETTINGS - CRITICAL FOR MULTI-TENANCY
# ==============================================================================

# Maximum number of simultaneous connections
# Set higher for multi-tenant applications (default: 151)
max_connections = 500

# Maximum connections per user account
max_user_connections = 400

# Connection timeouts
connect_timeout = 10
wait_timeout = 300
interactive_timeout = 300

# Thread and Connection Pool Optimization
thread_cache_size = 100
thread_pool_size = 16
max_connect_errors = 100000

# ==============================================================================
# MEMORY AND PERFORMANCE SETTINGS
# ==============================================================================

# InnoDB Buffer Pool - Most important setting for performance
# Set to 70-80% of available RAM for dedicated MySQL servers
innodb_buffer_pool_size = 1G

# InnoDB Log Settings
innodb_log_file_size = 256M
innodb_log_buffer_size = 16M
innodb_flush_log_at_trx_commit = 2

# Query Cache (Disabled for better multi-tenant performance)
query_cache_size = 0
query_cache_type = 0

# MyISAM Settings (if used)
key_buffer_size = 64M
sort_buffer_size = 2M
read_buffer_size = 2M
read_rnd_buffer_size = 4M

# Temporary Tables
tmp_table_size = 64M
max_heap_table_size = 64M

# ==============================================================================
# NETWORK SETTINGS
# ==============================================================================

# Maximum packet size
max_allowed_packet = 64M
net_buffer_length = 16K

# ==============================================================================
# SECURITY AND ISOLATION FOR MULTI-TENANCY
# ==============================================================================

# Disable local file loading for security
local_infile = 0

# Hide database list from unauthorized users
skip_show_database = 1

# ==============================================================================
# INNODB SETTINGS FOR MULTI-TENANT PERFORMANCE
# ==============================================================================

# File per table (recommended for multi-tenancy)
innodb_file_per_table = 1

# Open files limit
innodb_open_files = 2000

# I/O settings for better performance
innodb_io_capacity = 2000
innodb_read_io_threads = 8
innodb_write_io_threads = 8

# ==============================================================================
# BINARY LOGGING (FOR REPLICATION/BACKUP)
# ==============================================================================

# Enable binary logging
log_bin = mysql-bin
binlog_format = ROW
expire_logs_days = 7

# ==============================================================================
# MONITORING AND DEBUGGING
# ==============================================================================

# Slow Query Log for Performance Monitoring
slow_query_log = 1
long_query_time = 2.0
log_queries_not_using_indexes = 1

# General log (disable in production)
# general_log = 1
# general_log_file = /var/log/mysql/general.log

# Error log
log_error = /var/log/mysql/error.log

# ==============================================================================
# CHARSET AND COLLATION
# ==============================================================================

# Default character set and collation
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci

# Ensure proper character set handling
init_connect = 'SET NAMES utf8mb4'

[client]
default_character_set = utf8mb4

[mysql]
default_character_set = utf8mb4
```

## Important Notes

### 1. Memory Settings
- Adjust `innodb_buffer_pool_size` based on your server's RAM
- For servers with 4GB RAM: set to `2G`
- For servers with 8GB RAM: set to `6G`
- For servers with 16GB+ RAM: set to 70-80% of total RAM

### 2. Connection Limits
- `max_connections = 500` supports approximately 20-50 tenants with moderate load
- Increase for more tenants or higher concurrent usage
- Monitor `SHOW STATUS LIKE 'Threads_connected'` to track usage

### 3. File Permissions
Ensure MySQL has proper permissions to write to log directories:
```bash
sudo chown mysql:mysql /var/log/mysql/
sudo chmod 755 /var/log/mysql/
```

### 4. Restart MySQL
After applying configuration:
```bash
# Linux/Mac
sudo systemctl restart mysql

# Windows (as Administrator)
net stop mysql
net start mysql

# Docker
docker restart your-mysql-container
```

## Validation

After applying the configuration, verify settings:

```sql
-- Check max connections
SHOW VARIABLES LIKE 'max_connections';

-- Check InnoDB buffer pool
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';

-- Check current connections
SHOW STATUS LIKE 'Threads_connected';

-- Check if binary logging is enabled
SHOW VARIABLES LIKE 'log_bin';
```

## Performance Monitoring Queries

```sql
-- Monitor connection usage
SELECT 
    VARIABLE_VALUE as Current_Connections,
    @@max_connections as Max_Connections,
    ROUND((VARIABLE_VALUE / @@max_connections) * 100, 2) as Connection_Usage_Percent
FROM INFORMATION_SCHEMA.SESSION_STATUS 
WHERE VARIABLE_NAME = 'Threads_connected';

-- Monitor InnoDB buffer pool efficiency
SELECT 
    ROUND((1 - (VARIABLE_VALUE / (SELECT VARIABLE_VALUE 
        FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
        WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads'))) * 100, 2) as Buffer_Pool_Hit_Rate
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests';

-- Check slow queries
SHOW STATUS LIKE 'Slow_queries';
```

## Docker Compose Example

```yaml
version: '3.8'
services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: your_password
      MYSQL_DATABASE: tenancy_central
    volumes:
      - mysql_data:/var/lib/mysql
      - ./mysql-tenancy.cnf:/etc/mysql/conf.d/tenancy.cnf:ro
    ports:
      - "3306:3306"
    restart: unless-stopped

volumes:
  mysql_data:
```

## Related Documentation

- [Database Template Configuration](./database-template.md)
- [PDO Configuration Guide](./pdo-configuration.md)
- [Performance Tuning Guide](../performance/tuning.md)
- [Multi-Tenant Best Practices](../guides/best-practices.md)
