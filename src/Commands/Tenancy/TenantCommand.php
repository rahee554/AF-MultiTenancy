<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantCommand extends Command
{
    protected $signature = 'tenant:manage 
                            {action? : The action to perform (list, activate, deactivate, enable-homepage, disable-homepage, status, health)}
                            {--tenant= : Tenant UUID for actions on specific tenant}
                            {--force : Force action without confirmation}';

    protected $description = 'Tenant management command - for creation use tenant:create, for database operations use tenant:db';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $actions = [
            'list' => 'List all tenants',
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
            $this->comment('💡 For tenant creation, use: php artisan tenant:create');
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
            'list' => $this->listTenants(),
            'activate' => $this->activateTenant(),
            'deactivate' => $this->deactivateTenant(),
            'enable-homepage' => $this->enableHomepage(),
            'disable-homepage' => $this->disableHomepage(),
            'status' => $this->showTenantStatus(),
            'health' => $this->checkSystemHealth(),
        };
    }

    private function listTenants(): int
    {
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            $this->info('Create your first tenant with: php artisan tenant:create');
            return 0;
        }

        $headers = ['ID', 'Name', 'Domain', 'Database', 'Status', 'Homepage', 'Created'];
        $rows = $tenants->map(function ($tenant) {
            $domain = $tenant->domains->first()?->domain ?? 'No domain';
            return [
                Str::limit($tenant->id, 8) . '...',
                $tenant->name ?? 'N/A',
                $domain,
                $tenant->database ?? 'default',
                $tenant->status ?? 'active',
                $tenant->has_homepage ? '✅' : '❌',
                $tenant->created_at->format('Y-m-d H:i')
            ];
        });

        $this->table($headers, $rows);
        $this->info("Total tenants: {$tenants->count()}");
        return 0;
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

        $tenant->update(['has_homepage' => true]);
        $this->info("✅ Homepage enabled for tenant '{$tenant->name}'.");
        return 0;
    }

    private function disableHomepage(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $tenant->update(['has_homepage' => false]);
        $this->info("✅ Homepage disabled for tenant '{$tenant->name}'.");
        return 0;
    }

    private function showTenantStatus(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $status = $this->tenantService->getTenantStatus($tenant);
        
        $this->info("📊 Tenant Status: {$tenant->name}");
        $this->newLine();
        $this->line("ID: {$tenant->id}");
        $this->line("Name: {$tenant->name}");
        $this->line("Status: {$tenant->status}");
        $this->line("Database: {$tenant->database}");
        $this->line("Has Homepage: " . ($tenant->has_homepage ? 'Yes' : 'No'));
        $this->line("Created: {$tenant->created_at}");
        
        if (!empty($status['domains'])) {
            $this->newLine();
            $this->info("Domains:");
            foreach ($status['domains'] as $domain) {
                $this->line("  • {$domain}");
            }
        }

        if (isset($status['database_status'])) {
            $this->newLine();
            $dbStatus = $status['database_status'];
            $this->info("Database Status:");
            $this->line("  Connection: " . ($dbStatus['connected'] ? '✅ Connected' : '❌ Failed'));
            if (isset($dbStatus['tables_count'])) {
                $this->line("  Tables: {$dbStatus['tables_count']}");
            }
        }

        return 0;
    }

    private function checkSystemHealth(): int
    {
        $this->info('🔍 Checking System Health...');
        $health = $this->tenantService->checkSystemHealth();
        
        $this->newLine();
        $this->info("Overall Status: " . ($health['status'] === 'healthy' ? '✅ Healthy' : '⚠️ Issues Found'));
        
        foreach ($health['checks'] as $check => $result) {
            $status = $result['status'] === 'pass' ? '✅' : '❌';
            $this->line("  {$status} {$check}: {$result['message']}");
        }

        if (isset($health['recommendations']) && !empty($health['recommendations'])) {
            $this->newLine();
            $this->warn('Recommendations:');
            foreach ($health['recommendations'] as $rec) {
                $this->line("  • {$rec}");
            }
        }

        return $health['status'] === 'healthy' ? 0 : 1;
    }

    private function findTenant(): ?Tenant
    {
        $tenantIdentifier = $this->option('tenant');
        
        if (!$tenantIdentifier) {
            $tenantIdentifier = $this->ask('Enter tenant UUID or name');
        }

        // Try to find by UUID first
        $tenant = Tenant::find($tenantIdentifier);
        
        // If not found, try to find by name
        if (!$tenant) {
            $tenant = Tenant::where('name', $tenantIdentifier)->first();
        }

        if (!$tenant) {
            $this->error("Tenant '{$tenantIdentifier}' not found.");
            return null;
        }

        return $tenant;
    }

    private function showHelp(): int
    {
        $this->error('Invalid action specified.');
        $this->newLine();
        $this->info('Available commands:');
        $this->line('  • php artisan tenant:create     - Create a new tenant');
        $this->line('  • php artisan tenant:manage     - Manage existing tenants');
        $this->line('  • php artisan tenant:db         - Database operations for tenants');
        $this->newLine();
        return 1;
    }
}
