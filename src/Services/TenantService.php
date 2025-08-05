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
        ?string $customDatabase = null,
        ?string $notes = null,
        bool $hasHomepage = false
    ): Tenant {
        try {
            // Generate unique tenant ID
            $tenantId = (string) Str::uuid();
            
            // Determine database name
            $databaseName = $customDatabase ?: ('tenant_' . str_replace('-', '', $tenantId));
            
            // Create the physical database first (outside transaction)
            $this->createPhysicalDatabase($databaseName);
            
            // Now create tenant record in central database transaction
            DB::beginTransaction();
            
            // Create tenant record
            $tenant = Tenant::create([
                'id' => $tenantId,
                'name' => $name,
                'database' => $customDatabase, // Store custom name if provided
                'status' => $status,
                'has_homepage' => $hasHomepage,
                'data' => [
                    'notes' => $notes,
                ],
            ]);

            // Create domain
            $tenant->domains()->create([
                'domain' => $domain,
            ]);

            DB::commit();
            
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
            ]);

            return $tenant;
            
        } catch (\Exception $e) {
            // Rollback transaction if it was started
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            // Clean up database if it was created
            if (isset($databaseName)) {
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
        
        try {
            // Delete the tenant record first (this will also delete domains via foreign key)
            $tenant->delete();
            
            // Drop the physical database
            $this->dropPhysicalDatabase($databaseName);
            
            Log::info("Tenant and database deleted successfully", [
                'tenant_id' => $tenant->id,
                'database' => $databaseName,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to delete tenant", [
                'tenant_id' => $tenant->id,
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            
            throw new \Exception("Failed to delete tenant: " . $e->getMessage());
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
                    // Find the tenant and use proper stancl/tenancy context
                    $tenant = Tenant::where('database', $databaseName)->first();
                    if (!$tenant) {
                        $tenant = Tenant::where('id', str_replace('tenant_', '', $databaseName))->first();
                    }
                    
                    if ($tenant) {
                        $tenant->run(function () use (&$tableCount) {
                            $tableCount = count(DB::select('SHOW TABLES'));
                        });
                    } else {
                        $tableCount = 0;
                    }
                    
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
            // First try using stancl/tenancy's database manager
            $databaseManager = app(\Stancl\Tenancy\Contracts\TenantDatabaseManager::class);
            
            if (method_exists($databaseManager, 'databaseExists')) {
                return $databaseManager->databaseExists($databaseName);
            }
            
            // Fallback to direct SQL query
            $result = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            return !empty($result);
            
        } catch (\Exception $e) {
            Log::warning("Database existence check failed: " . $e->getMessage());
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
            $result = $databaseManager->createDatabase($tempTenant);
            
            if ($result) {
                Log::info("Physical database created using stancl/tenancy: {$databaseName}");
            } else {
                Log::warning("Database creation returned false: {$databaseName}");
            }
            
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
}
