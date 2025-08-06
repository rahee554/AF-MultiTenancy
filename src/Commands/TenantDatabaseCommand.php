<?php

namespace ArtflowStudio\Tenancy\Commands;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Facades\Tenancy;

class TenantDatabaseCommand extends Command
{
    protected $signature = 'tenant:db
                            {operation? : Database operation (migrate, migrate:fresh, migrate:rollback, seed, migrate:status)}
                            {--tenant= : Tenant UUID or name}
                            {--class= : Seeder class name for seeding}
                            {--step= : Number of steps to rollback}
                            {--force : Force operation without confirmation}
                            {--seed : Run seeders after migration}
                            {--all : Run operation for all active tenants}
                            {--status=active : Filter tenants by status}
                            {--pretend : Show what would be migrated without executing}';

    protected $description = 'Database operations for tenants (migrate, seed, rollback, etc.)';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle(): int
    {
        $operation = $this->argument('operation');
        
        $operations = [
            'migrate' => 'Run migrations for tenant database',
            'migrate:fresh' => 'Drop all tables and re-run migrations',
            'migrate:rollback' => 'Rollback migrations',
            'migrate:status' => 'Show migration status',
            'seed' => 'Run database seeders',
            'fresh-seed' => 'Fresh migrate + seed in one command',
            'reset' => 'Rollback all migrations',
            'refresh' => 'Rollback and re-run migrations',
            'sync' => 'Sync migrations/seeders from shared to tenant directories',
        ];

        if (!$operation) {
            $this->displayWelcome($operations);
            $operation = $this->choice('Select database operation', array_keys($operations));
        }

        if (!array_key_exists($operation, $operations)) {
            return $this->showHelp($operations);
        }

        // Handle operations that work on all tenants
        if ($this->option('all')) {
            return $this->runForAllTenants($operation);
        }

        // Single tenant operations
        $tenant = $this->selectTenant();
        if (!$tenant) {
            return 1;
        }

        return $this->runOperation($operation, $tenant);
    }

    /**
     * Display welcome message with available operations
     */
    private function displayWelcome(array $operations): void
    {
        $this->info('🗄️  Tenant Database Management');
        $this->info('Manage database operations for individual or all tenants');
        $this->newLine();
        
        $this->info('Available operations:');
        foreach ($operations as $cmd => $desc) {
            $this->info("  <fg=green>{$cmd}</fg=green> - {$desc}");
        }
        $this->newLine();
    }

    /**
     * Smart tenant selection with multiple options
     */
    private function selectTenant(): ?Tenant
    {
        $tenantIdentifier = $this->option('tenant');
        
        if ($tenantIdentifier) {
            // Try to find by UUID first, then by name
            $tenant = Tenant::where('id', $tenantIdentifier)->first();
            
            if (!$tenant) {
                // Try searching by name if UUID not found
                $tenant = Tenant::where('name', 'LIKE', "%{$tenantIdentifier}%")->first();
            }
                           
            if ($tenant) {
                $this->displaySelectedTenant($tenant);
                return $tenant;
            }
            
            $this->error("❌ Tenant not found: {$tenantIdentifier}");
            $this->info('💡 Try searching by name or UUID');
        }

        return $this->interactiveTenantSelection();
    }

    /**
     * Interactive tenant selection with search and filtering
     */
    private function interactiveTenantSelection(): ?Tenant
    {
        $this->info('🔍 Available tenant selection methods:');
        $this->info('1. List all tenants and select');
        $this->info('2. Search by name');
        $this->info('3. Enter UUID directly');
        
        $method = $this->choice('How would you like to select the tenant?', [
            'List and select',
            'Search by name', 
            'Enter UUID'
        ], 'List and select');

        return match ($method) {
            'List and select' => $this->selectFromList(),
            'Search by name' => $this->searchByName(),
            'Enter UUID' => $this->selectByUuid(),
            default => null
        };
    }

    /**
     * Select tenant from a list
     */
    private function selectFromList(): ?Tenant
    {
        $status = $this->option('status');
        $query = Tenant::query();
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        $tenants = $query->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->error("❌ No {$status} tenants found");
            return null;
        }

