<?php

namespace ArtflowStudio\Tenancy\Commands;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenantCommand extends Command
{
    protected $signature = 'tenant:manage
                            {action : The action to perform (create, list, delete, activate, deactivate, migrate, seed, status)}
                            {--tenant= : Tenant UUID for actions on specific tenant}
                            {--name= : Tenant name}
                            {--domain= : Tenant domain}
                            {--database= : Custom database name}
                            {--status=active : Tenant status}
                            {--notes= : Tenant notes}
                            {--force : Force action without confirmation}
                            {--seed : Run seeders after migration}
                            {--fresh : Drop all tables before migrating}';

    protected $description = 'Comprehensive tenant management command';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle()
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createTenant(),
            'list' => $this->listTenants(),
            'delete' => $this->deleteTenant(),
            'deactivate' => $this->deactivateTenant(),
            'activate' => $this->activateTenant(),
            'migrate' => $this->migrateTenant(),
            'migrate-all' => $this->migrateAllTenants(),
            'seed' => $this->seedTenant(),
            'seed-all' => $this->seedAllTenants(),
            'status' => $this->showTenantStatus(),
            'health' => $this->checkSystemHealth(),
            default => $this->showHelp(),
        };
    }

    private function createTenant(): int
    {
        $name = $this->option('name') ?: $this->ask('Tenant name');
        $domain = $this->option('domain') ?: $this->ask('Tenant domain');
        $status = $this->option('status') ?: 'active';
        $customDb = $this->option('database');
        $notes = $this->option('notes');

        if (!$name || !$domain) {
            $this->error('Name and domain are required');
            return 1;
        }

        try {
            $tenant = $this->tenantService->createTenant($name, $domain, $status, $customDb, $notes);

            $this->info("✅ Tenant created successfully!");
            $this->displayTenantInfo($tenant);

            // Optional migrations and seeding
            if ($this->confirm('Run migrations for this tenant?', true)) {
                $this->tenantService->migrateTenant($tenant, $this->option('fresh'));
                $this->info("✅ Migrations completed");
            }

            if ($this->confirm('Run seeders for this tenant?', false) || $this->option('seed')) {
                $this->tenantService->seedTenant($tenant);
                $this->info("✅ Seeders completed");
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

        $headers = ['ID', 'UUID', 'Name', 'Domain', 'Database', 'Status', 'Created'];
        $rows = $tenants->map(function ($tenant) {
            return [
                $tenant->id,
                Str::limit($tenant->uuid, 8) . '...',
                $tenant->name,
                $tenant->domain,
                $tenant->database_name ?: $tenant->getDatabaseName(),
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

        $this->info("Tenant: {$tenant->name} ({$tenant->domain})");

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

    private function migrateTenant(): int
    {
        $tenantUuid = $this->option('tenant') ?: $this->ask('Enter tenant UUID (or "all" for all tenants)');

        if ($tenantUuid === 'all') {
            return $this->migrateAllTenants();
        }

        $tenant = $this->findTenantByUuid($tenantUuid);
        if (!$tenant) return 1;

        try {
            $this->info("Running migrations for: {$tenant->name}");
            $this->tenantService->migrateTenant($tenant, $this->option('fresh'));
            $this->info("✅ Migrations completed!");

            if ($this->option('seed')) {
                $this->tenantService->seedTenant($tenant);
                $this->info("✅ Seeders completed!");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function seedTenant(): int
    {
        $tenantUuid = $this->option('tenant') ?: $this->ask('Enter tenant UUID (or "all" for all tenants)');

        if ($tenantUuid === 'all') {
            return $this->seedAllTenants();
        }

        $tenant = $this->findTenantByUuid($tenantUuid);
        if (!$tenant) return 1;

        try {
            $this->info("Running seeders for: {$tenant->name}");
            $this->tenantService->seedTenant($tenant);
            $this->info("✅ Seeders completed!");
            return 0;
        } catch (\Exception $e) {
            $this->error("Seeding failed: {$e->getMessage()}");
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

        $tenant = Tenant::where('uuid', $uuid)->first();
        if (!$tenant) {
            $this->error("Tenant not found: {$uuid}");
            return null;
        }

        return $tenant;
    }

    private function displayTenantInfo(Tenant $tenant): void
    {
        $this->table(['Field', 'Value'], [
            ['ID', $tenant->id],
            ['UUID', $tenant->uuid],
            ['Name', $tenant->name],
            ['Domain', $tenant->domain],
            ['Database', $tenant->database_name ?: $tenant->getDatabaseName()],
            ['Status', $tenant->status],
            ['Notes', $tenant->notes ?? 'N/A'],
            ['Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ]);
    }

    private function checkSystemHealth(): int
    {
        $this->info('🔍 Checking system health...');
        
        try {
            $health = $this->tenantService->checkSystemHealth();
            
            $this->info("System Status: " . ($health['status'] === 'healthy' ? '✅ HEALTHY' : '❌ UNHEALTHY'));
            $this->newLine();
            
            foreach ($health['checks'] as $check => $result) {
                $status = $result['status'] === 'ok' ? '✅' : '❌';
                $this->info("{$status} {$check}: {$result['message']}");
            }
            
            return $health['status'] === 'healthy' ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error('❌ Health check failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function migrateAllTenants(): int
    {
        $this->info('🔄 Running migrations for all active tenants...');
        $this->newLine();
        
        $fresh = $this->option('fresh');
        $results = $this->tenantService->migrateAllTenants($fresh);
        
        if ($results['success'] > 0) {
            $this->info("✅ Successfully migrated {$results['success']} tenants");
        }
        
        if ($results['failed'] > 0) {
            $this->error("❌ Failed to migrate {$results['failed']} tenants");
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        }
        
        if ($results['success'] === 0 && $results['failed'] === 0) {
            $this->info('ℹ️  No active tenants found to migrate');
        }
        
        return $results['failed'] > 0 ? 1 : 0;
    }
    
    private function seedAllTenants(): int
    {
        $this->info('🌱 Running seeders for all active tenants...');
        $this->newLine();
        
        $results = $this->tenantService->seedAllTenants();
        
        if ($results['success'] > 0) {
            $this->info("✅ Successfully seeded {$results['success']} tenants");
        }
        
        if ($results['failed'] > 0) {
            $this->error("❌ Failed to seed {$results['failed']} tenants");
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        }
        
        if ($results['success'] === 0 && $results['failed'] === 0) {
            $this->info('ℹ️  No active tenants found to seed');
        }
        
        return $results['failed'] > 0 ? 1 : 0;
    }

    private function showHelp(): int
    {
        $this->error("Unknown action. Available actions:");
        $this->info('- create: Create a new tenant');
        $this->info('- list: List all tenants');
        $this->info('- delete: Delete a tenant');
        $this->info('- activate: Activate a tenant');
        $this->info('- deactivate: Deactivate a tenant');
        $this->info('- migrate: Run migrations for a tenant');
        $this->info('- migrate-all: Run migrations for all active tenants');
        $this->info('- seed: Run seeders for a tenant');
        $this->info('- seed-all: Run seeders for all active tenants');
        $this->info('- status: Show tenant status');
        $this->info('- health: Check system health');
        return 1;
    }
}
