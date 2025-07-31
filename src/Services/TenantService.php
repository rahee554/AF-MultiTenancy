<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

class TenantService
{
    /**
     * Create a new tenant with database.
     */
    public function createTenant(string $name, string $domain, string $status = 'active', ?string $customDatabaseName = null, ?string $notes = null, ?string $customPrefix = null): Tenant
    {
        // Validate domain uniqueness in domains table
        if (DB::table('domains')->where('domain', $domain)->exists()) {
            throw new \Exception("Domain '{$domain}' already exists");
        }

        $uuid = (string) Str::uuid();
        
        // Use custom database name or generate readable one
        if ($customDatabaseName) {
            $databaseName = $customDatabaseName;
        } else {
            // Allow custom prefix or use environment default
            $prefix = $customPrefix ?? env('TENANT_DB_PREFIX', 'tenant_');
            $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
            $databaseName = $prefix . $cleanName . '_' . substr($uuid, 0, 8);
        }
        
        // Create tenant record (without domain)
        $id = DB::table('tenants')->insertGetId([
            'uuid' => $uuid,
            'name' => $name,
            'database_name' => $databaseName,
            'status' => $status,
            'notes' => $notes ?: 'Created via system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::find($id);
        
        // Create domain record for stancl/tenancy
        DB::table('domains')->insert([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create database
        $this->createTenantDatabase($tenant->getDatabaseName());
        
        return $tenant;
    }

    /**
     * Delete a tenant and its database.
     */
    public function deleteTenant(Tenant $tenant): void
    {
        $this->dropTenantDatabase($tenant->getDatabaseName());
        $tenant->delete();
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
        // Use stancl/tenancy's proper database switching
        tenancy()->initialize($tenant);

        try {
            $command = $fresh ? 'migrate:fresh' : 'migrate';
            
            // Run tenant-specific migrations using stancl's connection
            Artisan::call($command, [
                '--database' => 'tenant', // stancl uses 'tenant' connection automatically
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Seed tenant database.
     */
    public function seedTenant(Tenant $tenant): void
    {
        // Use stancl/tenancy's proper database switching
        tenancy()->initialize($tenant);

        try {
            Artisan::call('db:seed', [
                '--force' => true,
                '--database' => 'tenant', // stancl uses 'tenant' connection automatically
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Migrate all active tenants.
     */
    public function migrateAllTenants(bool $fresh = false): array
    {
        $tenants = Tenant::active()->get();
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
        $tenants = Tenant::active()->get();
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
        $activeTenants = Tenant::active()->count();
        $inactiveTenants = Tenant::inactive()->count();
        
        // Get database health statistics
        $healthyDatabases = 0;
        $idleDatabases = 0;
        $totalConnections = 0;
        
        try {
            $tenants = Tenant::active()->get();
            foreach ($tenants as $tenant) {
                if ($this->checkTenantDatabase($tenant->getDatabaseName())) {
                    $healthyDatabases++;
                    
                    // Check if database has recent activity (last 24 hours)
                    try {
                        config(['database.connections.tenant.database' => $tenant->getDatabaseName()]);
                        DB::purge('tenant');
                        DB::reconnect('tenant');
                        
                        // Simple check for recent activity - look for updated records
                        $hasRecentActivity = false;
                        $tables = ['users', 'businesses', 'customers', 'orders', 'invoices'];
                        
                        foreach ($tables as $table) {
                            try {
                                $recentRecords = DB::connection('tenant')
                                    ->table($table)
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
            'checks' => [
                'database' => ['status' => 'healthy', 'message' => 'Database connection successful'],
                'tenant_databases' => ['status' => 'healthy', 'message' => 'All tenant databases accessible'],
            ]
        ];

        try {
            // Test main database connection
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Check tenant databases with detailed issues
        $tenants = Tenant::active()->get();
        $failedDatabases = 0;
        $issues = [];

        foreach ($tenants as $tenant) {
            $databaseName = $tenant->getDatabaseName();
            
            if (!$this->checkTenantDatabase($databaseName)) {
                $failedDatabases++;
                $issues[] = [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant' => $tenant->name,
                    'database' => $databaseName,
                    'error' => 'Database does not exist or is not accessible',
                    'fixable' => true
                ];
            } else {
                // Check if database has tables (migrations ran)
                try {
                    // Temporarily switch to tenant database to check tables
                    config(['database.connections.tenant.database' => $databaseName]);
                    DB::purge('tenant');
                    DB::reconnect('tenant');
                    
                    $tableCount = count(DB::connection('tenant')->select('SHOW TABLES'));
                    
                    if ($tableCount === 0) {
                        $failedDatabases++;
                        $issues[] = [
                            'tenant_uuid' => $tenant->uuid,
                            'tenant' => $tenant->name,
                            'database' => $databaseName,
                            'error' => 'Database exists but no tables found (migrations not run)',
                            'fixable' => true
                        ];
                    }
                } catch (\Exception $e) {
                    $failedDatabases++;
                    $issues[] = [
                        'tenant_uuid' => $tenant->uuid,
                        'tenant' => $tenant->name,
                        'database' => $databaseName,
                        'error' => 'Database connection error: ' . $e->getMessage(),
                        'fixable' => false
                    ];
                }
            }
        }

        if ($failedDatabases > 0) {
            $health['status'] = 'warning';
            $health['checks']['tenant_databases'] = [
                'status' => 'warning',
                'message' => "{$failedDatabases} tenant databases have issues",
                'issues' => $issues,
                'healthy_count' => count($tenants) - $failedDatabases,
                'total_count' => count($tenants)
            ];
        } else {
            $health['checks']['tenant_databases']['message'] = "All {$tenants->count()} tenant databases are healthy";
        }

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
            $databaseManager = app(\Stancl\Tenancy\Contracts\TenantDatabaseManager::class);
            
            // Create a mock tenant object for database checking
            $mockTenant = new Tenant(['id' => 'temp']);
            $mockTenant->database_name = $databaseName;
            
            return $databaseManager->databaseExists($mockTenant);
        } catch (\Exception $e) {
            return false;
        }
    }
}