        // Display tenants in a nice table first
        $this->info("📋 Available tenants (Status: {$status}):");
        $headers = ['#', 'Name', 'Domain', 'Database', 'Status', 'UUID (short)'];
        $rows = $tenants->map(function ($tenant, $index) {
            $primaryDomain = $tenant->domains()->first();
            return [
                $index + 1,
                Str::limit($tenant->name, 25),
                $primaryDomain ? Str::limit($primaryDomain->domain, 30) : 'No domain',
                Str::limit($tenant->getDatabaseName(), 20),
                $tenant->status,
                Str::limit($tenant->id, 8) . '...'
            ];
        })->toArray();

        $this->table($headers, $rows);

        // Create choices for selection - simple array with tenant names and numbers
        $choices = [];
        foreach ($tenants as $index => $tenant) {
            $primaryDomain = $tenant->domains()->first();
            $domainText = $primaryDomain ? " ({$primaryDomain->domain})" : "";
            $choices[] = "{$tenant->name}{$domainText}";
        }

        $selection = $this->choice('Select a tenant', $choices);
        
        // Find the selected tenant by matching the choice text
        $selectedIndex = array_search($selection, $choices);
        $tenant = $tenants->get($selectedIndex);
        
        $this->displaySelectedTenant($tenant);
        
