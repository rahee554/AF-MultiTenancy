<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;

class TenantCommand extends Command
{
    protected $signature = 'tenant:manage 
                            {action? : The action to perform (create, list, delete, activate, deactivate, enable-homepage, disable-homepage, status, health)}
                            {--tenant= : Tenant UUID for actions on specific tenant}
                            {--name= : Tenant name}
                            {--domain= : Tenant domain}
                            {--database= : Custom database name}
                            {--status=active : Tenant status}
                            {--homepage : Enable homepage for tenant}
                            {--notes= : Tenant notes}
                            {--force : Force action without confirmation}';

    protected $description = 'Comprehensive tenant management command - for database operations use tenant:db';    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $actions = [
            'create' => 'Create a new tenant',
            'list' => 'List all tenants',
            'delete' => 'Delete a tenant',
            'activate' => 'Activate a tenant',
            'deactivate' => 'Deactivate a tenant',
            'enable-homepage' => 'Enable homepage for a tenant',
            'disable-homepage' => 'Disable homepage for a tenant',
            'status' => 'Show tenant status',
            'health' => 'Check system health'
        ];

        if (!$action) {
            $this->info('🚀 Tenant Management System');
            $this->info('Available actions:');
            $this->newLine();
            foreach ($actions as $cmd => $desc) {
                $this->info("  <fg=green>{$cmd}</fg=green> - {$desc}");
            }
            $this->newLine();
            $this->comment('💡 For database operations (migrate, seed, rollback), use: php artisan tenant:db');
            $this->newLine();
            $action = $this->choice('Please select an action', array_keys($actions));
        }

        // Check for database operations first and redirect
        if (in_array($action, ['migrate', 'migrate-all', 'seed', 'seed-all'])) {
            return $this->redirectToDatabaseCommand($action);
        }

        if (!array_key_exists($action, $actions)) {
            return $this->showHelp();
        }

