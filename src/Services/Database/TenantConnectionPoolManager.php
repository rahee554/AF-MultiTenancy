<?php

namespace ArtflowStudio\Tenancy\Services\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ArtflowStudio\Tenancy\Models\Tenant;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Multi-Tenant Connection Pool Manager
 * 
 * Manages persistent database connections for multiple tenants to improve performance
 * while ensuring proper isolation and preventing connection leaks.
 */
class TenantConnectionPoolManager
{
    /** @var array<string, ConnectionInterface> */
    private array $connectionPool = [];
    
    /** @var array<string, int> */
    private array $connectionUsage = [];
    
    /** @var array<string, float> */
    private array $lastAccessed = [];
    
    private int $maxPoolSize;
    private int $maxIdleTime;
    private int $connectionTimeout;
    private bool $enablePooling;
    
    public function __construct(
        private DatabaseManager $databaseManager,
        int $maxPoolSize = 50,
        int $maxIdleTime = 300, // 5 minutes
        int $connectionTimeout = 10,
        bool $enablePooling = true
    ) {
        $this->maxPoolSize = $maxPoolSize;
        $this->maxIdleTime = $maxIdleTime;
        $this->connectionTimeout = $connectionTimeout;
        $this->enablePooling = $enablePooling;
    }

    /**
     * Get a database connection for the specified tenant
     */
    public function getConnection(string $tenantId): ConnectionInterface
    {
        if (!$this->enablePooling) {
            return $this->createTenantConnection($tenantId);
        }

        $connectionKey = $this->getConnectionKey($tenantId);

        // Return existing connection if available and valid
        if ($this->hasValidConnection($connectionKey)) {
            $this->updateConnectionUsage($connectionKey);
            return $this->connectionPool[$connectionKey];
        }

        // Clean up expired connections
        $this->cleanupExpiredConnections();

        // Create new connection if pool has space
        if (count($this->connectionPool) < $this->maxPoolSize) {
            return $this->createPooledConnection($tenantId, $connectionKey);
        }

        // Pool is full - remove least recently used connection
        $this->removeLeastRecentlyUsed();
        return $this->createPooledConnection($tenantId, $connectionKey);
    }

    /**
     * Release a connection back to the pool
     */
    public function releaseConnection(string $tenantId): void
    {
        $connectionKey = $this->getConnectionKey($tenantId);
        
        if (isset($this->connectionPool[$connectionKey])) {
            $this->lastAccessed[$connectionKey] = microtime(true);
            Log::debug("Connection released for tenant: {$tenantId}");
        }
    }

    /**
     * Remove a specific tenant's connection from the pool
     */
    public function removeConnection(string $tenantId): void
    {
        $connectionKey = $this->getConnectionKey($tenantId);
        
        if (isset($this->connectionPool[$connectionKey])) {
            $this->closeConnection($connectionKey);
            unset($this->connectionPool[$connectionKey]);
            unset($this->connectionUsage[$connectionKey]);
            unset($this->lastAccessed[$connectionKey]);
            
            Log::info("Connection removed from pool for tenant: {$tenantId}");
        }
    }

    /**
     * Clear all connections in the pool
     */
    public function clearPool(): void
    {
        foreach ($this->connectionPool as $connectionKey => $connection) {
            $this->closeConnection($connectionKey);
        }
        
        $this->connectionPool = [];
        $this->connectionUsage = [];
        $this->lastAccessed = [];
        
        Log::info('Connection pool cleared');
    }

    /**
     * Get pool statistics
     */
    public function getPoolStatistics(): array
    {
        return [
            'pool_size' => count($this->connectionPool),
            'max_pool_size' => $this->maxPoolSize,
            'active_connections' => array_keys($this->connectionPool),
            'usage_counts' => $this->connectionUsage,
            'last_accessed' => array_map(function($timestamp) {
                return date('Y-m-d H:i:s', (int)$timestamp);
            }, $this->lastAccessed),
            'pooling_enabled' => $this->enablePooling,
        ];
    }

    /**
     * Execute a callback within a tenant context using pooled connection
     */
    public function runInTenantContext(string $tenantId, callable $callback)
    {
        $connection = $this->getConnection($tenantId);
        
        // Set the tenant context
        $previousConnection = DB::getDefaultConnection();
        $connectionName = $this->getConnectionName($tenantId);
        
        try {
            // Configure the connection for this tenant
            $this->configureTenantConnection($connectionName, $tenantId);
            
            // Set as default connection
            DB::setDefaultConnection($connectionName);
            
            // Execute callback
            $result = $callback($connection);
            
            // Update usage statistics
            $this->updateConnectionUsage($this->getConnectionKey($tenantId));
            
            return $result;
            
        } finally {
            // Restore previous connection
            DB::setDefaultConnection($previousConnection);
            
            // Release connection back to pool
            $this->releaseConnection($tenantId);
        }
    }

    /**
     * Create a new tenant database connection
     */
    private function createTenantConnection(string $tenantId): ConnectionInterface
    {
        $connectionName = $this->getConnectionName($tenantId);
        $this->configureTenantConnection($connectionName, $tenantId);
        
        return $this->databaseManager->connection($connectionName);
    }

    /**
     * Create a pooled connection for the tenant
     */
    private function createPooledConnection(string $tenantId, string $connectionKey): ConnectionInterface
    {
        $connection = $this->createTenantConnection($tenantId);
        
        $this->connectionPool[$connectionKey] = $connection;
        $this->connectionUsage[$connectionKey] = 1;
        $this->lastAccessed[$connectionKey] = microtime(true);
        
        Log::debug("New pooled connection created for tenant: {$tenantId}");
        
        return $connection;
    }

