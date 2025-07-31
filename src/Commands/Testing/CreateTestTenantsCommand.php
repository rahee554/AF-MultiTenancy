<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class CreateTestTenantsCommand extends Command
{
    protected $signature = 'tenancy:create-test-tenants 
                            {count=5 : Number of test tenants to create}
                            {--prefix=test : Prefix for tenant names}
                            {--domain-suffix=.localhost : Domain suffix}
                            {--migrate : Run migrations on created tenants}
                            {--seed : Seed the tenant databases}';

    protected $description = 'Create test tenants with timestamp for testing purposes';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle(): int
    {
        $count = max(1, min(20, (int) $this->argument('count'))); // Limit between 1-20
        $prefix = $this->option('prefix');
        $domainSuffix = $this->option('domain-suffix');
        $migrate = $this->option('migrate');
        $seed = $this->option('seed');

        $this->info("🏗️  Creating {$count} test tenants...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->start();

        $createdTenants = [];
        $timestamp = now()->format('Ymd_His');

        for ($i = 1; $i <= $count; $i++) {
            $progressBar->setMessage("Creating tenant {$i}...");

            try {
                $tenantName = "{$prefix}_{$timestamp}_{$i}";
                $domain = "{$tenantName}{$domainSuffix}";

                // Use TenantService to create tenant properly
                $tenant = $this->tenantService->createTenant(
                    name: $tenantName,
                    domain: $domain,
                    status: 'active',
                    notes: 'Test tenant created via command'
                );
                
                if ($migrate) {
                    $this->runMigrations($tenant);
                }

                if ($seed) {
                    $this->seedDatabase($tenant);
                }

                $createdTenants[] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $domain
                ];

                $progressBar->advance();

            } catch (\Exception $e) {
                $progressBar->setMessage("Failed to create tenant {$i}: " . $e->getMessage());
                $this->newLine();
                $this->error("❌ Failed to create tenant {$i}: " . $e->getMessage());
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        // Display results
        $this->displayResults($createdTenants, $count);

        return 0;
    }

    private function runMigrations(Tenant $tenant): void
    {
        try {
            $this->tenantService->migrateTenant($tenant);
        } catch (\Exception $e) {
            $this->warn("⚠️  Migration failed for tenant {$tenant->name}: " . $e->getMessage());
        }
    }

    private function seedDatabase(Tenant $tenant): void
    {
        try {
            Tenancy::initialize($tenant);
            
            // Run seeders
            $this->call('db:seed', [
                '--force' => true,
                '--class' => 'TenantDatabaseSeeder'
            ]);

            Tenancy::end();
        } catch (\Exception $e) {
            Tenancy::end();
            // Don't throw for seeder failures, just warn
            $this->warn("⚠️  Seeding failed for tenant {$tenant->name}: " . $e->getMessage());
        }
    }

    private function displayResults(array $createdTenants, int $requestedCount): void
    {
        $successCount = count($createdTenants);
        $failureCount = $requestedCount - $successCount;

        $this->newLine();
        
        if ($successCount > 0) {
            $this->info("✅ Successfully created {$successCount} test tenants:");
            $this->newLine();

            $headers = ['ID', 'Name', 'Domain'];
            $rows = array_map(function ($tenant) {
                return [
                    substr($tenant['id'], 0, 8) . '...',
                    $tenant['name'],
                    $tenant['domain']
                ];
            }, $createdTenants);

            $this->table($headers, $rows);
        }

        if ($failureCount > 0) {
            $this->error("❌ Failed to create {$failureCount} tenants");
        }

        $this->newLine();
        $this->comment('💡 Use these commands to manage your test tenants:');
        $this->line('  • php artisan tenants:list - List all tenants');
        $this->line('  • php artisan tenancy:health-check - Check system health');
        $this->line('  • php artisan tenancy:test-all - Run full test suite');
        $this->line('  • php artisan tenant:delete <tenant-id> - Delete a tenant');
    }
}
