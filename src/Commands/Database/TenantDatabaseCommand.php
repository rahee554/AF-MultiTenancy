<?php

namespace ArtflowStudio\Tenancy\Commands\Database;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class TenantDatabaseCommand extends Command
{
    protected $signature = 'tenant:db
                            {operation? : Database operation (migrate, migrate:fresh, migrate:rollback, seed, migrate:status)}
                            {--tenant= : Tenant UUID or name}
                            {--domain= : Tenant domain}
                            {--database= : Tenant database name}
                            {--class= : Seeder class name for seeding}
                            {--step= : Number of steps to rollback}
                            {--force : Force operation without confirmation}
                            {--seed : Run seeders after migration}
                            {--all : Run operation for all active tenants}
                            {--status=active : Filter tenants by status}
                            {--pretend : Show what would be migrated without executing}
                            {--show-details : Show detailed migration/seeder output}
                            {--monitor : Monitor and compare database vs file system}
                            {--sync-status : Show sync status between shared and tenant directories}';

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
            'monitor' => 'Monitor database vs filesystem differences',
            'sync-status' => 'Show sync status between directories',
            'health-check' => 'Check tenant database health and integrity',
        ];

        // Handle special monitoring operations
        if ($this->option('monitor') || $operation === 'monitor') {
            return $this->runMonitor();
        }

        if ($this->option('sync-status') || $operation === 'sync-status') {
            return $this->runSyncStatus();
        }

        if ($operation === 'health-check') {
            return $this->runHealthCheck();
        }

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
        // Try domain first if provided
        if ($domain = $this->option('domain')) {
            $tenant = $this->findTenantByDomain($domain);
            if ($tenant) {
                $this->displaySelectedTenant($tenant);
                return $tenant;
            }
            $this->error("❌ Tenant not found for domain: {$domain}");
        }

        // Try database name if provided
        if ($database = $this->option('database')) {
            $tenant = $this->findTenantByDatabase($database);
            if ($tenant) {
                $this->displaySelectedTenant($tenant);
                return $tenant;
            }
            $this->error("❌ Tenant not found for database: {$database}");
        }

        // Try tenant identifier (UUID or name) if provided
        if ($tenantIdentifier = $this->option('tenant')) {
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
     * Find tenant by domain
     */
    private function findTenantByDomain(string $domain): ?Tenant
    {
        // Try exact match first
        $tenant = Tenant::whereHas('domains', function ($query) use ($domain) {
            $query->where('domain', $domain);
        })->first();

        if (!$tenant) {
            // Try partial match
            $tenant = Tenant::whereHas('domains', function ($query) use ($domain) {
                $query->where('domain', 'LIKE', "%{$domain}%");
            })->first();
        }

        return $tenant;
    }

    /**
     * Find tenant by database name
     */
    private function findTenantByDatabase(string $database): ?Tenant
    {
        // Search by database field or generated database name
        $tenant = Tenant::where('database', $database)->first();
        
        if (!$tenant) {
            // Try searching tenants and check their generated database names
            $tenants = Tenant::all();
            foreach ($tenants as $t) {
                if ($t->getDatabaseName() === $database) {
                    return $t;
                }
            }
        }

        return $tenant;
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

        // Show which migration files will be considered for this tenant
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        $migrationDir = base_path($tenantMigrationsPath);

        if (is_dir($migrationDir)) {
            $migrationFiles = glob($migrationDir . '/*.php');
            if (!empty($migrationFiles)) {
                $this->info('📁 Migration files found:');
                foreach ($migrationFiles as $file) {
                    $this->line('   - ' . basename($file));
                }
                $this->newLine();
            } else {
                $this->info("ℹ️  No migration files found in: {$tenantMigrationsPath}");
            }
        } else {
            $this->warn("⚠️  Migrations directory not found: {$migrationDir}");
        }

        tenancy()->initialize($tenant);

        // Capture before state for comparison
        $beforeMigrations = $this->getMigrationStatus($tenantMigrationsPath);

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--pretend' => $this->option('pretend'),
            '--path' => $tenantMigrationsPath,
        ]);

        $output = Artisan::output();
        
        if ($this->option('show-details')) {
            $this->line($output);
        } else {
            // Show only successful migrations
            $this->showMigrationSummary($output);
        }

        // Show what changed if not pretend mode
        if (!$this->option('pretend')) {
            $afterMigrations = $this->getMigrationStatus($tenantMigrationsPath);
            $this->showMigrationChanges($beforeMigrations, $afterMigrations);
        }

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
        $requestedClass = $this->option('class');

        // Prefer TenantDatabaseSeeder if present in tenant seeders folder
        $tenantSeedersPath = config('artflow-tenancy.seeders.tenant_seeders_path', 'database/seeders/tenant');
        $sharedSeedersPath = config('artflow-tenancy.seeders.shared_seeders_path', 'database/seeders');

        $tenantSeederFile = base_path($tenantSeedersPath . '/TenantDatabaseSeeder.php');
        $sharedSeederFile = base_path($sharedSeedersPath . '/TenantDatabaseSeeder.php');

        // Determine which seeder class/file to run.
        // Priority: requested class > tenant-specific TenantDatabaseSeeder > shared TenantDatabaseSeeder > fallback DatabaseSeeder
        $class = null;
        $seederFileToRequire = null;

        if ($requestedClass) {
            $class = $requestedClass;
        } elseif (file_exists($tenantSeederFile)) {
            $class = 'TenantDatabaseSeeder';
            $seederFileToRequire = $tenantSeederFile;
        } elseif (file_exists($sharedSeederFile)) {
            $class = 'TenantDatabaseSeeder';
            $seederFileToRequire = $sharedSeederFile;
        } else {
            // Fall back to DatabaseSeeder if TenantDatabaseSeeder not present
            $class = 'DatabaseSeeder';
        }

        $this->info("🌱 Running seeder '{$class}' for: {$tenant->name}");

        // Show which seeder file will be used (tenant-specific preferred)
        if ($seederFileToRequire) {
            // show relative path from base_path for readability
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $seederFileToRequire);
            $this->info("📁 Using seeder file: {$relativePath}");
        } else {
            $this->info("📁 Using seeder class: {$class}");
        }

        // If the chosen seeder file exists but the class is not yet declared, require it so db:seed can find the class.
        if ($seederFileToRequire && !class_exists($class)) {
            require_once $seederFileToRequire;
        }

        tenancy()->initialize($tenant);

        $params = ['--force' => true];
        if ($class !== 'DatabaseSeeder') {
            $params['--class'] = $class;
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
            'sync' => 'Sync migrations/seeders from shared to tenant directories',
            'monitor' => 'Monitor database vs filesystem differences',
            'sync-status' => 'Show sync status between directories',
            'health-check' => 'Check tenant database health and integrity',
        ];
        
        foreach ($ops as $cmd => $desc) {
            $this->info("  <fg=green>{$cmd}</fg=green> - {$desc}");
        }

        $this->newLine();
        $this->info("💡 Usage examples:");
        $this->info("  <fg=yellow>Basic operations:</fg=yellow>");
        $this->info("  php artisan tenant:db migrate");
        $this->info("  php artisan tenant:db migrate:fresh --seed");
        $this->info("  php artisan tenant:db seed --class=UserSeeder");
        
        $this->newLine();
        $this->info("  <fg=yellow>Tenant selection options:</fg=yellow>");
        $this->info("  php artisan tenant:db migrate --tenant=uuid-here");
        $this->info("  php artisan tenant:db migrate --domain=example.com");
        $this->info("  php artisan tenant:db migrate --database=tenant_db_name");
        
        $this->newLine();
        $this->info("  <fg=yellow>Bulk operations:</fg=yellow>");
        $this->info("  php artisan tenant:db migrate --all");
        $this->info("  php artisan tenant:db seed --all --status=active");
        
        $this->newLine();
        $this->info("  <fg=yellow>Monitoring & Status:</fg=yellow>");
        $this->info("  php artisan tenant:db monitor");
        $this->info("  php artisan tenant:db sync-status");
        $this->info("  php artisan tenant:db health-check");
        
        $this->newLine();
        $this->info("  <fg=yellow>Advanced options:</fg=yellow>");
        $this->info("  php artisan tenant:db migrate --show-details");
        $this->info("  php artisan tenant:db migrate:fresh --seed --force");
        $this->info("  php artisan tenant:db migrate --pretend");
        
        return 1;
    }

    /**
     * Get migration status for comparison - Enhanced version
     */
    private function getMigrationStatus(string $path): array
    {
        try {
            // Get all migration files from filesystem
            $migrationFiles = glob(base_path($path) . '/*.php');
            $availableMigrations = [];
            
            foreach ($migrationFiles as $file) {
                $filename = basename($file, '.php');
                $availableMigrations[] = $filename;
            }
            
            // Get ran migrations from database
            $ranMigrations = [];
            try {
                $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();
            } catch (\Exception $e) {
                // Migration table might not exist yet
            }
            
            return [
                'available' => $availableMigrations,
                'ran' => $ranMigrations,
                'pending' => array_diff($availableMigrations, $ranMigrations),
                'count_available' => count($availableMigrations),
                'count_ran' => count($ranMigrations),
                'count_pending' => count(array_diff($availableMigrations, $ranMigrations))
            ];
        } catch (\Exception $e) {
            return [
                'available' => [],
                'ran' => [],
                'pending' => [],
                'count_available' => 0,
                'count_ran' => 0,
                'count_pending' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Show enhanced migration summary with individual migration progress and timing
     */
    private function showMigrationSummary(string $output): void
    {
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);
        
        // Parse the Artisan output for migration information
        $lines = explode("\n", trim($output));
        $migratedFiles = [];
        $batchNumber = null;
        
        foreach ($lines as $line) {
            // Look for migrating pattern (in progress)
            if (preg_match('/Migrating: (.+)/', $line, $matches)) {
                $migrationName = trim($matches[1]);
                $this->line("⏳ " . Str::limit($migrationName, 60) . " ...");
                continue;
            }
            
            // Look for migrated pattern with timing
            if (preg_match('/Migrated:\s+(.+?)\s+\(([0-9.]+)([a-z]+)\)/', $line, $matches)) {
                $migrationName = trim($matches[1]);
                $duration = $matches[2];
                $unit = $matches[3];
                
                $dots = str_repeat('.', max(1, 65 - strlen($migrationName)));
                $this->info("✅ " . Str::limit($migrationName, 60) . " {$dots} {$duration}{$unit}");
                $migratedFiles[] = $migrationName;
                continue;
            }
            
            // Look for batch information
            if (preg_match('/Batch number: (\d+)/', $line, $matches)) {
                $batchNumber = $matches[1];
            }
            
            // Check for "Nothing to migrate" message
            if (str_contains($line, 'Nothing to migrate')) {
                $this->info("ℹ️  Nothing to migrate - all migrations are up to date");
                return;
            }
        }
        
        $endTime = microtime(true);
        $memoryEnd = memory_get_usage(true);
        $totalTime = round($endTime - $startTime, 2);
        $memoryUsed = round(($memoryEnd - $memoryStart) / 1024 / 1024, 2);
        
        if (!empty($migratedFiles)) {
            $this->newLine();
            $this->info("Migration completed in {$totalTime}s (Memory: {$memoryUsed}MB)");
            $this->info("� Total: " . count($migratedFiles) . " migration(s)" . 
                       ($batchNumber ? ", Batch: {$batchNumber}" : "") . 
                       ", Success: " . count($migratedFiles) . ", Failed: 0");
        }
    }

    private function showMigrationChanges(array $before, array $after): void
    {
        $newlyRan = array();
        if (isset($after['ran']) && isset($before['ran'])) {
            $newlyRan = array_diff($after['ran'], $before['ran']);
        }
        
        $stillPending = isset($after['pending']) ? $after['pending'] : array();
        
        if (!empty($newlyRan)) {
            $this->newLine();
            $this->info('Newly migrated (' . count($newlyRan) . '):');
            foreach ($newlyRan as $migration) {
                $shortName = Str::limit(basename($migration, '.php'), 60);
                $this->line('   - ' . $shortName);
            }
        }
        
        if (!empty($stillPending) && count($stillPending) > 0) {
            $this->newLine();
            $this->warn('Still pending (' . count($stillPending) . '):');
            foreach (array_slice($stillPending, 0, 5) as $migration) {
                $shortName = Str::limit(basename($migration, '.php'), 60);
                $this->line('   - ' . $shortName);
            }
            if (count($stillPending) > 5) {
                $this->line('   ... and ' . (count($stillPending) - 5) . ' more');
            }
        }
        
        if (empty($newlyRan) && empty($stillPending)) {
            $this->info('No migration state changes detected');
        }
        
        $beforeCount = isset($before['count_ran']) ? $before['count_ran'] : 0;
        $afterCount = isset($after['count_ran']) ? $after['count_ran'] : 0;
        
        if ($afterCount > $beforeCount) {
            $this->newLine();
            $this->info('Migration Progress: ' . $beforeCount . ' -> ' . $afterCount . ' (+' . ($afterCount - $beforeCount) . ')');
        }
    }

    /**
     * Monitor database vs filesystem differences
     */
    private function runMonitor(): int
    {
        $this->info("🔍 Monitoring tenant databases vs filesystem");
        $this->newLine();

        $tenants = Tenant::where('status', 'active')->get();
        
        if ($tenants->isEmpty()) {
            $this->error("❌ No active tenants found");
            return 1;
        }

        $issues = [];
        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
        $tenantSeedersPath = config('artflow-tenancy.seeders.tenant_seeders_path', 'database/seeders/tenant');

        // Get available files
        $availableMigrations = $this->getAvailableFiles($tenantMigrationsPath, '*.php');
        $availableSeeders = $this->getAvailableFiles($tenantSeedersPath, '*.php');

        foreach ($tenants as $tenant) {
            $this->info("🔍 Checking: {$tenant->name}");
            
            try {
                tenancy()->initialize($tenant);
                
                // Check migrations
                $runMigrations = $this->getRunMigrations();
                $migrationIssues = $this->compareFiles($availableMigrations, $runMigrations, 'migrations');
                
                // Check seeders (approximate - we can't easily get exact seeder status)
                $seederIssues = $this->checkSeederStatus($tenant, $availableSeeders);
                
                tenancy()->end();
                
                if (!empty($migrationIssues) || !empty($seederIssues)) {
                    $issues[$tenant->name] = [
                        'migrations' => $migrationIssues,
                        'seeders' => $seederIssues
                    ];
                }
                
                $this->line("  ✅ Checked");
                
            } catch (\Exception $e) {
                $issues[$tenant->name] = [
                    'error' => $e->getMessage()
                ];
                $this->line("  ❌ Error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->displayMonitorResults($issues);
        
        return empty($issues) ? 0 : 1;
    }

    /**
     * Show sync status between directories
     */
    private function runSyncStatus(): int
    {
        $this->info("📊 Sync Status Between Shared and Tenant Directories");
        $this->newLine();

        $config = config('artflow-tenancy');
        
        $sharedMigrationsPath = base_path($config['migrations']['shared_migrations_path']);
        $tenantMigrationsPath = base_path($config['migrations']['tenant_migrations_path']);
        $sharedSeedersPath = base_path($config['seeders']['shared_seeders_path']);
        $tenantSeedersPath = base_path($config['seeders']['tenant_seeders_path']);

        // Check migrations sync status
        $this->info("📁 Migration Sync Status:");
        $migrationStatus = $this->comparDirectories($sharedMigrationsPath, $tenantMigrationsPath, 
            $config['migrations']['skip_migrations'] ?? []);
        $this->displaySyncStatus($migrationStatus, 'Migrations');

        $this->newLine();

        // Check seeders sync status
        $this->info("🌱 Seeder Sync Status:");
        $seederStatus = $this->comparDirectories($sharedSeedersPath, $tenantSeedersPath,
            $config['seeders']['skip_seeders'] ?? []);
        $this->displaySyncStatus($seederStatus, 'Seeders');

        return 0;
    }

    /**
     * Run health check for tenant databases
     */
    private function runHealthCheck(): int
    {
        $this->info("🏥 Tenant Database Health Check");
        $this->newLine();

        $tenants = Tenant::where('status', 'active')->get();
        $healthReport = [];

        foreach ($tenants as $tenant) {
            $this->info("🔍 Checking: {$tenant->name}");
            
            $health = [
                'connection' => false,
                'migrations' => 'unknown',
                'tables' => 0,
                'size' => 'unknown',
                'issues' => []
            ];

            try {
                tenancy()->initialize($tenant);
                
                // Test connection
                DB::connection()->getPdo();
                $health['connection'] = true;
                
                // Check migration status
                $health['migrations'] = $this->checkMigrationHealth();
                
                // Count tables
                $tables = DB::select("SHOW TABLES");
                $health['tables'] = count($tables);
                
                // Get database size
                $dbName = $tenant->getDatabaseName();
                $sizeResult = DB::select("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [$dbName]);
                
                $health['size'] = ($sizeResult[0]->size_mb ?? 0) . ' MB';
                
                tenancy()->end();
                
                $this->line("  ✅ Healthy");
                
            } catch (\Exception $e) {
                $health['issues'][] = $e->getMessage();
                $this->line("  ❌ Issues found");
            }
            
            $healthReport[$tenant->name] = $health;
        }

        $this->newLine();
        $this->displayHealthReport($healthReport);
        
        return 0;
    }

    /**
     * Get available files in a directory
     */
    private function getAvailableFiles(string $path, string $pattern): array
    {
        if (!is_dir($path)) {
            return [];
        }
        
        $files = glob($path . '/' . $pattern);
        return array_map('basename', $files);
    }

    /**
     * Get run migrations from database
     */
    private function getRunMigrations(): array
    {
        try {
            $migrations = DB::table('migrations')->pluck('migration')->toArray();
            return array_map(function ($migration) {
                return $migration . '.php';
            }, $migrations);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Compare available files vs run files
     */
    private function compareFiles(array $available, array $run, string $type): array
    {
        $issues = [];
        
        // Files that exist but haven't been run
        $pending = array_diff($available, $run);
        if (!empty($pending)) {
            $issues['pending'] = $pending;
        }
        
        // Files that were run but no longer exist
        $missing = array_diff($run, $available);
        if (!empty($missing)) {
            $issues['missing'] = $missing;
        }
        
        return $issues;
    }

    /**
     * Check seeder status for a tenant
     */
    private function checkSeederStatus(Tenant $tenant, array $availableSeeders): array
    {
        // This is approximate since we can't easily track seeder execution
        // We'll check if common seeder tables exist and have data
        $issues = [];
        
        try {
            $tables = DB::select("SHOW TABLES");
            $tableCount = count($tables);
            
            if ($tableCount === 0 && !empty($availableSeeders)) {
                $issues['no_data'] = "Database is empty but " . count($availableSeeders) . " seeders are available";
            }
            
            // Could add more sophisticated seeder checking here
            
        } catch (\Exception $e) {
            $issues['error'] = $e->getMessage();
        }
        
        return $issues;
    }

    /**
     * Compare two directories
     */
    private function comparDirectories(string $sourcePath, string $targetPath, array $skipList): array
    {
        $status = [
            'source_files' => 0,
            'target_files' => 0,
            'sync_needed' => [],
            'up_to_date' => [],
            'missing_in_target' => [],
            'extra_in_target' => []
        ];

        if (!is_dir($sourcePath)) {
            $status['error'] = "Source directory not found: {$sourcePath}";
            return $status;
        }

        if (!is_dir($targetPath)) {
            $status['error'] = "Target directory not found: {$targetPath}";
            return $status;
        }

        $sourceFiles = array_diff(scandir($sourcePath), ['.', '..']);
        $targetFiles = array_diff(scandir($targetPath), ['.', '..']);

        $sourceFiles = array_filter($sourceFiles, fn($file) => pathinfo($file, PATHINFO_EXTENSION) === 'php');
        $targetFiles = array_filter($targetFiles, fn($file) => pathinfo($file, PATHINFO_EXTENSION) === 'php');

        $status['source_files'] = count($sourceFiles);
        $status['target_files'] = count($targetFiles);

        foreach ($sourceFiles as $file) {
            // Check if should be skipped
            $shouldSkip = false;
            foreach ($skipList as $skipPattern) {
                if (str_contains($file, $skipPattern)) {
                    $shouldSkip = true;
                    break;
                }
            }
            
            if ($shouldSkip) {
                continue;
            }

            $sourceFile = $sourcePath . '/' . $file;
            $targetFile = $targetPath . '/' . $file;

            if (!file_exists($targetFile)) {
                $status['missing_in_target'][] = $file;
            } elseif (filemtime($sourceFile) > filemtime($targetFile)) {
                $status['sync_needed'][] = $file;
            } else {
                $status['up_to_date'][] = $file;
            }
        }

        // Files in target but not in source
        $status['extra_in_target'] = array_diff($targetFiles, $sourceFiles);

        return $status;
    }

    /**
     * Display sync status results
     */
    private function displaySyncStatus(array $status, string $type): void
    {
        if (isset($status['error'])) {
            $this->error("❌ {$status['error']}");
            return;
        }

        $this->info("📊 {$type} Status:");
        $this->info("   📁 Source files: {$status['source_files']}");
        $this->info("   📂 Target files: {$status['target_files']}");

        if (!empty($status['up_to_date'])) {
            $this->info("   ✅ Up to date: " . count($status['up_to_date']));
        }

        if (!empty($status['sync_needed'])) {
            $this->warn("   🔄 Need sync: " . count($status['sync_needed']));
            foreach ($status['sync_needed'] as $file) {
                $this->line("      - {$file}");
            }
        }

        if (!empty($status['missing_in_target'])) {
            $this->error("   ❌ Missing in target: " . count($status['missing_in_target']));
            foreach ($status['missing_in_target'] as $file) {
                $this->line("      - {$file}");
            }
        }

        if (!empty($status['extra_in_target'])) {
            $this->warn("   ⚠️  Extra in target: " . count($status['extra_in_target']));
            foreach ($status['extra_in_target'] as $file) {
                $this->line("      - {$file}");
            }
        }
    }

    /**
     * Display monitor results
     */
    private function displayMonitorResults(array $issues): void
    {
        if (empty($issues)) {
            $this->info("✅ All tenant databases are in sync with filesystem!");
            return;
        }

        $this->warn("⚠️  Found issues in " . count($issues) . " tenant(s):");
        
        foreach ($issues as $tenantName => $tenantIssues) {
            $this->newLine();
            $this->info("🏢 Tenant: {$tenantName}");
            
            if (isset($tenantIssues['error'])) {
                $this->error("   ❌ Error: {$tenantIssues['error']}");
                continue;
            }
            
            if (!empty($tenantIssues['migrations'])) {
                $this->warn("   📁 Migration Issues:");
                foreach ($tenantIssues['migrations'] as $type => $files) {
                    $this->line("      {$type}: " . implode(', ', $files));
                }
            }
            
            if (!empty($tenantIssues['seeders'])) {
                $this->warn("   🌱 Seeder Issues:");
                foreach ($tenantIssues['seeders'] as $type => $issue) {
                    $this->line("      {$type}: {$issue}");
                }
            }
        }
    }

    /**
     * Check migration health for current tenant
     */
    private function checkMigrationHealth(): string
    {
        try {
            $pending = DB::table('migrations')->count();
            $total = count(glob(base_path(config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant') . '/*.php')));
            
            if ($pending === 0) {
                return 'no_migrations';
            } elseif ($pending < $total) {
                return 'pending_migrations';
            } else {
                return 'up_to_date';
            }
        } catch (\Exception $e) {
            return 'error';
        }
    }

    /**
     * Display health report
     */
    private function displayHealthReport(array $healthReport): void
    {
        $this->info("🏥 Health Report Summary:");
        
        $headers = ['Tenant', 'Connection', 'Migrations', 'Tables', 'Size', 'Issues'];
        $rows = [];
        
        foreach ($healthReport as $tenantName => $health) {
            $connectionStatus = $health['connection'] ? '✅ OK' : '❌ Failed';
            $migrationStatus = match($health['migrations']) {
                'up_to_date' => '✅ Current',
                'pending_migrations' => '⚠️ Pending',
                'no_migrations' => '🔄 None',
                'error' => '❌ Error',
                default => '❓ Unknown'
            };
            
            $issuesText = empty($health['issues']) ? '✅ None' : '❌ ' . count($health['issues']);
            
            $rows[] = [
                Str::limit($tenantName, 20),
                $connectionStatus,
                $migrationStatus,
                $health['tables'],
                $health['size'],
                $issuesText
            ];
        }
        
        $this->table($headers, $rows);
        
        // Show detailed issues if any
        foreach ($healthReport as $tenantName => $health) {
            if (!empty($health['issues'])) {
                $this->newLine();
                $this->error("❌ Issues for {$tenantName}:");
                foreach ($health['issues'] as $issue) {
                    $this->line("   • {$issue}");
                }
            }
        }
    }
}
