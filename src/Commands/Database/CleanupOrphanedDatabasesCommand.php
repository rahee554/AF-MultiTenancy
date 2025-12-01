<?php

namespace ArtflowStudio\Tenancy\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedDatabasesCommand extends Command
{
    protected $signature = 'af-tenancy:cleanup-orphaned 
                          {--force : Delete without confirmation}';

    protected $description = 'Clean up orphaned tenant databases (created but not registered)';

    public function handle()
    {
        $this->info('🔍 Scanning for orphaned tenant databases...');

        // Get all databases using INFORMATION_SCHEMA for better compatibility
        $allDatabases = DB::select("SELECT SCHEMA_NAME as `db_name` FROM INFORMATION_SCHEMA.SCHEMATA");
        $allDbNames = array_map(function ($row) {
            return $row->db_name;
        }, $allDatabases);

        // Filter tenant databases
        $tenantDbs = array_filter($allDbNames, function ($db) {
            return str_starts_with($db, 'tenant_');
        });

        if (empty($tenantDbs)) {
            $this->info('✅ No tenant databases found.');
            return 0;
        }

        $this->info('Found '.count($tenantDbs).' tenant database(s):');
        foreach ($tenantDbs as $db) {
            $this->line("  - {$db}");
        }

        // Get registered tenants' databases
        $tenantModel = config('tenancy.tenant_model');
        $registeredTenants = $tenantModel::all();
        $registeredDbs = $registeredTenants->pluck('database_name')->toArray();

        // Find orphaned databases
        $orphanedDbs = array_diff($tenantDbs, $registeredDbs);

        if (empty($orphanedDbs)) {
            $this->info('✅ All tenant databases are properly registered.');
            return 0;
        }

        $this->warn(count($orphanedDbs).' orphaned database(s) found:');
        foreach ($orphanedDbs as $db) {
            $this->line("  - {$db}");
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Delete these orphaned databases?')) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        // Delete orphaned databases
        foreach ($orphanedDbs as $db) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$db}`");
                $this->line("   ✓ Deleted {$db}");
            } catch (\Exception $e) {
                $this->error("   ❌ Failed to delete {$db}: {$e->getMessage()}");
            }
        }

        $this->info('✅ Cleanup completed');
        return 0;
    }
}