        return $tenant;
    }

    /**
     * Search tenants by name
     */
    private function searchByName(): ?Tenant
    {
        $searchTerm = $this->ask('Enter tenant name to search');
        
        if (!$searchTerm) {
            $this->error('❌ Search term is required');
            return null;
        }

        $tenants = Tenant::where('name', 'LIKE', "%{$searchTerm}%")
                         ->orderBy('name')
                         ->get();

        if ($tenants->isEmpty()) {
            $this->error("❌ No tenants found matching: {$searchTerm}");
            return null;
        }

        if ($tenants->count() === 1) {
            $tenant = $tenants->first();
            $this->info("✅ Found exact match: {$tenant->name}");
            $this->displaySelectedTenant($tenant);
            return $tenant;
        }

        // Multiple matches - let user choose
        $this->info("🔍 Found {$tenants->count()} matching tenants:");
        $choices = [];
        foreach ($tenants as $tenant) {
            $primaryDomain = $tenant->domains()->first();
            $domainText = $primaryDomain ? " ({$primaryDomain->domain})" : "";
            $choices[] = "{$tenant->name}{$domainText}";
        }

        $selection = $this->choice('Select the correct tenant', $choices);
        $selectedIndex = array_search($selection, $choices);
        $tenant = $tenants->get($selectedIndex);
        
        $this->displaySelectedTenant($tenant);
        return $tenant;
    }

    /**
     * Select tenant by UUID
     */
    private function selectByUuid(): ?Tenant
    {
        $uuid = $this->ask('Enter tenant UUID');
        
        if (!$uuid) {
            $this->error('❌ UUID is required');
            return null;
        }

        $tenant = Tenant::where('id', $uuid)->first();
        
        if (!$tenant) {
            $this->error("❌ Tenant not found with UUID: {$uuid}");
            return null;
        }

        $this->displaySelectedTenant($tenant);
        return $tenant;
    }

    /**
     * Display selected tenant information
     */
    private function displaySelectedTenant(Tenant $tenant): void
    {
        $primaryDomain = $tenant->domains()->first();
        
        $this->info('🎯 Selected Tenant:');
        $this->table(['Field', 'Value'], [
            ['Name', $tenant->name],
            ['Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
            ['Database', $tenant->getDatabaseName()],
            ['Status', $tenant->status],
            ['UUID', $tenant->id],
        ]);
        $this->newLine();
    }

    /**
     * Run operation for a specific tenant
     */
    private function runOperation(string $operation, Tenant $tenant): int
    {
        try {
            return match ($operation) {
                'migrate' => $this->runMigrate($tenant),
                'migrate:fresh' => $this->runMigrateFresh($tenant),
                'migrate:rollback' => $this->runMigrateRollback($tenant),
                'migrate:status' => $this->runMigrateStatus($tenant),
                'seed' => $this->runSeed($tenant),
                'fresh-seed' => $this->runFreshSeed($tenant),
                'reset' => $this->runReset($tenant),
                'refresh' => $this->runRefresh($tenant),
                'sync' => $this->runSync(),
                default => $this->showHelp([])
            };
        } catch (\Exception $e) {
            $this->error("❌ Operation failed: {$e->getMessage()}");
            $this->info("💡 Check logs for more details");
            return 1;
        }
    }

    /**
     * Run migrate for tenant
     */
    private function runMigrate(Tenant $tenant): int
    {
        $this->info("🔄 Running migrations for: {$tenant->name}");
        
        if ($this->option('pretend')) {
            $this->info("🔍 [DRY RUN] - Would run migrations (no actual changes)");
        }

        tenancy()->initialize($tenant);
        
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        
        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--pretend' => $this->option('pretend'),
            '--path' => $tenantMigrationsPath,
        ]);

        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        if ($exitCode === 0) {
            $this->info("✅ Migrations completed successfully!");
            
            if ($this->option('seed')) {
                return $this->runSeed($tenant);
            }
        } else {
            $this->error("❌ Migrations failed");
        }

        return $exitCode;
    }

    /**
     * Run fresh migration for tenant
     */
    private function runMigrateFresh(Tenant $tenant): int
    {
        $this->warn("⚠️  This will DROP ALL TABLES and re-run migrations!");
        
        if (!$this->option('force') && !$this->confirm("Are you sure you want to continue?", false)) {
            $this->info("❌ Operation cancelled");
            return 0;
        }

        $this->info("🗑️  Dropping all tables and running fresh migrations for: {$tenant->name}");

        tenancy()->initialize($tenant);
        
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        
        $exitCode = Artisan::call('migrate:fresh', [
            '--force' => true,
            '--path' => $tenantMigrationsPath,
        ]);

        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        if ($exitCode === 0) {
            $this->info("✅ Fresh migration completed successfully!");
            
            if ($this->option('seed')) {
                return $this->runSeed($tenant);
            }
        } else {
            $this->error("❌ Fresh migration failed");
        }

        return $exitCode;
    }

    /**
     * Run rollback for tenant
     */
    private function runMigrateRollback(Tenant $tenant): int
    {
        $step = $this->option('step') ?: $this->ask('How many steps to rollback?', '1');
        
        $this->info("↩️  Rolling back {$step} step(s) for: {$tenant->name}");

        tenancy()->initialize($tenant);
        
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        
        $exitCode = Artisan::call('migrate:rollback', [
            '--step' => (int)$step,
            '--force' => true,
            '--path' => $tenantMigrationsPath,
        ]);

        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        if ($exitCode === 0) {
            $this->info("✅ Rollback completed successfully!");
        } else {
            $this->error("❌ Rollback failed");
        }

        return $exitCode;
    }

    /**
     * Show migration status for tenant
     */
    private function runMigrateStatus(Tenant $tenant): int
    {
        $this->info("📊 Migration status for: {$tenant->name}");

        tenancy()->initialize($tenant);
        
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        
        $exitCode = Artisan::call('migrate:status', [
            '--path' => $tenantMigrationsPath,
        ]);
        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        return $exitCode;
    }

    /**
     * Run seeders for tenant
     */
    private function runSeed(Tenant $tenant): int
    {
        $class = $this->option('class');
        
        if (!$class) {
            $class = $this->ask('Enter seeder class name (leave empty for DatabaseSeeder)', 'DatabaseSeeder');
        }

        $this->info("🌱 Running seeder '{$class}' for: {$tenant->name}");

        tenancy()->initialize($tenant);
        
        $params = ['--force' => true];
        
        if ($class && $class !== 'DatabaseSeeder') {
            $params['--class'] = $class;
        }
        
        // Check if tenant seeder exists first
        $tenantSeedersPath = config('artflow-tenancy.seeders.tenant_seeders_path', 'database/seeders/tenant');
        $seederFile = base_path($tenantSeedersPath . '/' . $class . '.php');
        
        if (file_exists($seederFile)) {
            $this->info("📁 Using tenant-specific seeder from: {$tenantSeedersPath}");
            // For tenant seeders, we might need to adjust the class namespace
        } else {
            $this->info("📁 Using shared seeder");
        }
        
        $exitCode = Artisan::call('db:seed', $params);
        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        if ($exitCode === 0) {
            $this->info("✅ Seeding completed successfully!");
        } else {
            $this->error("❌ Seeding failed");
        }

        return $exitCode;
    }

    /**
     * Run fresh migration + seed in one command
     */
    private function runFreshSeed(Tenant $tenant): int
    {
        $this->info("🔄 Running fresh migration + seeding for: {$tenant->name}");
        
        $result = $this->runMigrateFresh($tenant);
        if ($result !== 0) {
            return $result;
        }

        return $this->runSeed($tenant);
    }

    /**
     * Reset all migrations
     */
    private function runReset(Tenant $tenant): int
    {
        $this->warn("⚠️  This will rollback ALL migrations!");
        
        if (!$this->option('force') && !$this->confirm("Are you sure?", false)) {
            return 0;
        }

        $this->info("🔄 Resetting all migrations for: {$tenant->name}");

        tenancy()->initialize($tenant);
        
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        
        $exitCode = Artisan::call('migrate:reset', [
            '--force' => true,
            '--path' => $tenantMigrationsPath,
        ]);
        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        return $exitCode;
    }

    /**
     * Refresh migrations (rollback + migrate)
     */
    private function runRefresh(Tenant $tenant): int
    {
        $this->info("🔄 Refreshing migrations for: {$tenant->name}");

        tenancy()->initialize($tenant);
        
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        
        $exitCode = Artisan::call('migrate:refresh', [
            '--force' => true,
            '--path' => $tenantMigrationsPath,
        ]);
        $output = Artisan::output();
        $this->line($output);

        tenancy()->end();

        if ($exitCode === 0 && $this->option('seed')) {
            return $this->runSeed($tenant);
        }

        return $exitCode;
    }

    /**
     * Sync migrations and seeders from shared to tenant directories
     */
    private function runSync(): int
    {
        $this->info("🔄 Syncing migrations and seeders to tenant directories");
        $this->newLine();

        $config = config('artflow-tenancy');
        
        // Get paths
        $sharedMigrationsPath = base_path($config['migrations']['shared_migrations_path']);
        $tenantMigrationsPath = base_path($config['migrations']['tenant_migrations_path']);
        $sharedSeedersPath = base_path($config['seeders']['shared_seeders_path']);
        $tenantSeedersPath = base_path($config['seeders']['tenant_seeders_path']);
        
        // Get skip lists
        $skipMigrations = $config['migrations']['skip_migrations'] ?? [];
        $skipSeeders = $config['seeders']['skip_seeders'] ?? [];

        $results = [
            'migrations_copied' => 0,
            'migrations_skipped' => 0,
            'seeders_copied' => 0,
            'seeders_skipped' => 0,
        ];

        // Sync Migrations
        $this->info("📁 Syncing migrations...");
        $this->syncMigrations($sharedMigrationsPath, $tenantMigrationsPath, $skipMigrations, $results);
        
        $this->newLine();
        
        // Sync Seeders (optional)
        $syncSeeders = $this->confirm('Do you want to sync seeders as well?', true);
        if ($syncSeeders) {
            $this->info("🌱 Syncing seeders...");
            $this->syncSeeders($sharedSeedersPath, $tenantSeedersPath, $skipSeeders, $results);
        }

        // Display results
        $this->newLine();
        $this->info("📊 Sync Summary:");
        $this->info("   📁 Migrations copied: {$results['migrations_copied']}");
        $this->info("   ⏭️  Migrations skipped: {$results['migrations_skipped']}");
        if ($syncSeeders) {
            $this->info("   🌱 Seeders copied: {$results['seeders_copied']}");
            $this->info("   ⏭️  Seeders skipped: {$results['seeders_skipped']}");
        }

        $totalOperations = $results['migrations_copied'] + ($syncSeeders ? $results['seeders_copied'] : 0);
        
        if ($totalOperations > 0) {
            $this->info("✅ Sync completed successfully!");
            return 0;
        } else {
            $this->info("ℹ️  No files needed syncing");
            return 0;
        }
    }

    /**
     * Sync migrations from shared to tenant directory
     */
    private function syncMigrations(string $sourcePath, string $targetPath, array $skipList, array &$results): void
    {
        if (!is_dir($sourcePath)) {
            $this->warn("⚠️  Shared migrations directory not found: {$sourcePath}");
            return;
        }

        // Create tenant migrations directory if it doesn't exist
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
            $this->info("📁 Created directory: {$targetPath}");
        }

        $migrationFiles = glob($sourcePath . '/*.php');
        
        foreach ($migrationFiles as $sourceFile) {
            $filename = basename($sourceFile);
            $targetFile = $targetPath . '/' . $filename;
            
            // Check if file should be skipped
            $shouldSkip = false;
            foreach ($skipList as $skipPattern) {
                if (str_contains($filename, $skipPattern)) {
                    $shouldSkip = true;
                    break;
                }
            }
            
            if ($shouldSkip) {
                $this->line("  ⏭️  Skipped: {$filename}");
                $results['migrations_skipped']++;
                continue;
            }
            
            // Only copy if file doesn't exist or source is newer
            if (!file_exists($targetFile) || filemtime($sourceFile) > filemtime($targetFile)) {
                copy($sourceFile, $targetFile);
                $this->line("  ✅ Copied: {$filename}");
                $results['migrations_copied']++;
            } else {
                $this->line("  📄 Up to date: {$filename}");
            }
        }
    }

    /**
     * Sync seeders from shared to tenant directory
     */
    private function syncSeeders(string $sourcePath, string $targetPath, array $skipList, array &$results): void
    {
        if (!is_dir($sourcePath)) {
            $this->warn("⚠️  Shared seeders directory not found: {$sourcePath}");
            return;
        }

        // Create tenant seeders directory if it doesn't exist
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
            $this->info("📁 Created directory: {$targetPath}");
        }

        $seederFiles = glob($sourcePath . '/*.php');
        
        foreach ($seederFiles as $sourceFile) {
            $filename = basename($sourceFile);
            $targetFile = $targetPath . '/' . $filename;
            
            // Check if file should be skipped
            $shouldSkip = false;
            foreach ($skipList as $skipPattern) {
                if (str_contains($filename, $skipPattern)) {
                    $shouldSkip = true;
                    break;
                }
            }
            
            if ($shouldSkip) {
                $this->line("  ⏭️  Skipped: {$filename}");
                $results['seeders_skipped']++;
                continue;
            }
            
            // Only copy if file doesn't exist or source is newer
            if (!file_exists($targetFile) || filemtime($sourceFile) > filemtime($targetFile)) {
                copy($sourceFile, $targetFile);
                $this->line("  ✅ Copied: {$filename}");
                $results['seeders_copied']++;
            } else {
                $this->line("  📄 Up to date: {$filename}");
            }
        }
    }

    /**
     * Run operation for all tenants
     */
    private function runForAllTenants(string $operation): int
    {
        $status = $this->option('status');
        $tenants = Tenant::where('status', $status)->get();

        if ($tenants->isEmpty()) {
            $this->error("❌ No {$status} tenants found");
            return 1;
        }

        $this->info("🔄 Running '{$operation}' for {$tenants->count()} {$status} tenant(s)");
        
        if (!$this->option('force') && !$this->confirm("Continue?", true)) {
            return 0;
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($tenants as $tenant) {
            $this->info("📍 Processing: {$tenant->name}");
            
            try {
                $result = $this->runOperation($operation, $tenant);
                
                if ($result === 0) {
                    $results['success']++;
                    $this->info("  ✅ Success");
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed for {$tenant->name}";
                    $this->error("  ❌ Failed");
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error for {$tenant->name}: {$e->getMessage()}";
                $this->error("  ❌ Error: {$e->getMessage()}");
            }
            
            $this->newLine();
        }

        // Summary
        $this->info("📊 Operation Summary:");
        $this->info("   ✅ Successful: {$results['success']}");
        $this->info("   ❌ Failed: {$results['failed']}");

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error("❌ Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        }

        return $results['failed'] > 0 ? 1 : 0;
    }

    /**
     * Show help message
     */
    private function showHelp(array $operations): int
    {
        $this->error("❌ Unknown operation");
        $this->newLine();
        
        $this->info("📚 Available database operations:");
        $ops = [
            'migrate' => 'Run pending migrations',
            'migrate:fresh' => 'Drop all tables and re-run migrations', 
            'migrate:rollback' => 'Rollback migrations',
            'migrate:status' => 'Show migration status',
            'seed' => 'Run database seeders',
            'fresh-seed' => 'Fresh migrate + seed in one command',
            'reset' => 'Rollback all migrations',
            'refresh' => 'Rollback and re-run migrations',
        ];
        
        foreach ($ops as $cmd => $desc) {
            $this->info("  <fg=green>{$cmd}</fg=green> - {$desc}");
        }

        $this->newLine();
        $this->info("💡 Usage examples:");
        $this->info("  php artisan tenant:db migrate");
        $this->info("  php artisan tenant:db seed --class=UserSeeder");
        $this->info("  php artisan tenant:db migrate:fresh --seed");
        $this->info("  php artisan tenant:db migrate --all");
        $this->info("  php artisan tenant:db seed --tenant=tenant-uuid");
        
        return 1;
    }
}