        return match ($action) {
            'create' => $this->createTenant(),
            'list' => $this->listTenants(),
            'delete' => $this->deleteTenant(),
            'deactivate' => $this->deactivateTenant(),
            'activate' => $this->activateTenant(),
            'enable-homepage' => $this->enableHomepage(),
            'disable-homepage' => $this->disableHomepage(),
            'status' => $this->showTenantStatus(),
            'health' => $this->checkSystemHealth(),
        };
    }

    private function createTenant(): int
    {
        $name = $this->option('name') ?: $this->ask('Tenant name');
        $domain = $this->option('domain') ?: $this->ask('Tenant domain');
        
        // Ask for database name
        $customDb = $this->option('database');
        if (!$customDb) {
            $customDb = $this->ask('Database name (leave empty for auto-generated)', null);
        }
        
        // Ask for homepage
        $hasHomepage = $this->option('homepage') || $this->confirm('Does this tenant have a homepage?', false);
        
        $status = $this->option('status') ?: 'active';
        $notes = $this->option('notes');

        if (!$name || !$domain) {
            $this->error('Name and domain are required');
            return 1;
        }

        // Normalize and prefix custom DB name
        if ($customDb && strtolower($customDb) !== 'null') {
            $prefix = env('TENANT_DB_PREFIX', 'tenant_');
            // Replace hyphens, spaces, and other non-alphanumeric chars with underscores
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $customDb);
            // Collapse multiple underscores into one
            $normalized = preg_replace('/_+/', '_', $normalized);
            // Trim leading/trailing underscores
            $normalized = trim($normalized, '_');
            // Add prefix if not already present
            if (!str_starts_with($normalized, $prefix)) {
                $normalized = $prefix . $normalized;
            }
            $customDb = strtolower($normalized);
        } else {
            $customDb = null; // Will auto-generate
        }

        try {
            $tenant = $this->tenantService->createTenant($name, $domain, $status, $customDb, $notes, $hasHomepage);

            $this->info("✅ Tenant created successfully!");
            $this->newLine();
            
            // Beautiful summary table
            $primaryDomain = $tenant->domains()->first();
            $this->table([
                'Field', 'Value'
            ], [
                ['🏢 Tenant Name', $tenant->name],
                ['🌐 Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
                ['💾 Database', $tenant->getDatabaseName()],
                ['🏠 Homepage', $tenant->hasHomepage() ? 'Enabled' : 'Disabled'],
                ['📊 Status', $tenant->status],
                ['🆔 UUID', $tenant->id],
                ['📅 Created', $tenant->created_at->format('Y-m-d H:i:s')],
            ]);

            // Optional migrations and seeding
            if ($this->confirm('Run migrations for this tenant?', true)) {
                $this->info('🔄 Running migrations via tenant:db...');
                $migrateExitCode = Artisan::call('tenant:db', [
                    'operation' => 'migrate',
                    '--tenant' => $tenant->id
                ]);
                
                if ($migrateExitCode === 0) {
                    $this->info("✅ Migrations completed");
                } else {
                    $this->warn("⚠️ Migrations had issues (exit code: {$migrateExitCode})");
                }
            }

            if ($this->confirm('Run seeders for this tenant?', false)) {
                $this->info('🌱 Running seeders via tenant:db...');
                $seedExitCode = Artisan::call('tenant:db', [
                    'operation' => 'seed',
                    '--tenant' => $tenant->id
                ]);
                
                if ($seedExitCode === 0) {
                    $this->info("✅ Seeders completed");
                } else {
                    $this->warn("⚠️ Seeders had issues (exit code: {$seedExitCode})");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function listTenants(): int
    {
        $tenants = Tenant::orderBy('created_at', 'desc')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return 0;
        }

        $headers = ['ID', 'UUID', 'Name', 'Domain', 'Database', 'Homepage', 'Status', 'Created'];
        $rows = $tenants->map(function ($tenant) {
            $primaryDomain = $tenant->domains()->first();
            return [
                $tenant->id,
                Str::limit($tenant->id, 8) . '...',
                $tenant->name,
                $primaryDomain ? $primaryDomain->domain : 'No domain',
                $tenant->getDatabaseName(),
                $tenant->hasHomepage() ? '✅ Yes' : '❌ No',
                $tenant->status,
                $tenant->created_at->format('Y-m-d H:i'),
            ];
        });

        $this->table($headers, $rows);
        $this->info("Total tenants: {$tenants->count()}");
        return 0;
    }

    private function deleteTenant(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $primaryDomain = $tenant->domains()->first();
        $domainName = $primaryDomain ? $primaryDomain->domain : 'No domain';
        $this->info("Tenant: {$tenant->name} ({$domainName})");

        if (!$this->option('force') && !$this->confirm('Delete this tenant and its database?', false)) {
            $this->info('Deletion cancelled.');
            return 0;
        }

        try {
            $this->tenantService->deleteTenant($tenant);
            $this->info('✅ Tenant deleted successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to delete tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function activateTenant(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $this->tenantService->activateTenant($tenant);
        $this->info("✅ Tenant '{$tenant->name}' activated!");
        return 0;
    }

    private function deactivateTenant(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $this->tenantService->deactivateTenant($tenant);
        $this->info("✅ Tenant '{$tenant->name}' deactivated!");
        return 0;
    }

    private function enableHomepage(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        try {
            $tenant->enableHomepage();
            $this->info("✅ Homepage enabled for tenant '{$tenant->name}'!");
            $this->info("   Tenant will now show homepage at root URL");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to enable homepage: {$e->getMessage()}");
            return 1;
        }
    }

    private function disableHomepage(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        try {
            $tenant->disableHomepage();
            $this->info("✅ Homepage disabled for tenant '{$tenant->name}'!");
            $this->info("   Tenant will now redirect to /login from root URL");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to disable homepage: {$e->getMessage()}");
            return 1;
        }
    }

    private function showTenantStatus(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $status = $this->tenantService->getTenantStatus($tenant);

        $this->info("Tenant Status Report");
        $this->info("==================");

        $this->displayTenantInfo($tenant);

        $this->info("\nDatabase Status:");
        $this->info($status['database_exists'] ? "✅ Database exists" : "❌ Database missing");
        
        if ($status['database_exists']) {
            $this->info("📊 Migrations: {$status['migration_count']}");
            $this->info("📋 Tables: {$status['table_count']}");
            $this->info("💾 Size: {$status['database_size']} MB");
        }

        if (isset($status['error'])) {
            $this->error("⚠️  Error: {$status['error']}");
        }

        return 0;
    }

    private function findTenant(): ?Tenant
    {
        $tenantUuid = $this->option('tenant') ?: $this->ask('Enter tenant UUID');
        return $this->findTenantByUuid($tenantUuid);
    }

    private function findTenantByUuid(?string $uuid): ?Tenant
    {
        if (!$uuid) {
            $this->error('Tenant UUID is required');
            return null;
        }

        $tenant = Tenant::where('id', $uuid)->first();
        if (!$tenant) {
            $this->error("Tenant not found: {$uuid}");
            return null;
        }

        return $tenant;
    }

    private function displayTenantInfo(Tenant $tenant): void
    {
        $primaryDomain = $tenant->domains()->first();
        $this->table(['Field', 'Value'], [
            ['ID', $tenant->id],
            ['Name', $tenant->name],
            ['Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
            ['Database', $tenant->getDatabaseName()],
            ['Status', $tenant->status],
            ['Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ]);
    }

    private function checkSystemHealth(): int
    {
        $this->info('🔍 Checking system health...');
        $this->newLine();
        
        try {
            $health = $this->tenantService->checkSystemHealth();
            
            $overallStatus = $health['status'] === 'healthy' ? '✅ HEALTHY' : '❌ UNHEALTHY';
            $this->info("🎯 System Status: {$overallStatus}");
            $this->newLine();
            
            // Create table for health checks
            $rows = [];
            foreach ($health['checks'] as $check => $result) {
                $status = $result['status'] === 'ok' ? '✅' : '❌';
                $rows[] = [
                    $check,
                    $status,
                    $result['message']
                ];
            }
            
            $this->table(['Component', 'Status', 'Details'], $rows);
            
            // Add summary info
            if (isset($health['summary'])) {
                $this->newLine();
                $this->info('📊 Summary:');
                foreach ($health['summary'] as $key => $value) {
                    $this->info("   {$key}: {$value}");
                }
            }
            
            return $health['status'] === 'healthy' ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error('❌ Health check failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function showHelp(): int
    {
        $this->error("Unknown action. Available actions:");
        $this->info('- create: Create a new tenant');
        $this->info('- list: List all tenants');
        $this->info('- delete: Delete a tenant');
        $this->info('- activate: Activate a tenant');
        $this->info('- deactivate: Deactivate a tenant');
        $this->info('- enable-homepage: Enable homepage for a tenant');
        $this->info('- disable-homepage: Disable homepage for a tenant');
        $this->info('- status: Show tenant status');
        $this->info('- health: Check system health');
        $this->newLine();
        $this->comment('💡 For database operations (migrate, seed, rollback), use: php artisan tenant:db');
        return 1;
    }

    private function redirectToDatabaseCommand(string $action): int
    {
        $this->warn("⚠️  Database operation '{$action}' has been moved to 'tenant:db' command.");
        $this->newLine();
        $this->info('💡 Use one of these commands instead:');
        
        $suggestions = match($action) {
            'migrate' => [
                'tenant:db migrate --tenant=<uuid>' => 'Migrate specific tenant',
                'tenant:db migrate --all' => 'Migrate all active tenants'
            ],
            'migrate-all' => [
                'tenant:db migrate --all' => 'Migrate all active tenants'
            ],
            'seed' => [
                'tenant:db seed --tenant=<uuid>' => 'Seed specific tenant',
                'tenant:db seed --all' => 'Seed all active tenants'
            ],
            'seed-all' => [
                'tenant:db seed --all' => 'Seed all active tenants'
            ],
            default => [
                'tenant:db --help' => 'See all database operations'
            ]
        };

        foreach ($suggestions as $command => $description) {
            $this->info("  <fg=green>{$command}</fg=green> - {$description}");
        }
        
        $this->newLine();
        $this->comment('For more database operations: php artisan tenant:db --help');
        
        return 1;
    }
}
