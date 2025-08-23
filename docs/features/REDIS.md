# 🚀 Redis Configuration Guide

**ArtFlow Studio Tenancy Package - Redis Integration**

This guide covers Redis setup, configuration, testing, and performance optimization for multi-tenant applications.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Redis Installation](#redis-installation)
3. [PHP Redis Extension](#php-redis-extension)
4. [Laravel Configuration](#laravel-configuration)
5. [Tenancy Configuration](#tenancy-configuration)
6. [Testing Redis](#testing-redis)
7. [Performance Optimization](#performance-optimization)
8. [Troubleshooting](#troubleshooting)
9. [Production Deployment](#production-deployment)

---

## 🔧 Prerequisites

Before configuring Redis, ensure you have:

- Ubuntu/Debian or compatible Linux distribution
- PHP 8.1+ with FPM
- Laravel 10+ application
- Root or sudo access
- ArtFlow Studio Tenancy Package installed

---

## 📦 Redis Installation

### System Redis Installation

```bash
# Update package index
sudo apt update

# Install Redis server
sudo apt install redis-server

# Start and enable Redis service
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verify Redis is running
sudo systemctl status redis-server
redis-cli ping  # Should return PONG
```

### Redis Configuration

Edit Redis configuration file:

```bash
sudo nano /etc/redis/redis.conf
```

Key settings for production:

```conf
# Bind to localhost only (secure by default)
bind 127.0.0.1 -::1

# Enable protected mode
protected-mode yes

# Set max memory (adjust based on your server)
maxmemory 256mb
maxmemory-policy allkeys-lru

# Enable persistence (optional)
save 900 1     # Save if at least 1 key changed in 900 seconds
save 300 10    # Save if at least 10 keys changed in 300 seconds
save 60 10000  # Save if at least 10000 keys changed in 60 seconds

# Set database count (default 16 is usually sufficient)
databases 16

# Security: require password (uncomment and set strong password)
# requirepass YourStrongPasswordHere
```

Restart Redis after configuration changes:

```bash
sudo systemctl restart redis-server
```

---

## 🔗 PHP Redis Extension

### Install phpredis Extension

```bash
# Install build dependencies
sudo apt install -y php8.3-dev php-pear build-essential

# Install phpredis via PECL
sudo pecl install redis

# Create module configuration
echo "extension=redis.so" | sudo tee /etc/php/8.3/mods-available/redis.ini

# Enable the extension
sudo phpenmod redis

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Verify installation
php -m | grep redis
php -r "echo extension_loaded('redis') ? 'Redis: '.phpversion('redis').PHP_EOL : 'Redis not installed'.PHP_EOL;"
```

### Alternative: Predis (Pure PHP)

If you prefer a pure PHP solution:

```bash
# Install via Composer
composer require predis/predis

# Update .env
REDIS_CLIENT=predis
```

---

## ⚙️ Laravel Configuration

### Environment Variables

Add to your `.env` file:

```env
# Redis Connection
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Cache Configuration
CACHE_DRIVER=redis
CACHE_PREFIX=

# Redis Cache Database (separate from default)
REDIS_CACHE_DB=1

# Session Configuration (optional)
SESSION_DRIVER=redis
SESSION_CONNECTION=default

# Queue Configuration (optional)
QUEUE_CONNECTION=redis
```

### Redis Database Configuration

Update `config/database.php`:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_timeout' => 60,
        'context' => [
            // 'auth' => ['username', 'secret'],
            // 'stream' => ['verify_peer' => false],
        ],
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
        'read_timeout' => 60,
    ],

    'session' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '2'),
        'read_timeout' => 60,
    ],
],
```

### Cache Configuration

Update `config/cache.php`:

```php
'default' => env('CACHE_DRIVER', 'database'),

'stores' => [
    // ... other stores

    'redis' => [
        'driver' => 'redis',
        'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
        'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
    ],
],
```

---

## 🏢 Tenancy Configuration

### Enable Redis in Tenancy

Update `config/tenancy.php`:

```php
'bootstrappers' => [
    \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class, // Enabled
],

'redis' => [
    'prefix_base' => 'tenant',
    'prefixed_connections' => [
        'default',
        'cache',
        'session',
    ],
],
```

### Tenancy-Specific Configuration

Update `config/artflow-tenancy.php`:

```php
'cache' => [
    'driver' => env('TENANT_CACHE_DRIVER', 'redis'), // Use Redis by default
    'prefix' => env('TENANT_CACHE_PREFIX', 'tenant_'),
    'default_ttl' => env('TENANT_CACHE_TTL', 3600),
    'stats_ttl' => env('TENANT_CACHE_STATS_TTL', 300),
],
```

### Environment Variables for Tenancy

Add to `.env`:

```env
# Tenancy Cache Configuration
TENANT_CACHE_DRIVER=redis
TENANCY_CACHE_STORE=redis
TENANCY_CACHED_LOOKUP=true
TENANCY_CACHE_TTL=3600

# Fallback Configuration
CACHE_FALLBACK_DRIVER=database
```

---

## 🧪 Testing Redis

### Basic Redis Test

```bash
# Test Redis CLI connection
redis-cli ping

# Test Redis from PHP
php -r "
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
echo $redis->ping() ? 'Redis OK' : 'Redis Failed';
echo PHP_EOL;
"
```

### Laravel Redis Test

```bash
# Test Redis configuration
php artisan tinker
>>> Redis::ping()
>>> Cache::store('redis')->put('test', 'value', 60)
>>> Cache::store('redis')->get('test')
```

### Tenancy Redis Test

```bash
# Run comprehensive Redis test
php artisan tenancy:test-redis --detailed

# Test with specific tenant context
php artisan tenant:run --tenant={tenant-id} "php -r \"Cache::put('test', 'tenant-value', 60); echo Cache::get('test');\""
```

### Stress Test Redis

Create a stress test command:

```bash
# Create stress test
php artisan make:command Redis:StressTest

# Run stress test (if implemented)
php artisan redis:stress-test --connections=100 --operations=1000
```

---

## ⚡ Performance Optimization

### Redis Configuration Tuning

```conf
# /etc/redis/redis.conf

# Memory optimizations
maxmemory 512mb
maxmemory-policy allkeys-lru
maxmemory-samples 5

# Network optimizations
tcp-keepalive 300
timeout 300

# Persistence (adjust based on needs)
save 900 1
save 300 10
save 60 10000

# Disable RDB if using AOF
# save ""

# AOF configuration for durability
appendonly yes
appendfsync everysec
no-appendfsync-on-rewrite no
```

### PHP Redis Optimizations

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'app_'),
        'persistent' => env('REDIS_PERSISTENT', true), // Use persistent connections
        'serializer' => Redis::SERIALIZER_MSGPACK, // Fast serialization
        'compression' => Redis::COMPRESSION_LZ4, // Enable compression
    ],
    
    'default' => [
        // ... connection config
        'read_timeout' => 60,
        'connect_timeout' => 5,
        'retry_interval' => 100,
    ],
],
```

### Cache Strategy

```php
// Tenant-specific cache keys
$cacheKey = "tenant:{$tenantId}:users:all";

// Use cache tags for organized invalidation
Cache::tags(['tenant:'.$tenantId, 'users'])->put($cacheKey, $data, 3600);

// Bulk cache operations
Cache::many([
    'key1' => 'value1',
    'key2' => 'value2',
]);
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Redis Connection Failed

```bash
# Check if Redis is running
sudo systemctl status redis-server

# Check Redis logs
sudo journalctl -u redis-server -f

# Test connection manually
redis-cli ping
```

#### 2. PHP Redis Extension Not Found

```bash
# Verify extension is loaded
php -m | grep redis

# Check PHP configuration
php --ini | grep redis

# Reinstall if necessary
sudo pecl uninstall redis
sudo pecl install redis
sudo phpenmod redis
sudo systemctl restart php8.3-fpm
```

#### 3. Permission Denied

```bash
# Check Redis socket permissions
ls -la /var/run/redis/

# Check Redis log permissions
sudo chown redis:redis /var/log/redis/redis-server.log
```

#### 4. Memory Issues

```bash
# Check Redis memory usage
redis-cli info memory

# Monitor key patterns
redis-cli --scan --pattern "*" | head -20

# Clear cache if needed
php artisan cache:clear
redis-cli flushdb
```

### Debug Commands

```bash
# Redis monitoring
redis-cli monitor

# Redis client list
redis-cli client list

# Redis info
redis-cli info all

# Laravel cache debug
php artisan cache:table
php artisan config:show cache
```

---

## 🚀 Production Deployment

### Security Checklist

1. **Bind to localhost only** (unless clustering)
2. **Set strong password** in Redis configuration
3. **Use firewall rules** to restrict access
4. **Enable SSL/TLS** for remote connections
5. **Regular backups** of Redis data
6. **Monitor memory usage** and set limits
7. **Use Redis Sentinel** for high availability

### Monitoring

```bash
# Basic monitoring script
#!/bin/bash
echo "Redis Status: $(redis-cli ping)"
echo "Memory Usage: $(redis-cli info memory | grep used_memory_human)"
echo "Connected Clients: $(redis-cli info clients | grep connected_clients)"
echo "Total Keys: $(redis-cli dbsize)"
```

### Backup Strategy

```bash
# Create backup script
#!/bin/bash
BACKUP_DIR="/backup/redis"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
redis-cli bgsave
cp /var/lib/redis/dump.rdb $BACKUP_DIR/redis_backup_$DATE.rdb

# Compress old backups
find $BACKUP_DIR -name "*.rdb" -mtime +7 -exec gzip {} \;

# Remove old compressed backups
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete
```

### High Availability

For production environments, consider:

1. **Redis Sentinel** for automatic failover
2. **Redis Cluster** for horizontal scaling
3. **Load balancing** Redis connections
4. **Regular health checks** and monitoring

---

## 📚 Additional Resources

- [Redis Official Documentation](https://redis.io/documentation)
- [phpredis GitHub Repository](https://github.com/phpredis/phpredis)
- [Laravel Redis Documentation](https://laravel.com/docs/redis)
- [Stancl Tenancy Documentation](https://tenancyforlaravel.com/)

---

## 🆘 Support

If you encounter issues with Redis configuration:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review Laravel and Redis logs
3. Test Redis connectivity manually
4. Verify PHP extension installation
5. Check firewall and permission settings

For package-specific issues, refer to the main documentation or create an issue in the repository.

---

**Last Updated:** August 2025
**Version:** 2.0
**Compatibility:** Laravel 10+, PHP 8.1+, Redis 6.0+