    /**
     * Configure tenant database connection
     */
    private function configureTenantConnection(string $connectionName, string $tenantId): void
    {
        // Get the tenant template configuration
        $templateConfig = config('database.connections.tenant_template');
        
        if (!$templateConfig) {
            throw new \RuntimeException('tenant_template connection not configured. Please run: php artisan tenant:check-privileges --interactive');
        }

        // Set the tenant-specific database name
        $databaseName = config('tenancy.database.prefix', 'tenant') . $tenantId . config('tenancy.database.suffix', '');
        
        $tenantConfig = array_merge($templateConfig, [
            'database' => $databaseName,
        ]);

        // Add the connection to Laravel's database manager
        config(["database.connections.{$connectionName}" => $tenantConfig]);
        
        // Purge any existing connection with this name to ensure fresh config
        $this->databaseManager->purge($connectionName);
    }

    /**
     * Get connection key for tenant
     */
    private function getConnectionKey(string $tenantId): string
    {
        return "tenant_{$tenantId}";
    }

    /**
     * Get connection name for tenant
     */
    private function getConnectionName(string $tenantId): string
    {
        return "tenant_pool_{$tenantId}";
    }

    /**
     * Check if connection exists and is valid
     */
    private function hasValidConnection(string $connectionKey): bool
    {
        if (!isset($this->connectionPool[$connectionKey])) {
            return false;
        }

        try {
            // Test connection with a simple query
            $connection = $this->connectionPool[$connectionKey];
            $connection->select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            // Connection is invalid, remove it
            $this->removeInvalidConnection($connectionKey);
            return false;
        }
    }

    /**
     * Update connection usage statistics
     */
    private function updateConnectionUsage(string $connectionKey): void
    {
        if (isset($this->connectionUsage[$connectionKey])) {
            $this->connectionUsage[$connectionKey]++;
        } else {
            $this->connectionUsage[$connectionKey] = 1;
        }
        
        $this->lastAccessed[$connectionKey] = microtime(true);
    }

    /**
     * Clean up expired connections
     */
    private function cleanupExpiredConnections(): void
    {
        $currentTime = microtime(true);
        $expiredConnections = [];

        foreach ($this->lastAccessed as $connectionKey => $lastAccessed) {
            if (($currentTime - $lastAccessed) > $this->maxIdleTime) {
                $expiredConnections[] = $connectionKey;
            }
        }

        foreach ($expiredConnections as $connectionKey) {
            $this->closeConnection($connectionKey);
            unset($this->connectionPool[$connectionKey]);
            unset($this->connectionUsage[$connectionKey]);
            unset($this->lastAccessed[$connectionKey]);
            
            Log::debug("Expired connection removed: {$connectionKey}");
        }
    }

    /**
     * Remove least recently used connection
     */
    private function removeLeastRecentlyUsed(): void
    {
        if (empty($this->lastAccessed)) {
            return;
        }

        $lruConnectionKey = array_search(min($this->lastAccessed), $this->lastAccessed);
        
        if ($lruConnectionKey !== false) {
            $this->closeConnection($lruConnectionKey);
            unset($this->connectionPool[$lruConnectionKey]);
            unset($this->connectionUsage[$lruConnectionKey]);
            unset($this->lastAccessed[$lruConnectionKey]);
            
            Log::debug("LRU connection removed: {$lruConnectionKey}");
        }
    }

    /**
     * Remove invalid connection
     */
    private function removeInvalidConnection(string $connectionKey): void
    {
        unset($this->connectionPool[$connectionKey]);
        unset($this->connectionUsage[$connectionKey]);
        unset($this->lastAccessed[$connectionKey]);
        
        Log::warning("Invalid connection removed: {$connectionKey}");
    }

    /**
     * Close a specific connection
     */
    private function closeConnection(string $connectionKey): void
    {
        if (isset($this->connectionPool[$connectionKey])) {
            try {
                // For Laravel, we'll rely on the database manager to handle cleanup
                // The connection will be automatically closed when it goes out of scope
                Log::debug("Marked connection for cleanup: {$connectionKey}");
            } catch (\Exception $e) {
                Log::warning("Error closing connection {$connectionKey}: " . $e->getMessage());
            }
        }
    }

    /**
     * Create a tenant-aware connection manager
     */
    public static function create(array $config = []): self
    {
        return new self(
            app(DatabaseManager::class),
            $config['max_pool_size'] ?? 50,
            $config['max_idle_time'] ?? 300,
            $config['connection_timeout'] ?? 10,
            $config['enable_pooling'] ?? true
        );
    }

    /**
     * Health check for the connection pool
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'statistics' => $this->getPoolStatistics(),
        ];

        // Check for too many connections
        if (count($this->connectionPool) > ($this->maxPoolSize * 0.9)) {
            $health['issues'][] = 'Connection pool is near capacity';
            $health['status'] = 'warning';
        }

        // Check for stale connections
        $currentTime = microtime(true);
        $staleConnections = 0;
        
        foreach ($this->lastAccessed as $lastAccessed) {
            if (($currentTime - $lastAccessed) > ($this->maxIdleTime * 0.8)) {
                $staleConnections++;
            }
        }

        if ($staleConnections > 0) {
            $health['issues'][] = "{$staleConnections} connections are becoming stale";
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }

        // Test a random connection if any exist
        if (!empty($this->connectionPool)) {
            $randomKey = array_rand($this->connectionPool);
            if (!$this->hasValidConnection($randomKey)) {
                $health['issues'][] = 'Invalid connections detected in pool';
                $health['status'] = 'error';
            }
        }

        return $health;
    }
}
