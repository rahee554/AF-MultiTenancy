<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain as StanclDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

class TenantService
{
        /**
     * Create a new tenant with optional custom database name
     * Automatically creates the physical database
     */
    public function createTenant(
        string $name,
        string $domain,
        string $status = 'active',
        ?string $databaseName = null,
        ?string $notes = null,
        bool $hasHomepage = false,
        bool $skipDatabaseCreation = false
    ): Tenant {
        try {
            // Generate unique tenant ID
            $tenantId = (string) Str::uuid();
            
            // Determine database name
            $databaseName = $databaseName ?: ('tenant_' . str_replace('-', '', $tenantId));
            
            // Create the physical database first (outside transaction) unless skipped
            // When skipDatabaseCreation is true, the database should already exist (created via FastPanel)
            if (!$skipDatabaseCreation) {
                $this->createPhysicalDatabase($databaseName);
            } else {
                // Verify that the database exists when skipping creation
                if (!$this->checkTenantDatabase($databaseName)) {
                    throw new \Exception("Database '{$databaseName}' does not exist. Cannot create tenant without database.");
                }
            }
            
            // Now create tenant record in central database transaction
            DB::beginTransaction();
            
            // Insert tenant record directly to bypass stancl/tenancy's database existence checks
            // This is necessary when FastPanel creates the database externally
            DB::table('tenants')->insert([
                'id' => $tenantId,
                'data' => json_encode([
                    'notes' => $notes,
                ]),
                'name' => $name,
                'database' => $databaseName,
                'status' => $status,
                'has_homepage' => $hasHomepage ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create domain
            DB::table('domains')->insert([
                'domain' => $domain,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            
            // Retrieve the created tenant
            $tenant = Tenant::findOrFail($tenantId);
            
            // Auto-create homepage view if enabled
            if ($hasHomepage && config('artflow-tenancy.homepage.auto_create_directory', true)) {
                $this->createHomepageView($domain);
            }
            
            // Log success
            Log::info("Tenant created successfully", [
                'tenant_id' => $tenant->id,
                'name' => $name,
                'domain' => $domain,
                'database' => $databaseName,
                'has_homepage' => $hasHomepage,
                'skip_database_creation' => $skipDatabaseCreation,
            ]);

            return $tenant;
            
        } catch (\Exception $e) {
            // Rollback transaction if it was started
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            // Clean up database if it was created (only if we actually created it)
            if (isset($databaseName) && !$skipDatabaseCreation) {
                try {
                    $this->dropPhysicalDatabase($databaseName);
                } catch (\Exception $cleanupError) {
                    Log::warning("Failed to cleanup database after tenant creation error", [
                        'database' => $databaseName,
                        'error' => $cleanupError->getMessage()
                    ]);
                }
            }
            
            Log::error("Failed to create tenant", [
                'name' => $name,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a tenant and its physical database
     */
    public function deleteTenant(Tenant $tenant): void
    {
        $databaseName = $tenant->getDatabaseName();
        $tenantId = $tenant->id;
        
        try {
            // CRITICAL FIX: Clear all tenant-related cache and sessions BEFORE deletion
            $this->clearTenantCacheAndSessions($tenant);
            
            // Delete the tenant record first (this will also delete domains via foreign key)
            $tenant->delete();
            
            // Drop the physical database
            $this->dropPhysicalDatabase($databaseName);
            
            Log::info("Tenant and database deleted successfully", [
                'tenant_id' => $tenantId,
                'database' => $databaseName,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to delete tenant", [
                'tenant_id' => $tenantId,
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            
            throw new \Exception("Failed to delete tenant: " . $e->getMessage());
        }
    }

    /**
     * Clear all cache and session data related to a tenant
     * Prevents stale authentication data issues (403 Forbidden errors)
     */
    public function clearTenantCacheAndSessions(Tenant $tenant): void
    {
        try {
            $tenantId = $tenant->id;
            $domains = $tenant->domains;
            
            // 1. Clear tenant-specific cache
            if (config('cache.default') === 'redis') {
                try {
                    $redis = \Illuminate\Support\Facades\Redis::connection();
                    $pattern = "tenant_{$tenantId}_*";
                    $keys = $redis->keys($pattern);
                    if (!empty($keys)) {
                        $redis->del($keys);
                        Log::info("Cleared Redis cache keys for tenant", ['count' => count($keys)]);
                    }
                } catch (\Exception $e) {
                    Log::warning("Redis cache clear failed for tenant: " . $e->getMessage());
                }
            } else {
                // Database cache driver
                try {
                    DB::table('cache')->where('key', 'like', "tenant_{$tenantId}_%")->delete();
                    DB::table('cache')->where('key', 'like', "laravel_cache:tenant_{$tenantId}_%")->delete();
                } catch (\Exception $e) {
                    Log::warning("Database cache clear failed: " . $e->getMessage());
                }
            }
            
            // 2. Clear sessions related to this tenant
            if (config('session.driver') === 'database') {
                try {
                    $deleted = DB::table(config('session.table', 'sessions'))
                        ->where('payload', 'like', "%{$tenantId}%")
                        ->delete();
                    
                    // Also clear by domain
                    foreach ($domains as $domain) {
                        DB::table(config('session.table', 'sessions'))
                            ->where('payload', 'like', "%{$domain->domain}%")
                            ->delete();
                    }
                    
                    Log::info("Cleared tenant sessions", ['count' => $deleted]);
                } catch (\Exception $e) {
                    Log::warning("Session clear failed: " . $e->getMessage());
                }
            }
            
            // 3. Clear cache facade
            try {
                \Illuminate\Support\Facades\Cache::flush();
            } catch (\Exception $e) {
                Log::warning("Cache flush failed: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            Log::error("Overall cache/session cleanup failed", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Activate a tenant.
     */
    public function activateTenant(Tenant $tenant): void
    {
        $tenant->activate();
    }

    /**
     * Deactivate a tenant.
     */
    public function deactivateTenant(Tenant $tenant): void
    {
        $tenant->deactivate();
    }

    /**
     * Migrate tenant database.
     */
    public function migrateTenant(Tenant $tenant, bool $fresh = false): void
    {
        $tenant->run(function () use ($fresh) {
            $command = $fresh ? 'migrate:fresh' : 'migrate';
            
            // Run tenant-specific migrations using stancl's connection
            Artisan::call($command, [
                '--force' => true,
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
            ]);
        });
    }

    /**
     * Seed tenant database without user conflicts.
     */
    public function seedTenant(Tenant $tenant): void
    {
        $tenant->run(function () {
            Artisan::call('db:seed', [
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);
        });
    }

    /**
     * Migrate all active tenants.
     */
    public function migrateAllTenants(bool $fresh = false): array
    {
        $tenants = Tenant::where('status', 'active')->get();
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($tenants as $tenant) {
            try {
                $this->migrateTenant($tenant, $fresh);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Tenant {$tenant->name}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Seed all active tenants.
     */
    public function seedAllTenants(): array
    {
        $tenants = Tenant::where('status', 'active')->get();
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($tenants as $tenant) {
            try {
                $this->seedTenant($tenant);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Tenant {$tenant->name}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get tenant status information.
     */
    public function getTenantStatus(Tenant $tenant): array
    {
        $status = [
            'tenant' => $tenant,
            'database_exists' => $this->checkTenantDatabase($tenant->getDatabaseName()),
            'migration_count' => 0,
            'table_count' => 0,
            'database_size' => 0,
        ];

        if ($status['database_exists']) {
            try {
                Tenancy::initialize($tenant);
                
                // Check migrations
                $status['migration_count'] = DB::table('migrations')->count();
                
                // Check tables
                $status['table_count'] = count(DB::select('SHOW TABLES'));
                
                // Get database size
                $result = DB::select("SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb' 
                    FROM information_schema.tables 
                    WHERE table_schema = ?", [$tenant->getDatabaseName()]);
                
                $status['database_size'] = $result[0]->size_mb ?? 0;
                
                Tenancy::end();
            } catch (\Exception $e) {
                Tenancy::end();
                $status['error'] = $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * Get system statistics.
     */
    public function getSystemStats(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $inactiveTenants = Tenant::where('status', 'inactive')->count();
        
        // Get database health statistics
        $healthyDatabases = 0;
        $idleDatabases = 0;
        $totalConnections = 0;
        
        try {
            $tenants = Tenant::where('status', 'active')->get();
            foreach ($tenants as $tenant) {
                if ($this->checkTenantDatabase($tenant->getDatabaseName())) {
                    $healthyDatabases++;
                    
                    // Check if database has recent activity (last 24 hours)
                    try {
                        // Use stancl/tenancy's proper tenant context
                        $tenant->run(function () use (&$hasRecentActivity) {
                            // Simple check for recent activity - look for updated records
                            $hasRecentActivity = false;
                            $tables = ['users', 'businesses', 'customers', 'orders', 'invoices'];
                            
                            foreach ($tables as $table) {
                                try {
                                    $recentRecords = DB::table($table)
                                        ->where('updated_at', '>=', now()->subDay())
                                        ->count();
                                    if ($recentRecords > 0) {
                                        $hasRecentActivity = true;
                                        break;
                                    }
                                } catch (\Exception $e) {
                                    // Table might not exist, continue
                                    continue;
                                }
                            }
                        });
                        
                        if (!$hasRecentActivity) {
                            $idleDatabases++;
                        }
                        
                        $totalConnections++;
                    } catch (\Exception $e) {
                        // Database connection issue
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's an error, set default values
        }
        
        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'inactive_tenants' => $inactiveTenants,
            'healthy_databases' => $healthyDatabases,
            'idle_databases' => $idleDatabases,
            'active_databases' => $healthyDatabases - $idleDatabases,
            'total_connections' => $totalConnections,
            'created_today' => Tenant::whereDate('created_at', today())->count(),
            'created_this_week' => Tenant::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'created_this_month' => Tenant::whereMonth('created_at', now()->month)->count(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    /**
     * Check tenant database health.
     */
    public function checkSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        // Check main database connection
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'ok', 
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'error', 
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }

        // Check tenant databases
        try {
            $tenants = Tenant::where('status', 'active')->get();
            $accessibleCount = 0;
            $totalCount = $tenants->count();
            $issues = [];

            foreach ($tenants as $tenant) {
                try {
                    $databaseName = $tenant->getDatabaseName();
                    
                    // Check if database exists
                    if (!$this->checkTenantDatabase($databaseName)) {
                        $issues[] = [
                            'tenant' => $tenant->name,
                            'database' => $databaseName,
                            'error' => 'Database does not exist'
                        ];
                        continue;
                    }

                    // Test connection by running in tenant context
                    $tableCount = 0;
                    $tenant->run(function () use (&$tableCount) {
                        $tableCount = count(DB::select('SHOW TABLES'));
                    });

                    if ($tableCount === 0) {
                        $issues[] = [
                            'tenant' => $tenant->name,
                            'database' => $databaseName,
                            'error' => 'Database exists but no tables (migrations not run)'
                        ];
                    } else {
                        $accessibleCount++;
                    }
                } catch (\Exception $e) {
                    $issues[] = [
                        'tenant' => $tenant->name,
                        'database' => $tenant->getDatabaseName(),
                        'error' => 'Connection error: ' . $e->getMessage()
                    ];
                }
            }

            if ($accessibleCount === $totalCount && $totalCount > 0) {
                $health['checks']['tenant_databases'] = [
                    'status' => 'ok',
                    'message' => "All {$totalCount} tenant databases accessible"
                ];
            } elseif ($totalCount === 0) {
                $health['checks']['tenant_databases'] = [
                    'status' => 'ok',
                    'message' => "No active tenants to check"
                ];
            } else {
                $status = $accessibleCount > 0 ? 'warning' : 'error';
                $health['checks']['tenant_databases'] = [
                    'status' => $status,
                    'message' => "{$accessibleCount}/{$totalCount} tenant databases accessible",
                    'issues' => $issues
                ];
                
                if ($accessibleCount === 0) {
                    $health['status'] = 'unhealthy';
                } elseif ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            }
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['tenant_databases'] = [
                'status' => 'error',
                'message' => 'Failed to check tenant databases: ' . $e->getMessage()
            ];
        }

        // Check cache system
        try {
            $cacheKey = 'health_check_' . time();
            $testValue = 'health_test_' . \Illuminate\Support\Str::random(10);
            
            cache()->put($cacheKey, $testValue, 60);
            $retrieved = cache()->get($cacheKey);
            
            if ($retrieved === $testValue) {
                $health['checks']['cache'] = [
                    'status' => 'ok',
                    'message' => 'Cache system operational (' . config('cache.default') . ')'
                ];
            } else {
                $health['checks']['cache'] = [
                    'status' => 'warning',
                    'message' => 'Cache not working properly (stored: ' . $testValue . ', retrieved: ' . $retrieved . ')'
                ];
                if ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            }
            
            cache()->forget($cacheKey);
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'error',
                'message' => 'Cache system error: ' . $e->getMessage()
            ];
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }

        // Check storage system
        try {
            $testFile = storage_path('app/health_check_test.txt');
            $testContent = 'health_test_' . time();
            
            \Illuminate\Support\Facades\File::put($testFile, $testContent);
            $readContent = \Illuminate\Support\Facades\File::get($testFile);
            \Illuminate\Support\Facades\File::delete($testFile);
            
            if ($readContent === $testContent) {
                $health['checks']['storage'] = [
                    'status' => 'ok',
                    'message' => 'Storage system operational'
                ];
            } else {
                $health['checks']['storage'] = [
                    'status' => 'error',
                    'message' => 'Storage read/write verification failed'
                ];
                $health['status'] = 'unhealthy';
            }
        } catch (\Exception $e) {
            $health['checks']['storage'] = [
                'status' => 'error',
                'message' => 'Storage system error: ' . $e->getMessage()
            ];
            $health['status'] = 'unhealthy';
        }

        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

        if ($memoryPercent < 80) {
            $health['checks']['memory'] = [
                'status' => 'ok',
                'message' => sprintf('Memory usage: %.1f%% (%s / %s)', 
                    $memoryPercent, 
                    $this->formatBytes($memoryUsage), 
                    $this->formatBytes($memoryLimit)
                )
            ];
        } elseif ($memoryPercent < 95) {
            $health['checks']['memory'] = [
                'status' => 'warning',
                'message' => sprintf('High memory usage: %.1f%%', $memoryPercent)
            ];
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        } else {
            $health['checks']['memory'] = [
                'status' => 'error',
                'message' => sprintf('Critical memory usage: %.1f%%', $memoryPercent)
            ];
            $health['status'] = 'unhealthy';
        }

        // Add summary information
        $health['summary'] = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'memory_usage' => $this->formatBytes($memoryUsage),
            'cache_driver' => config('cache.default'),
            'timestamp' => now()->toISOString()
        ];

        return $health;
    }

    /**
     * Create tenant database.
     */
    private function createTenantDatabase(string $databaseName): void
    {
        // Use stancl/tenancy's database manager for proper database creation
        $databaseManager = app(\Stancl\Tenancy\Contracts\TenantDatabaseManager::class);
        
        // Create a mock tenant object for database creation
        $mockTenant = new Tenant(['id' => 'temp']);
        $mockTenant->database_name = $databaseName;
        
        $databaseManager->createDatabase($mockTenant);
    }

    /**
     * Drop tenant database.
     */
    private function dropTenantDatabase(string $databaseName): void
    {
        // Use stancl/tenancy's database manager for proper database deletion
        $databaseManager = app(\Stancl\Tenancy\Contracts\TenantDatabaseManager::class);
        
        // Create a mock tenant object for database deletion
        $mockTenant = new Tenant(['id' => 'temp']);
        $mockTenant->database_name = $databaseName;
        
        $databaseManager->deleteDatabase($mockTenant);
    }

    /**
     * Check if tenant database exists.
     */
    private function checkTenantDatabase(string $databaseName): bool
    {
        try {
            // Use direct SQL query for reliable database existence check
            $result = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            return !empty($result);
            
        } catch (\Exception $e) {
            Log::warning("Database existence check failed for {$databaseName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a physical database using stancl/tenancy's database manager
     */
    private function createPhysicalDatabase(string $databaseName): void
    {
        try {
            // Use stancl/tenancy's database manager for proper database creation
            $databaseManager = app(\Stancl\Tenancy\Contracts\TenantDatabaseManager::class);
            
            // Create a temporary tenant object for database creation
            $tempTenant = new Tenant(['id' => (string) \Illuminate\Support\Str::uuid()]);
            $tempTenant->setInternal('db_name', $databaseName);
            
            // Create the database using stancl/tenancy's manager
            $databaseManager->createDatabase($tempTenant);
            
            Log::info("Physical database created using stancl/tenancy: {$databaseName}");
            
        } catch (\Exception $e) {
            // Fallback to direct SQL if stancl/tenancy fails
            Log::warning("Stancl/tenancy database creation failed, using fallback: " . $e->getMessage());
            
            $connection = config('database.default');
            $charset = config("database.connections.{$connection}.charset", 'utf8mb4');
            $collation = config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci');
            
            // Validate database name to prevent SQL injection
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
                throw new \Exception("Invalid database name: {$databaseName}");
            }
            
            // Check if database already exists
            $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            
            if (empty($exists)) {
                DB::statement("CREATE DATABASE `{$databaseName}` CHARACTER SET {$charset} COLLATE {$collation}");
                Log::info("Physical database created via fallback: {$databaseName}");
            } else {
                Log::info("Database already exists: {$databaseName}");
            }
        }
    }
    
    /**
     * Drop a physical database
     */
    private function dropPhysicalDatabase(string $databaseName): void
    {
        // Safety check - don't drop main database
        $mainDatabase = config('database.connections.' . config('database.default') . '.database');
        if ($databaseName === $mainDatabase) {
            throw new \Exception("Cannot drop main database: {$databaseName}");
        }
        
        // Check if database exists before dropping
        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
        
        if (!empty($exists)) {
            DB::statement("DROP DATABASE `{$databaseName}`");
            Log::info("Physical database dropped: {$databaseName}");
        } else {
            Log::info("Database does not exist, no need to drop: {$databaseName}");
        }
    }

    /**
     * Create homepage view for a tenant domain
     */
    public function createHomepageView(string $domain): void
    {
        $viewPath = config('artflow-tenancy.homepage.view_path', 'tenants');
        $viewDirectory = resource_path("views/{$viewPath}/{$domain}");
        $homepageFile = "{$viewDirectory}/home.blade.php";

        // Check if directory already exists
        if (!File::exists($viewDirectory)) {
            File::makeDirectory($viewDirectory, 0755, true);
            Log::info("Created homepage view directory: {$viewDirectory}");
        }

        // Check if homepage file already exists
        if (!File::exists($homepageFile)) {
            $homepageContent = $this->getHomepageTemplate($domain);
            File::put($homepageFile, $homepageContent);
            Log::info("Created homepage view file: {$homepageFile}");
        }
    }

    /**
     * Remove homepage view for a tenant domain
     */
    public function removeHomepageView(string $domain): void
    {
        $viewPath = config('artflow-tenancy.homepage.view_path', 'tenants');
        $viewDirectory = resource_path("views/{$viewPath}/{$domain}");

        if (File::exists($viewDirectory)) {
            File::deleteDirectory($viewDirectory);
            Log::info("Removed homepage view directory: {$viewDirectory}");
        }
    }

    /**
     * Get homepage template content for a domain
     */
    protected function getHomepageTemplate(string $domain): string
    {
        return <<<'BLADE'
@extends('layouts.app')

@section('title', 'Welcome to {{ $tenant->data["name"] ?? "Tenant" }}')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow-lg rounded-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-800 mb-4">
                    Welcome to {{ $tenant->data['name'] ?? 'Your Tenant' }}
                </h1>
                <p class="text-xl text-gray-600">
                    Domain: <strong>{{ $domain }}</strong>
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <div class="bg-blue-50 p-6 rounded-lg">
                    <h2 class="text-2xl font-semibold text-blue-800 mb-4">🏠 Tenant Information</h2>
                    <ul class="space-y-2 text-gray-700">
                        <li><strong>Tenant ID:</strong> {{ $tenant->id }}</li>
                        <li><strong>Name:</strong> {{ $tenant->data['name'] ?? 'Not set' }}</li>
                        <li><strong>Status:</strong> 
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                {{ $tenant->data['status'] ?? 'Active' }}
                            </span>
                        </li>
                        <li><strong>Homepage:</strong> 
                            @if($tenant->hasHomepage())
                                <span class="text-green-600">✅ Enabled</span>
                            @else
                                <span class="text-red-600">❌ Disabled</span>
                            @endif
                        </li>
                    </ul>
                </div>

                <div class="bg-green-50 p-6 rounded-lg">
                    <h2 class="text-2xl font-semibold text-green-800 mb-4">🚀 Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="/dashboard" class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded-lg hover:bg-blue-700 transition">
                            Go to Dashboard
                        </a>
                        <a href="/login" class="block w-full bg-gray-600 text-white text-center py-2 px-4 rounded-lg hover:bg-gray-700 transition">
                            Sign In
                        </a>
                        <a href="/register" class="block w-full bg-green-600 text-white text-center py-2 px-4 rounded-lg hover:bg-green-700 transition">
                            Create Account
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center text-gray-500">
                <p>This is a custom homepage for your tenant. You can customize this view at:</p>
                <code class="bg-gray-100 px-2 py-1 rounded">resources/views/tenants/{{ $domain }}/home.blade.php</code>
            </div>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX; // No limit
        }
        
        $value = (int) $memoryLimit;
        $unit = strtolower(substr($memoryLimit, -1));
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
