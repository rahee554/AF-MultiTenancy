<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Exception;

class RedisHelper
{
    /**
     * Check if Redis is available and working
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        try {
            // Check if phpredis extension is loaded
            if (!extension_loaded('redis')) {
                return false;
            }

            // Check if Redis client can connect and ping
            $redis = new \Redis();
            $connected = $redis->connect(
                config('database.redis.default.host', '127.0.0.1'),
                config('database.redis.default.port', 6379),
                config('database.redis.default.timeout', 5)
            );

            if (!$connected) {
                return false;
            }

            // Test ping
            $pong = $redis->ping();
            $redis->close();

            return $pong === '+PONG' || $pong === 'PONG' || $pong === true;

        } catch (Exception $e) {
            Log::debug('Redis availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the appropriate cache driver based on Redis availability
     *
     * @return string
     */
    public static function getCacheDriver(): string
    {
        if (static::isAvailable()) {
            return 'redis';
        }

        return config('cache.fallback_driver', 'database');
    }

    /**
     * Get cache store with fallback
     *
     * @param string|null $preferredStore
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public static function getStore(string $preferredStore = null): \Illuminate\Contracts\Cache\Repository
    {
        $store = $preferredStore ?: static::getCacheDriver();
        
        try {
            return Cache::store($store);
        } catch (Exception $e) {
            Log::warning("Failed to connect to {$store} cache, falling back to database", [
                'error' => $e->getMessage()
            ]);
            
            return Cache::store('database');
        }
    }

    /**
     * Test Redis connection with detailed information
     *
     * @return array
     */
    public static function testConnection(): array
    {
        $result = [
            'available' => false,
            'extension_loaded' => false,
            'connection' => false,
            'ping_response' => null,
            'server_info' => null,
            'error' => null,
        ];

        try {
            // Check extension
            $result['extension_loaded'] = extension_loaded('redis');
            if (!$result['extension_loaded']) {
                $result['error'] = 'phpredis extension not loaded';
                return $result;
            }

            // Test connection
            $redis = new \Redis();
            $host = config('database.redis.default.host', '127.0.0.1');
            $port = config('database.redis.default.port', 6379);
            $timeout = config('database.redis.default.timeout', 5);

            $result['connection'] = $redis->connect($host, $port, $timeout);
            if (!$result['connection']) {
                $result['error'] = "Cannot connect to Redis at {$host}:{$port}";
                return $result;
            }

            // Test ping
            $result['ping_response'] = $redis->ping();
            $result['available'] = in_array($result['ping_response'], ['+PONG', 'PONG', true]);

            // Get server info if available
            if ($result['available']) {
                $info = $redis->info();
                $result['server_info'] = [
                    'redis_version' => $info['redis_version'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                    'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 'unknown',
                ];
            }

            $redis->close();

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get Redis statistics
     *
     * @return array
     */
    public static function getStats(): array
    {
        if (!static::isAvailable()) {
            return ['available' => false];
        }

        try {
            $redis = new \Redis();
            $redis->connect(
                config('database.redis.default.host', '127.0.0.1'),
                config('database.redis.default.port', 6379)
            );

            $info = $redis->info();
            $stats = [
                'available' => true,
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => 0,
            ];

            // Calculate hit rate
            $hits = $stats['keyspace_hits'];
            $misses = $stats['keyspace_misses'];
            if ($hits + $misses > 0) {
                $stats['hit_rate'] = round(($hits / ($hits + $misses)) * 100, 2);
            }

            $redis->close();
            return $stats;

        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear all Redis cache
     *
     * @return bool
     */
    public static function flushCache(): bool
    {
        if (!static::isAvailable()) {
            return false;
        }

        try {
            $redis = new \Redis();
            $redis->connect(
                config('database.redis.default.host', '127.0.0.1'),
                config('database.redis.default.port', 6379)
            );

            $result = $redis->flushDB();
            $redis->close();

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to flush Redis cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear Redis cache by pattern
     *
     * @param string $pattern
     * @return bool
     */
    public static function flushPattern(string $pattern): bool
    {
        if (!static::isAvailable()) {
            return false;
        }

        try {
            $redis = new \Redis();
            $redis->connect(
                config('database.redis.default.host', '127.0.0.1'),
                config('database.redis.default.port', 6379)
            );

            // Get all keys matching pattern
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
            }

            $redis->close();
            return true;

        } catch (Exception $e) {
            Log::error('Failed to flush Redis pattern: ' . $e->getMessage());
            return false;
        }
    }
}
