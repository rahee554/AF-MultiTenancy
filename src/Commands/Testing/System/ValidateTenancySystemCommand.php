<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\System;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class ValidateTenancySystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:validate 
                            {--fix : Fix issues automatically where possible}
                            {--tenant= : Validate specific tenant by ID}';

    /**
     * The console command description.
     */
    protected $description = 'Comprehensive validation of the tenancy system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fix = $this->option('fix');
        $tenantId = $this->option('tenant');

        $this->info('🔍 Validating Tenancy System...');
        $this->newLine();

        $issues = [];
        $fixed = [];

        // Step 1: Basic system validation
        $this->info('Step 1: Basic System Validation');
        $systemIssues = $this->validateSystem();
        $issues = array_merge($issues, $systemIssues);

        // Step 2: Tenant validation
        $this->info('Step 2: Tenant Validation');
        $tenantIssues = $this->validateTenants($tenantId, $fix);
        $issues = array_merge($issues, $tenantIssues['issues']);
        $fixed = array_merge($fixed, $tenantIssues['fixed']);

        // Step 3: Connection validation
        $this->info('Step 3: Connection Validation');
        $connectionIssues = $this->validateConnections($tenantId);
        $issues = array_merge($issues, $connectionIssues);

        // Step 4: Migration validation
        $this->info('Step 4: Migration Validation');
        $migrationIssues = $this->validateMigrations($tenantId);
        $issues = array_merge($issues, $migrationIssues);

        // Display results
        $this->displayResults($issues, $fixed);

        return empty($issues) ? 0 : 1;
    }

    /**
     * Validate basic system requirements
     */
    private function validateSystem(): array
    {
        $issues = [];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->info('  ✅ Central database connection successful');
        } catch (\Exception $e) {
            $issues[] = ['type' => 'system', 'message' => 'Central database connection failed: ' . $e->getMessage()];
        }

        // Check stancl/tenancy is loaded
        try {
            app(\Stancl\Tenancy\Contracts\TenantDatabaseManager::class);
            $this->info('  ✅ Stancl/tenancy database manager loaded');
        } catch (\Exception $e) {
            $issues[] = ['type' => 'system', 'message' => 'Stancl/tenancy not properly loaded: ' . $e->getMessage()];
        }

        // Check configuration
        if (!config('tenancy')) {
            $issues[] = ['type' => 'system', 'message' => 'Tenancy configuration not found'];
        } else {
            $this->info('  ✅ Tenancy configuration loaded');
        }

        return $issues;
    }

    /**
     * Validate tenant records and databases
     */
    private function validateTenants(?string $tenantId, bool $fix): array
    {
        $issues = [];
        $fixed = [];

        $query = Tenant::with('domains');
        if ($tenantId) {
            $query->where('id', $tenantId);
        }
        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $issues[] = ['type' => 'tenant', 'message' => 'No tenants found'];
            return ['issues' => $issues, 'fixed' => $fixed];
        }

        $this->info("  Validating {$tenants->count()} tenants...");

        foreach ($tenants as $tenant) {
            $tenantIssues = [];
            
            // Check database exists
            $databaseName = $tenant->getDatabaseName();
            if (!$this->databaseExists($databaseName)) {
                $tenantIssues[] = "Database '{$databaseName}' does not exist";
                
                if ($fix) {
                    try {
                        $this->createDatabase($databaseName);
                        $fixed[] = "Created database: {$databaseName}";
                        $this->info("    🔧 Created database: {$databaseName}");
                    } catch (\Exception $e) {
                        $tenantIssues[] = "Failed to create database: " . $e->getMessage();
                    }
                }
            }

            // Check domain exists
            if ($tenant->domains->isEmpty()) {
                $tenantIssues[] = "No domains configured";
            }

            // Check tenant status
            if (!in_array($tenant->status, ['active', 'inactive', 'suspended'])) {
                $tenantIssues[] = "Invalid status: {$tenant->status}";
            }

            if (!empty($tenantIssues)) {
                $issues[] = [
                    'type' => 'tenant',
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name ?? 'Unnamed',
                    'domain' => $tenant->domains->first()?->domain ?? 'No domain',
                    'issues' => $tenantIssues
                ];
            } else {
                $this->info("    ✅ Tenant: {$tenant->name} ({$tenant->domains->first()?->domain})");
            }
        }

        return ['issues' => $issues, 'fixed' => $fixed];
    }

    /**
     * Validate tenant connections
     */
    private function validateConnections(?string $tenantId): array
    {
        $issues = [];

        $query = Tenant::query();
        if ($tenantId) {
            $query->where('id', $tenantId);
        }
        $tenants = $query->limit(5)->get(); // Limit for performance

        foreach ($tenants as $tenant) {
            try {
                Tenancy::initialize($tenant);
                
                // Test connection
                $connection = DB::connection('tenant');
                $connection->getPdo();
                
                $this->info("    ✅ Connection test passed: {$tenant->name}");
                
                Tenancy::end();
            } catch (\Exception $e) {
                $issues[] = [
                    'type' => 'connection',
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name ?? 'Unnamed',
                    'message' => 'Connection failed: ' . $e->getMessage()
                ];
                Tenancy::end();
            }
        }

        return $issues;
    }

    /**
     * Validate tenant migrations
     */
    private function validateMigrations(?string $tenantId): array
    {
        $issues = [];

        $query = Tenant::query();
        if ($tenantId) {
            $query->where('id', $tenantId);
        }
        $tenants = $query->limit(3)->get(); // Limit for performance

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, &$issues) {
                    // Check if migrations table exists
                    $migrationCount = DB::table('migrations')->count();
                    
                    if ($migrationCount === 0) {
                        $issues[] = [
                            'type' => 'migration',
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name ?? 'Unnamed',
                            'message' => 'No migrations found - database may not be properly migrated'
                        ];
                    } else {
                        $this->info("    ✅ Migrations OK: {$tenant->name} ({$migrationCount} migrations)");
                    }
                });
            } catch (\Exception $e) {
                $issues[] = [
                    'type' => 'migration',
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name ?? 'Unnamed',
                    'message' => 'Migration check failed: ' . $e->getMessage()
                ];
            }
        }

        return $issues;
    }

    /**
     * Display validation results
     */
    private function displayResults(array $issues, array $fixed): void
    {
        $this->newLine();

        if (!empty($fixed)) {
            $this->info('🔧 Fixed Issues:');
            foreach ($fixed as $fix) {
                $this->info("  • {$fix}");
            }
            $this->newLine();
        }

        if (empty($issues)) {
            $this->info('✅ Tenancy System Validation Complete - No Issues Found!');
            return;
        }

        $this->error('❌ Found ' . count($issues) . ' issues:');
        $this->newLine();

        $groupedIssues = collect($issues)->groupBy('type');

        foreach ($groupedIssues as $type => $typeIssues) {
            $this->warn(strtoupper($type) . ' ISSUES:');
            
            foreach ($typeIssues as $issue) {
                if (isset($issue['tenant_id'])) {
                    $this->error("  Tenant: {$issue['tenant_name']} (ID: " . substr($issue['tenant_id'], 0, 8) . '...)');
                    if (is_array($issue['issues'] ?? null)) {
                        foreach ($issue['issues'] as $subIssue) {
                            $this->error("    • {$subIssue}");
                        }
                    } else {
                        $this->error("    • {$issue['message']}");
                    }
                } else {
                    $this->error("  • {$issue['message']}");
                }
            }
            $this->newLine();
        }

        $this->info('To automatically fix database issues, run:');
        $this->info('php artisan tenancy:validate --fix');
    }

    /**
     * Check if database exists
     */
    private function databaseExists(string $databaseName): bool
    {
        try {
            $result = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create database
     */
    private function createDatabase(string $databaseName): void
    {
        $connection = config('database.default');
        $charset = config("database.connections.{$connection}.charset", 'utf8mb4');
        $collation = config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci');
        
        // Validate database name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
            throw new \Exception("Invalid database name: {$databaseName}");
        }
        
        DB::statement("CREATE DATABASE `{$databaseName}` CHARACTER SET {$charset} COLLATE {$collation}");
    }
}
