<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Facades\Tenancy;
use Exception;

class TenantTestManagerCommand extends Command
{
    protected $signature = 'tenancy:test-tenants 
                            {action=list : Action to perform (list|create|delete|cleanup|stats)}
                            {--count=5 : Number of test tenants to create}
                            {--prefix=test : Prefix for tenant names}
                            {--domain-suffix=.localhost : Domain suffix}
                            {--migrate : Run migrations on created tenants}
                            {--seed : Seed the tenant databases}
                            {--force : Force deletion without confirmation}
                            {--interactive : Interactive mode}
                            {--pattern= : Pattern to match for deletion (e.g., test_*)}';

    protected $description = 'Comprehensive test tenant management - create, delete, and manage test tenants';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        if ($this->option('interactive')) {
            return $this->runInteractiveMode();
        }

        return match($action) {
            'list' => $this->listTestTenants(),
            'create' => $this->createTestTenants(),
            'delete' => $this->deleteTestTenants(),
            'cleanup' => $this->cleanupTestTenants(),
            'stats' => $this->showTestTenantStats(),
            default => $this->showHelp()
        };
    }

    private function runInteractiveMode(): int
    {
        $this->info('🧪 Interactive Test Tenant Manager');
        $this->newLine();

        while (true) {
            $this->displayCurrentStats();
            
            $action = $this->choice(
                'What would you like to do?',
                [
                    'list' => 'List all test tenants',
                    'create' => 'Create new test tenants',
                    'delete' => 'Delete specific test tenants',
                    'cleanup' => 'Cleanup all test tenants',
                    'stats' => 'Show detailed statistics',
                    'validate' => 'Validate test tenant integrity',
                    'exit' => 'Exit'
                ]
            );

            if ($action === 'exit') {
                $this->info('👋 Goodbye!');
                return 0;
            }

            $this->newLine();
            $this->executeAction($action);
            $this->newLine();

            if (!$this->confirm('Continue with another action?', true)) {
                break;
            }
        }

        return 0;
    }

    private function executeAction(string $action): void
    {
        match($action) {
            'list' => $this->listTestTenants(),
            'create' => $this->createTestTenantsInteractive(),
            'delete' => $this->deleteTestTenantsInteractive(),
            'cleanup' => $this->cleanupTestTenantsInteractive(),
            'stats' => $this->showTestTenantStats(),
            'validate' => $this->validateTestTenants(),
            default => $this->showHelp()
        };
    }

    private function displayCurrentStats(): void
    {
        $totalTenants = Tenant::count();
        $testTenants = $this->getTestTenants()->count();
        
        $this->info("📊 Current Status:");
        $this->line("  • Total tenants: <fg=green>{$totalTenants}</>");
        $this->line("  • Test tenants: <fg=yellow>{$testTenants}</>");
        $this->newLine();
    }

    private function createTestTenants(): int
    {
        $count = max(1, min(50, (int) $this->option('count')));
        $prefix = $this->option('prefix');
        $domainSuffix = $this->option('domain-suffix');
        $migrate = $this->option('migrate');
        $seed = $this->option('seed');

        $this->info("🏗️  Creating {$count} test tenants...");
        $this->newLine();

        return $this->performTenantCreation($count, $prefix, $domainSuffix, $migrate, $seed);
    }

    private function createTestTenantsInteractive(): void
    {
        $count = $this->ask('How many test tenants to create?', 5);
        $count = max(1, min(50, (int) $count));

        $prefix = $this->ask('Tenant name prefix?', 'test');
        $domainSuffix = $this->ask('Domain suffix?', '.localhost');
        
        $migrate = $this->confirm('Run migrations on created tenants?', true);
        $seed = $this->confirm('Seed the tenant databases?', false);

        $this->newLine();
        $this->performTenantCreation($count, $prefix, $domainSuffix, $migrate, $seed);
    }

    private function performTenantCreation(int $count, string $prefix, string $domainSuffix, bool $migrate, bool $seed): int
    {
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->start();

        $createdTenants = [];
        $failed = [];
        $timestamp = now()->format('Ymd_His');

        for ($i = 1; $i <= $count; $i++) {
            $progressBar->setMessage("Creating tenant {$i}...");

            try {
                $tenantName = "{$prefix}_{$timestamp}_{$i}";
                $domain = "{$tenantName}{$domainSuffix}";

                // Create tenant using the same method as tenant:create
                $tenant = $this->createTenantLikeStancl($tenantName, $domain);
                
                if ($migrate) {
                    $progressBar->setMessage("Migrating tenant {$i}...");
                    $this->runMigrations($tenant);
                }

                if ($seed) {
                    $progressBar->setMessage("Seeding tenant {$i}...");
                    $this->seedDatabase($tenant);
                }

                $createdTenants[] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $domain,
                    'database' => $tenant->database ?? $tenant->getTenantKey()
                ];

                $progressBar->advance();

            } catch (Exception $e) {
                $failed[] = [
                    'index' => $i,
                    'name' => $tenantName ?? "tenant_{$i}",
                    'error' => $e->getMessage()
                ];
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->displayCreationResults($createdTenants, $failed, $count);
        return empty($failed) ? 0 : 1;
    }

    private function createTenantLikeStancl(string $name, string $domain): Tenant
    {
        // Use TenantService to create tenant properly (like tenant:create does)
        $tenant = $this->tenantService->createTenant(
            name: $name,
            domain: $domain,
            status: 'active',
            notes: 'Test tenant created via tenancy:test-tenants command'
        );

        return $tenant;
    }

    private function runMigrations(Tenant $tenant): void
    {
        try {
            $tenant->run(function () {
                // Run tenant migrations
                $this->call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true
                ]);
            });
        } catch (Exception $e) {
            $this->warn("  ⚠️  Migration failed for tenant {$tenant->id}: " . $e->getMessage());
        }
    }

    private function seedDatabase(Tenant $tenant): void
    {
        try {
            $tenant->run(function () {
                // Run tenant seeders
                $this->call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'TenantDatabaseSeeder',
                    '--force' => true
                ]);
            });
        } catch (Exception $e) {
            $this->warn("  ⚠️  Seeding failed for tenant {$tenant->id}: " . $e->getMessage());
        }
    }

    private function listTestTenants(): int
    {
        $testTenants = $this->getTestTenants();

        if ($testTenants->isEmpty()) {
            $this->info('📝 No test tenants found.');
            $this->line('Create some with: <fg=cyan>php artisan tenancy:test-tenants create</fg=cyan>');
            return 0;
        }

        $this->info("📝 Found {$testTenants->count()} test tenants:");
        $this->newLine();

        $headers = ['ID', 'Name', 'Domain', 'Database', 'Status', 'Created'];
        $rows = [];

        foreach ($testTenants as $tenant) {
            $domain = $tenant->domains->first()?->domain ?? 'No domain';
            $database = $tenant->database ?? $tenant->getTenantKey(); // Use database field first, fallback to tenant key
            
            $rows[] = [
                $tenant->id,
                $tenant->name,
                $domain,
                $database,
                $tenant->status ?? 'active',
                $tenant->created_at->format('Y-m-d H:i')
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }

    private function deleteTestTenants(): int
    {
        $pattern = $this->option('pattern');
        
        if (!$pattern) {
            $this->error('❌ Pattern required for deletion. Use --pattern=test_* or similar.');
            $this->line('Example: <fg=cyan>php artisan tenancy:test-tenants delete --pattern="test_*"</fg=cyan>');
            return 1;
        }

        return $this->performTenantDeletion($pattern);
    }

    private function deleteTestTenantsInteractive(): void
    {
        $testTenants = $this->getTestTenants();

        if ($testTenants->isEmpty()) {
            $this->info('📝 No test tenants found to delete.');
            return;
        }

        $this->info("Found {$testTenants->count()} test tenants:");
        
        $options = ['All test tenants'];
        $tenantMap = ['all' => null];

        foreach ($testTenants as $tenant) {
            $domain = $tenant->domains->first()?->domain ?? 'No domain';
            $label = "{$tenant->name} ({$domain})";
            $options[] = $label;
            $tenantMap[$label] = $tenant;
        }

        $options[] = 'Cancel';

        $choice = $this->choice('Which tenants to delete?', $options);

        if ($choice === 'Cancel') {
            $this->info('Operation cancelled.');
            return;
        }

        if ($choice === 'All test tenants') {
            $this->performBulkDeletion($testTenants);
        } else {
            $tenant = $tenantMap[$choice];
            $this->performSingleTenantDeletion($tenant);
        }
    }

    private function performTenantDeletion(string $pattern): int
    {
        $pattern = str_replace('*', '%', $pattern);
        $tenants = Tenant::where('name', 'LIKE', $pattern)->get();

        if ($tenants->isEmpty()) {
            $this->info("📝 No tenants found matching pattern: {$pattern}");
            return 0;
        }

        if (!$this->option('force')) {
            $this->warn("⚠️  Found {$tenants->count()} tenants matching pattern: {$pattern}");
            
            if (!$this->confirm('Are you sure you want to delete these tenants?', false)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        return $this->performBulkDeletion($tenants);
    }

    private function performBulkDeletion($tenants): int
    {
        $count = $tenants->count();
        $this->info("🗑️  Deleting {$count} test tenants...");
        
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->start();

        $deleted = [];
        $failed = [];

        foreach ($tenants as $tenant) {
            $progressBar->setMessage("Deleting {$tenant->name}...");

            try {
                $this->deleteTenantCompletely($tenant);
                $deleted[] = $tenant->name;
            } catch (Exception $e) {
                $failed[] = [
                    'name' => $tenant->name,
                    'error' => $e->getMessage()
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->displayDeletionResults($deleted, $failed);
        return empty($failed) ? 0 : 1;
    }

    private function performSingleTenantDeletion(Tenant $tenant): void
    {
        $this->info("🗑️  Deleting tenant: {$tenant->name}");

        try {
            $this->deleteTenantCompletely($tenant);
            $this->info("✅ Successfully deleted tenant: {$tenant->name}");
        } catch (Exception $e) {
            $this->error("❌ Failed to delete tenant {$tenant->name}: " . $e->getMessage());
        }
    }

    private function deleteTenantCompletely(Tenant $tenant): void
    {
        $databaseName = $tenant->database ?? $tenant->getTenantKey();

        try {
            // Drop the database first
            DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
            
            // Delete domains
            $tenant->domains()->delete();
            
            // Delete the tenant record
            $tenant->delete();
            
        } catch (Exception $e) {
            throw new Exception("Failed to delete tenant completely: " . $e->getMessage());
        }
    }

    private function cleanupTestTenants(): int
    {
        return $this->cleanupTestTenantsInteractive();
    }

    private function cleanupTestTenantsInteractive(): int
    {
        $testTenants = $this->getTestTenants();

        if ($testTenants->isEmpty()) {
            $this->info('📝 No test tenants found to cleanup.');
            return 0;
        }

        $this->warn("⚠️  This will delete ALL {$testTenants->count()} test tenants!");
        $this->line('This action will:');
        $this->line('  • Drop all test tenant databases');
        $this->line('  • Delete all test tenant records');
        $this->line('  • Remove all associated domains');
        
        if (!$this->confirm('Are you absolutely sure?', false)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        return $this->performBulkDeletion($testTenants);
    }

    private function showTestTenantStats(): int
    {
        $testTenants = $this->getTestTenants();
        $totalTenants = Tenant::count();

        $this->info('📊 Test Tenant Statistics');
        $this->newLine();

        $headers = ['Metric', 'Value'];
        $rows = [
            ['Total Tenants', $totalTenants],
            ['Test Tenants', $testTenants->count()],
            ['Production Tenants', $totalTenants - $testTenants->count()],
            ['Test Tenant Percentage', $totalTenants > 0 ? round(($testTenants->count() / $totalTenants) * 100, 1) . '%' : '0%']
        ];

        if ($testTenants->isNotEmpty()) {
            $oldestTest = $testTenants->sortBy('created_at')->first();
            $newestTest = $testTenants->sortByDesc('created_at')->first();
            
            $rows[] = ['Oldest Test Tenant', $oldestTest->created_at->format('Y-m-d H:i')];
            $rows[] = ['Newest Test Tenant', $newestTest->created_at->format('Y-m-d H:i')];
        }

        $this->table($headers, $rows);

        // Show test tenant prefixes
        if ($testTenants->isNotEmpty()) {
            $prefixes = $testTenants->pluck('name')
                ->map(fn($name) => explode('_', $name)[0])
                ->countBy()
                ->sortDesc();

            $this->newLine();
            $this->info('📋 Test Tenant Prefixes:');
            foreach ($prefixes as $prefix => $count) {
                $this->line("  • {$prefix}: {$count} tenants");
            }
        }

        return 0;
    }

    private function validateTestTenants(): void
    {
        $testTenants = $this->getTestTenants();
        
        if ($testTenants->isEmpty()) {
            $this->info('📝 No test tenants to validate.');
            return;
        }

        $this->info("🔍 Validating {$testTenants->count()} test tenants...");
        $this->newLine();

        $issues = [];
        foreach ($testTenants as $tenant) {
            $tenantIssues = $this->validateSingleTenant($tenant);
            if (!empty($tenantIssues)) {
                $issues[$tenant->name] = $tenantIssues;
            }
        }

        if (empty($issues)) {
            $this->info('✅ All test tenants are valid!');
        } else {
            $this->warn("⚠️  Found issues with " . count($issues) . " test tenants:");
            foreach ($issues as $tenantName => $tenantIssues) {
                $this->line("  • {$tenantName}:");
                foreach ($tenantIssues as $issue) {
                    $this->line("    - {$issue}");
                }
            }
        }
    }

    private function validateSingleTenant(Tenant $tenant): array
    {
        $issues = [];
        
        // Check if domain exists
        if ($tenant->domains->isEmpty()) {
            $issues[] = 'No domains configured';
        }

        // Check if database exists
        $databaseName = $tenant->database ?? $tenant->getTenantKey();
        try {
            $databases = DB::select("SHOW DATABASES LIKE '{$databaseName}'");
            if (empty($databases)) {
                $issues[] = "Database '{$databaseName}' does not exist";
            }
        } catch (Exception $e) {
            $issues[] = "Cannot check database: " . $e->getMessage();
        }

        return $issues;
    }

    private function getTestTenants()
    {
        return Tenant::where('name', 'LIKE', 'test_%')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function displayCreationResults(array $created, array $failed, int $total): void
    {
        $this->newLine();
        $this->info("📊 Creation Results:");
        
        if (!empty($created)) {
            $this->info("✅ Successfully created " . count($created) . " tenants:");
            foreach ($created as $tenant) {
                $this->line("  • {$tenant['name']} ({$tenant['domain']}) - DB: {$tenant['database']}");
            }
        }

        if (!empty($failed)) {
            $this->newLine();
            $this->error("❌ Failed to create " . count($failed) . " tenants:");
            foreach ($failed as $failure) {
                $this->line("  • {$failure['name']}: {$failure['error']}");
            }
        }

        $this->newLine();
        $this->line("Summary: " . count($created) . " created, " . count($failed) . " failed out of {$total} total");
    }

    private function displayDeletionResults(array $deleted, array $failed): void
    {
        $this->newLine();
        $this->info("📊 Deletion Results:");
        
        if (!empty($deleted)) {
            $this->info("✅ Successfully deleted " . count($deleted) . " tenants:");
            foreach ($deleted as $name) {
                $this->line("  • {$name}");
            }
        }

        if (!empty($failed)) {
            $this->newLine();
            $this->error("❌ Failed to delete " . count($failed) . " tenants:");
            foreach ($failed as $failure) {
                $this->line("  • {$failure['name']}: {$failure['error']}");
            }
        }
    }

    private function showHelp(): int
    {
        $this->info('🧪 Test Tenant Manager');
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  • <fg=cyan>list</fg=cyan>    - List all test tenants');
        $this->line('  • <fg=cyan>create</fg=cyan>  - Create new test tenants');
        $this->line('  • <fg=cyan>delete</fg=cyan>  - Delete test tenants by pattern');
        $this->line('  • <fg=cyan>cleanup</fg=cyan> - Delete all test tenants');
        $this->line('  • <fg=cyan>stats</fg=cyan>   - Show test tenant statistics');
        $this->newLine();
        $this->line('Examples:');
        $this->line('  <fg=cyan>php artisan tenancy:test-tenants create --count=5 --migrate</fg=cyan>');
        $this->line('  <fg=cyan>php artisan tenancy:test-tenants delete --pattern="test_*" --force</fg=cyan>');
        $this->line('  <fg=cyan>php artisan tenancy:test-tenants --interactive</fg=cyan>');
        
        return 0;
    }
}
