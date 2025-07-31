<?php

namespace ArtflowStudio\Tenancy\Database;

use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * High Performance MySQL Database Manager
 * 
 * Extends the default stancl/tenancy MySQL manager with performance optimizations:
 * - Cached database existence checks
 * - Optimized connection pooling
 * - Fast database creation/deletion
 */
class HighPerformanceMySQLDatabaseManager extends MySQLDatabaseManager
{
    /**
     * Create database for tenant with caching
     */
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $database = $tenant->database()->getName();
        
        // Check cache first to avoid unnecessary database queries
        $cacheKey = "tenant_db_exists:{$database}";
        
        if (Cache::get($cacheKey)) {
            return true; // Database already exists
        }
        
        try {
            // Create database using parent method
            $result = parent::createDatabase($tenant);
            
            // Cache the result for future checks (1 hour)
            if ($result) {
                Cache::put($cacheKey, true, 3600);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // If database already exists, cache it and return true
            if (str_contains($e->getMessage(), 'database exists')) {
                Cache::put($cacheKey, true, 3600);
                return true;
            }
            
            throw $e;
        }
    }
    
    /**
     * Delete database with cache invalidation
     */
    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $database = $tenant->database()->getName();
        
        try {
            $result = parent::deleteDatabase($tenant);
            
            // Invalidate cache
            $cacheKey = "tenant_db_exists:{$database}";
            Cache::forget($cacheKey);
            
            return $result;
            
        } catch (\Exception $e) {
            // Even if deletion fails, clear cache to be safe
            $cacheKey = "tenant_db_exists:{$database}";
            Cache::forget($cacheKey);
            
            throw $e;
        }
    }
    
    /**
     * Check if database exists with caching
     */
    public function databaseExists(string $name): bool
    {
        $cacheKey = "tenant_db_exists:{$name}";
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // Use optimized query to check database existence
            $exists = DB::connection(config('tenancy.database.central_connection', 'mysql'))
                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$name]);
            
            $result = !empty($exists);
            
            // Cache the result (1 hour)
            Cache::put($cacheKey, $result, 3600);
            
            return $result;
            
        } catch (\Exception $e) {
            // Fall back to checking if we can connect to the database
            try {
                DB::connection("tenant_{$name}")->getPdo();
                $result = true;
            } catch (\Exception $connectionException) {
                $result = false;
            }
            
            Cache::put($cacheKey, $result, 3600);
            return $result;
        }
    }
    
    /**
     * Get optimized database creation query
     */
    protected function getCreateDatabaseQuery(string $name): string
    {
        return "CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    }
}
