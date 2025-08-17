<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Support\Str;

class CreateTestTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:create-test-tenants 
                            {--count=5 : Number of test tenants to create}
                            {--domain-prefix=test : Domain prefix for test tenants}
                            {--with-data : Create tenants with sample data}
                            {--load-test : Create tenants optimized for load testing}';

    /**
     * The console command description.
     */
    protected $description = 'Create test tenants for development and performance testing';

    /**
     * The tenant service instance.
     */
    protected TenantService $tenantService;

    /**
     * Create a new command instance.
     */
    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $prefix = $this->option('domain-prefix');
        $withData = $this->option('with-data');
        $loadTest = $this->option('load-test');

        if ($count > 50) {
            $this->error('Maximum 50 test tenants allowed at once for safety.');
            return 1;
        }

        $this->info("Creating {$count} test tenants with prefix '{$prefix}'...");
        
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $created = [];
        $errors = [];

        for ($i = 1; $i <= $count; $i++) {
            try {
                $name = $loadTest ? "LoadTest Tenant {$i}" : "Test Company {$i}";
                $domain = "{$prefix}{$i}.local";
                
                // Check if domain already exists
                if (\DB::table('domains')->where('domain', $domain)->exists()) {
                    $this->warn("\nDomain {$domain} already exists, skipping...");
                    $progressBar->advance();
                    continue;
                }

                $tenant = $this->tenantService->createTenant(
                    name: $name,
                    domain: $domain,
                    status: 'active',
                    notes: $loadTest ? 'Load testing tenant' : 'Development testing tenant'
                );

                // Run migrations for the new tenant
                $this->tenantService->migrateTenant($tenant);

                // Add sample data if requested
                if ($withData || $loadTest) {
                    $this->createSampleData($tenant);
                }

                $created[] = [
                    'name' => $tenant->name,
                    'domain' => $domain,
                    'uuid' => $tenant->uuid,
                    'database' => $tenant->database_name
                ];

                $progressBar->advance();

            } catch (\Exception $e) {
                $errors[] = "Failed to create tenant {$i}: " . $e->getMessage();
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        if (!empty($created)) {
            $this->info("Successfully created " . count($created) . " test tenants:");
            $this->table(
                ['Name', 'Domain', 'UUID', 'Database'],
                array_map(fn($tenant) => [
                    $tenant['name'],
                    $tenant['domain'],
                    $tenant['uuid'],
                    $tenant['database']
                ], $created)
            );
        }

        if (!empty($errors)) {
            $this->error("Errors encountered:");
            foreach ($errors as $error) {
                $this->error("  • {$error}");
            }
        }

        // Display testing instructions
        $this->displayTestingInstructions($created, $prefix);

        return 0;
    }

    /**
     * Create sample data for a tenant.
     */
    protected function createSampleData($tenant): void
    {
        try {
            // Initialize tenant context
            tenancy()->initialize($tenant);

            // Create sample data (basic examples)
            \DB::table('users')->insert([
                'name' => 'Test User',
                'email' => 'test@' . $tenant->getPrimaryDomainName(),
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add more sample data based on your application needs
            // This is just an example - customize based on your tenant schema

            tenancy()->end();
        } catch (\Exception $e) {
            $this->warn("Failed to create sample data for {$tenant->name}: {$e->getMessage()}");
        }
    }

    /**
     * Display testing instructions.
     */
    protected function displayTestingInstructions(array $created, string $prefix): void
    {
        if (empty($created)) {
            return;
        }

        $this->newLine();
        $this->info('🚀 Testing Instructions:');
        $this->line('');

        // Local development setup
        $this->comment('1. Add to your /etc/hosts file (Linux/Mac) or C:\Windows\System32\drivers\etc\hosts (Windows):');
        foreach ($created as $tenant) {
            $this->line("   127.0.0.1    {$tenant['domain']}");
        }

        $this->line('');
        $this->comment('2. Test tenant access:');
        $this->line("   • Visit: http://{$created[0]['domain']}");
        $this->line("   • Test API: curl -H \"X-API-Key: your_key\" http://{$created[0]['domain']}/api/tenant-info");

        $this->line('');
        $this->comment('3. Performance testing commands:');
        $this->line('   • Basic test: php artisan tenancy:test-performance');
        $this->line('   • Load test: php artisan tenancy:benchmark --users=50 --tenants=' . count($created));
        $this->line('   • Memory test: php artisan tenancy:test-memory --tenants=' . count($created));

        $this->line('');
        $this->comment('4. Cleanup when done:');
        $this->line('   • Remove test tenants: php artisan tenancy:cleanup-test-tenants');
        
        $this->line('');
        $this->info('✅ Test environment ready! Happy testing!');
    }
}
