<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Support\Facades\DB;

class FixTenantDatabasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:fix-databases 
                            {--dry-run : Show what would be fixed without making changes}
                            {--recreate : Recreate missing databases}
                            {--migrate : Run migrations on fixed databases}';

    /**
     * The console command description.
     */
    protected $description = 'Fix tenant database issues - recreate missing databases and ensure proper connections';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $recreate = $this->option('recreate');
        $migrate = $this->option('migrate');

        $this->info('🔧 Analyzing tenant database issues...');
        
        $tenants = Tenant::all();
        $issues = [];
        $fixed = [];

        foreach ($tenants as $tenant) {
            $databaseName = $tenant->getDatabaseName();
            $exists = $this->databaseExists($databaseName);
            
            if (!$exists) {
                $issues[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name ?? 'Unnamed',
                    'domain' => $tenant->domains()->first()?->domain ?? 'No domain',
                    'database' => $databaseName,
                    'issue' => 'Database missing'
                ];
                
                if ($recreate && !$dryRun) {
                    try {
                        $this->info("Recreating database: {$databaseName}");
                        $this->createDatabase($databaseName);
                        
                        if ($migrate) {
                            $this->info("Running migrations for: {$databaseName}");
                            app(TenantService::class)->migrateTenant($tenant);
                        }
                        
                        $fixed[] = $databaseName;
                    } catch (\Exception $e) {
                        $this->error("Failed to recreate {$databaseName}: " . $e->getMessage());
                    }
                }
            }
        }

        // Display results
        if (empty($issues)) {
            $this->info('✅ All tenant databases are healthy!');
            return 0;
        }

        $this->warn('Found ' . count($issues) . ' database issues:');
        $this->table(
            ['Tenant ID', 'Name', 'Domain', 'Database', 'Issue'],
            array_map(fn($issue) => [
                substr($issue['tenant_id'], 0, 8) . '...',
                $issue['tenant_name'],
                $issue['domain'],
                $issue['database'],
                $issue['issue']
            ], $issues)
        );

        if ($dryRun) {
            $this->info('');
            $this->info('To fix these issues, run:');
            $this->info('php artisan tenancy:fix-databases --recreate --migrate');
            return 1;
        }

        if ($recreate && !empty($fixed)) {
            $this->info('');
            $this->info('✅ Fixed ' . count($fixed) . ' databases:');
            foreach ($fixed as $db) {
                $this->info("  • {$db}");
            }
        }

        return 0;
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
