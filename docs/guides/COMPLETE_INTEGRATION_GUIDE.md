# Artflow Studio Tenancy - Complete Integration Guide

## Overview

This guide covers the complete setup and integration of **Laravel Telescope**, **Laravel Horizon**, and **Laravel Octane** with the Artflow Studio Tenancy package for multi-tenant SaaS applications.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Telescope Integration](#telescope-integration)
3. [Horizon Integration](#horizon-integration)
4. [Octane Integration](#octane-integration)
5. [Multi-Project Dashboard](#multi-project-dashboard)
6. [Server Configuration](#server-configuration)
7. [FastPanel Configuration](#fastpanel-configuration)
8. [API Documentation](#api-documentation)
9. [Troubleshooting](#troubleshooting)
10. [Performance Optimization](#performance-optimization)

## Prerequisites

- Laravel 11+ application with Artflow Studio Tenancy package installed
- PHP 8.2+ with required extensions
- Redis server (for Horizon and caching)
- MySQL/PostgreSQL database
- Composer and Node.js/npm

## Telescope Integration

### Installation

```bash
# Install Laravel Telescope
composer require laravel/telescope

# Publish and run migrations (on central database)
php artisan telescope:install
php artisan migrate
```

### Configuration

1. **Environment Variables** (`.env`):
```env
TELESCOPE_ENABLED=true
ARTFLOW_TELESCOPE_ENABLED=true
```

2. **Telescope Configuration** (`config/telescope.php`):
```php
<?php

return [
    'storage' => [
        'database' => [
            // IMPORTANT: Use central database connection
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],
    
    'watchers' => [
        // Enable watchers as needed
        Telescope\Watchers\QueryWatcher::class => env('TELESCOPE_QUERY_WATCHER', true),
        Telescope\Watchers\RequestWatcher::class => env('TELESCOPE_REQUEST_WATCHER', true),
        // ... other watchers
    ],
];
```

3. **Tenancy Configuration** (`config/tenancy.php`):
```php
'features' => [
    // Both features work together for enhanced tagging
    \Stancl\Tenancy\Features\TelescopeTags::class,
    \ArtflowStudio\Tenancy\Features\EnhancedTelescopeTags::class,
],
```

### Features

- **Automatic Tenant Tagging**: All Telescope entries are tagged with:
  - `tenant:{id}` - Tenant ID
  - `tenant_name:{name}` - Tenant name (spaces replaced with underscores)
  - `domain:{domain}` - Primary domain
  - `tenant_status:{status}` - Tenant status
  - `project:{id}` - Project identifier for multi-project setups
  - `environment:{env}` - Application environment

- **Central Database Storage**: All Telescope data is stored in the central database for easy analysis across all tenants.

## Horizon Integration

### Installation

```bash
# Install Laravel Horizon
composer require laravel/horizon

# Publish configuration
php artisan horizon:install

# Run migrations (central database)
php artisan migrate
```

### Configuration

1. **Environment Variables**:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
ARTFLOW_HORIZON_ENABLED=true
```

2. **Horizon Configuration** (`config/horizon.php`):
```php
<?php

return [
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'tenant'],
                'balance' => 'auto',
                'processes' => 10,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
        
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'tenant'],
                'balance' => 'simple',
                'processes' => 3,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
    ],
];
```

3. **Queue Configuration** (`config/queue.php`):
```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => null,
    ],
],
```

### Features

- **Tenant-Aware Job Tagging**: Jobs are automatically tagged with:
  - `tenant:{id}` - Tenant ID
  - `tenant_name:{name}` - Tenant name
  - `domain:{domain}` - Tenant domain
  - `project:{id}` - Project identifier

- **Queue Isolation**: Different queue names for tenant vs central operations
- **Monitoring Dashboard**: Real-time monitoring of queue performance per tenant

## Octane Integration

### Installation

Choose between Swoole or RoadRunner:

#### Option A: Swoole
```bash
# Install Swoole extension (Ubuntu/Debian)
sudo pecl install swoole

# Install Octane with Swoole
composer require laravel/octane
php artisan octane:install --server=swoole
```

#### Option B: RoadRunner
```bash
# Install Octane with RoadRunner
composer require laravel/octane
php artisan octane:install --server=roadrunner
```

### Configuration

1. **Environment Variables**:
```env
OCTANE_SERVER=swoole  # or roadrunner
ARTFLOW_OCTANE_ENABLED=true
OCTANE_HTTPS=false
OCTANE_HOST=0.0.0.0
OCTANE_PORT=8000
OCTANE_WORKERS=4
OCTANE_TASK_WORKERS=6
OCTANE_MAX_REQUESTS=500
```

2. **Octane Configuration** (`config/octane.php`):
```php
<?php

return [
    'server' => env('OCTANE_SERVER', 'swoole'),
    
    'https' => env('OCTANE_HTTPS', false),
    
    'listeners' => [
        // Tenancy integration is handled automatically
        // by ArtflowStudio\Tenancy\Features\OctaneIntegration
    ],
    
    'warm' => [
        'config',
        'routes',
        'views',
    ],
    
    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],
];
```

3. **Tenancy Configuration**:
```php
'features' => [
    \ArtflowStudio\Tenancy\Features\OctaneIntegration::class,
],
```

### Running Octane

```bash
# Development
php artisan octane:start --watch

# Production
php artisan octane:start --workers=4 --task-workers=6 --max-requests=500
```

## Multi-Project Dashboard

### Configuration

1. **Environment Variables**:
```env
ARTFLOW_PROJECT_ID=my-saas-app
ARTFLOW_PROJECT_NAME="My SaaS Application"
ARTFLOW_PROJECT_API_KEY=your-api-key-here
ARTFLOW_DASHBOARD_URL=https://dashboard.artflow.studio
ARTFLOW_DASHBOARD_ENABLED=true
ARTFLOW_WEBHOOK_SECRET=your-webhook-secret
ARTFLOW_SYNC_INTERVAL=300
```

2. **Features**:
- Centralized tenant management across multiple projects
- Real-time metrics aggregation
- Health monitoring
- Automated sync to central dashboard

### API Endpoints

```bash
# Get all tenants with project context
GET /api/tenancy/multi-project/tenants

# Get aggregated statistics
GET /api/tenancy/multi-project/stats

# Get specific tenant details
GET /api/tenancy/multi-project/tenants/{id}

# Sync to central dashboard
POST /api/tenancy/multi-project/sync

# Get system health
GET /api/tenancy/multi-project/health

# Get real-time metrics
GET /api/tenancy/multi-project/metrics/realtime
```

## Server Configuration

### Nginx Configuration for Octane

Create `/etc/nginx/sites-available/your-app`:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 80;
    listen [::]:80;
    server_name example.com *.example.com;
    
    location / {
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_pass http://127.0.0.1:8000;
    }
}
```

### Apache Configuration for Octane

Create virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    ServerAlias *.example.com
    
    ProxyPreserveHost On
    ProxyRequests Off
    ProxyPass / http://127.0.0.1:8000/
    ProxyPassReverse / http://127.0.0.1:8000/
    
    # WebSocket support
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/?(.*) "ws://127.0.0.1:8000/$1" [P,L]
</VirtualHost>
```

## FastPanel Configuration

### Environment Setup

1. **FastPanel Environment Variables**:
```env
FASTPANEL_ENABLED=true
FASTPANEL_API_URL=https://your-fastpanel-url/api
FASTPANEL_API_KEY=your-fastpanel-api-key
ARTFLOW_SERVER_TYPE=fastpanel
```

### Octane with FastPanel

1. **Create Octane Service** (via FastPanel or SSH):
```bash
# Create systemd service file
sudo tee /etc/systemd/system/octane-app.service > /dev/null <<EOF
[Unit]
Description=Laravel Octane Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/your-app
ExecStart=/usr/bin/php artisan octane:start --host=0.0.0.0 --port=8000 --workers=4
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

# Enable and start service
sudo systemctl enable octane-app
sudo systemctl start octane-app
```

2. **Configure Nginx Proxy** (through FastPanel UI):
   - Add new site in FastPanel
   - Set up reverse proxy to `http://127.0.0.1:8000`
   - Configure SSL if needed
   - Set up domain routing for multi-tenancy

### Domain Management

FastPanel integration allows automatic domain management:

```php
// In your tenant creation logic
$tenant = new Tenant([
    'name' => 'Client Name',
    'status' => 'active',
]);
$tenant->save();

// Create domain through FastPanel API
$tenant->domains()->create([
    'domain' => 'client.example.com',
]);

// Optionally create subdomain in FastPanel
if (config('artflow-tenancy.server.fastpanel.enabled')) {
    app(FastPanelService::class)->createSubdomain('client.example.com');
}
```

## Performance Optimization

### 1. Redis Configuration

```redis
# /etc/redis/redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### 2. PHP Configuration

```ini
# /etc/php/8.2/fpm/php.ini
memory_limit = 512M
max_execution_time = 300
max_input_vars = 10000
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0  # Production only
```

### 3. Database Optimization

```sql
-- MySQL configuration
SET innodb_buffer_pool_size = '1G';
SET innodb_log_file_size = '256M';
SET innodb_flush_log_at_trx_commit = 2;
SET query_cache_type = 1;
SET query_cache_size = 256M;
```

### 4. Octane Optimization

```php
// config/octane.php
'tables' => [
    'cache' => 1000,
    'telescope_entries' => 1000,
],

'cache' => [
    'rows' => 1000,
    'bytes' => 10000,
],
```

## Monitoring Commands

### Available Artisan Commands

```bash
# Health checks
php artisan tenancy:health
php artisan tenancy:test-comprehensive

# Dashboard sync
php artisan tenancy:sync-dashboard

# System monitoring
php artisan horizon:status
php artisan telescope:clear
php artisan octane:status

# Tenant management
php artisan tenants:list
php artisan tenants:migrate
php artisan tenants:seed
```

## Troubleshooting

### Common Issues

1. **Telescope Not Working**:
   - Ensure central database connection is configured
   - Check Telescope migrations are run on central DB
   - Verify `TELESCOPE_ENABLED=true`

2. **Horizon Jobs Not Processing**:
   - Check Redis connection
   - Verify queue workers are running: `php artisan horizon:status`
   - Ensure proper queue configuration

3. **Octane Memory Issues**:
   - Adjust `OCTANE_MAX_REQUESTS` to lower value
   - Increase PHP memory limit
   - Monitor with `php artisan octane:status`

4. **Multi-Project Dashboard Sync Failing**:
   - Verify API credentials
   - Check webhook signature
   - Review logs: `tail -f storage/logs/laravel.log`

### Debug Commands

```bash
# Check tenant database connections
php artisan tenancy:test-connections

# Verify integrations
php artisan tenancy:integration-status

# Clear all caches
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
```

## Security Considerations

1. **API Keys**: Store all API keys in environment variables
2. **Webhook Signatures**: Always verify webhook signatures
3. **Database Isolation**: Ensure tenant databases are properly isolated
4. **SSL/TLS**: Use HTTPS for all external communications
5. **Rate Limiting**: Implement rate limiting on API endpoints

## Next Steps

1. **Set up monitoring alerts** for system health
2. **Configure backup strategies** for tenant databases
3. **Implement automated scaling** based on load
4. **Set up CI/CD pipelines** for deployment
5. **Create custom dashboards** for specific metrics

## Support

For issues and questions:
- Check the [troubleshooting section](#troubleshooting)
- Review Laravel documentation for Telescope, Horizon, and Octane
- Contact Artflow Studio support for package-specific issues

---

**Last Updated**: August 2025  
**Package Version**: Latest  
**Laravel Version**: 11+
