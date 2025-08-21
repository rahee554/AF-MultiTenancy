<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use ArtflowStudio\Tenancy\Models\Tenant;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:health {--detailed : Show detailed health information}';

    /**
     * The console command description.
     */
    protected $description = 'Check the health status of the tenancy system';

    /**
     * Categorized issue buckets for final summary
     * @var array<int,string>
     */
    protected array $missingDatabases = [];

    /** @var array<int,string> */
    protected array $migrationErrors = [];

    /** @var array<int,string> */
    protected array $seederErrors = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Running Tenancy Health Check...');
        $this->newLine();

        $issues = [];

        // Check central database connection
        $this->checkCentralDatabase($issues);
        
        // Check tenant databases
        $this->checkTenantDatabases($issues);
        
        // Check configuration
        $this->checkConfiguration($issues);
        
        // Check stancl/tenancy integration
        $this->checkStanclIntegration($issues);

        // Display results
        $this->displayResults($issues);

        return empty($issues) ? 0 : 1;
    }

    /**
     * Check central database connection
     */
    protected function checkCentralDatabase(array &$issues): void
    {
        $this->info('📊 Checking Central Database...');
        
        try {
            DB::connection()->getPdo();
            $this->line('   ✅ Central database connection: OK');
            
            // Check if tenant tables exist
            $tables = ['tenants', 'domains'];
            foreach ($tables as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    $issues[] = "Missing required table: {$table}";
                    $this->error("   ❌ Missing table: {$table}");
                } else {
                    $this->line("   ✅ Table '{$table}': OK");
                }
            }
        } catch (\Exception $e) {
            $issues[] = "Central database connection failed: " . $e->getMessage();
            $this->error('   ❌ Central database connection: FAILED');
        }
        
        $this->newLine();
    }

    /**
     * Check tenant databases
     */
    protected function checkTenantDatabases(array &$issues): void
    {
        $this->info('🏢 Checking Tenant Databases...');
        
        try {
            $tenants = Tenant::with('domains')->get();
            $this->line("   Found {$tenants->count()} tenants");
            $workingCount = 0;
            foreach ($tenants as $tenant) {
                $tenantIssues = [];
                $printedInIteration = false;

                try {
                    // Run checks inside tenant context
                    $tenant->run(function () use ($tenant, &$tenantIssues) {
                        // Verify DB connection
                        DB::connection()->getPdo();

                        // Current DB name (MySQL/Postgres compatible query)
                        try {
                            $currentDbRow = DB::selectOne('select database() as db');
                            $currentDb = $currentDbRow->db ?? null;
                        } catch (\Throwable $e) {
                            // Fallback for SQLite or other drivers
                            $currentDb = DB::getDatabaseName() ?? null;
                        }

                        $expectedDb = method_exists($tenant, 'getDatabaseName') ? $tenant->getDatabaseName() : null;
                        if ($expectedDb && $currentDb && $currentDb !== $expectedDb) {
                            $tenantIssues[] = "Connected database mismatch (connected: {$currentDb}, expected: {$expectedDb})";
                        }

                        // Check migrations table and pending migrations
                        $migrationsTableExists = Schema::hasTable('migrations');

                        $tenantMigrationsPath = config('artflow-tenancy.migrations.tenant_migrations_path', 'database/migrations/tenant');
                        $migrationFiles = glob(base_path($tenantMigrationsPath) . '/*.php') ?: [];
                        $migrationFilesCount = count($migrationFiles);

                        if (!$migrationsTableExists && $migrationFilesCount > 0) {
                            $tenantIssues[] = "Migrations table missing but {$migrationFilesCount} migration file(s) present";
                        } elseif ($migrationsTableExists) {
                            // Run migrate:status within tenant context and parse output for pending
                            try {
                                Artisan::call('migrate:status', ['--path' => $tenantMigrationsPath]);
                                $statusOutput = Artisan::output();
                                // Count lines with 'No' in Ran? column
                                $pending = 0;
                                foreach (explode("\n", $statusOutput) as $line) {
                                    // typical line contains '| No |' for pending
                                    if (str_contains($line, '| No |') || preg_match('/^\|\s*\w+\s*\|\s*No\s*\|/i', $line)) {
                                        $pending++;
                                    }
                                }
                                if ($pending > 0) {
                                    $tenantIssues[] = "{$pending} pending migration(s)";
                                }
                            } catch (\Throwable $e) {
                                $tenantIssues[] = "Failed to determine migration status: {$e->getMessage()}";
                            }
                        }

                        // Check for seeders and basic data presence (users table as heuristic)
                        $tenantSeedersPath = config('artflow-tenancy.seeders.tenant_seeders_path', 'database/seeders/tenant');
                        $sharedSeedersPath = config('artflow-tenancy.seeders.shared_seeders_path', 'database/seeders');
                        $tenantSeederExists = file_exists(base_path($tenantSeedersPath . '/TenantDatabaseSeeder.php'));
                        $sharedSeederExists = file_exists(base_path($sharedSeedersPath . '/TenantDatabaseSeeder.php'));

                        if (!$tenantSeederExists && !$sharedSeederExists) {
                            // If no tenant seeder present, but migrations exist, warn if common data missing
                            if (Schema::hasTable('users')) {
                                $userCount = DB::table('users')->count();
                                if ($userCount === 0) {
                                    $tenantIssues[] = 'No users found (seeders missing or not run)';
                                }
                            }
                        }
                    });

                    if (empty($tenantIssues)) {
                        $workingCount++;
                        if ($this->option('detailed')) {
                            $domain = $tenant->domains->first()?->domain ?? 'No domain';
                            $this->line("   ✅ {$tenant->id} ({$domain}): OK");
                            $printedInIteration = true;
                        }
                    } else {
                        // Build a friendly tenant label and actionable suggestions
                        $domain = $tenant->domains->first()?->domain ?? 'No domain';
                        $tenantLabel = "{$tenant->id} - {$tenant->name} ({$domain})";

                        $combined = implode('; ', $tenantIssues);

                        // Add suggested fixes based on common issues
                        $suggestions = [];
                        if (str_contains($combined, 'Migrations table missing') || str_contains($combined, 'pending migration')) {
                            $suggestions[] = "Run migrations: php artisan tenant:db migrate --tenant={$tenant->id}";
                        }
                        if (str_contains($combined, 'No users found')) {
                            $suggestions[] = "Run seeders: php artisan tenant:db seed --tenant={$tenant->id}";
                        }
                        if (str_contains($combined, 'Connected database mismatch') || str_contains($combined, 'Database')) {
                            $suggestions[] = "Verify tenant DB name/provisioning and update tenant record or create the DB";
                        }

                        $suggestionText = empty($suggestions) ? '' : implode(' | ', $suggestions);

                        // Add to issues list (compact)
                        $issues[] = "{$tenant->id} - {$tenant->name}: {$combined}";

                        // Categorize with structured entries for table output
                        $entry = [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'domain' => $domain,
                            'issue' => $combined,
                            'suggestion' => $suggestionText,
                        ];

                        if (str_contains($combined, 'Database')) {
                            $this->missingDatabases[] = $entry;
                        }
                        if (str_contains($combined, 'Migrations') || str_contains($combined, 'pending migration')) {
                            $this->migrationErrors[] = $entry;
                        }
                        if (str_contains($combined, 'No users found') || str_contains($combined, 'seed')) {
                            $this->seederErrors[] = $entry;
                        }

                        // Print compact per-tenant error and suggestion
                        $this->error("   ❌ {$tenant->id} - {$tenant->name} ({$domain}): {$combined}");
                        if ($suggestionText) {
                            $this->line("      → {$suggestionText}");
                        }
                        $printedInIteration = true;
                    }
                } catch (\Exception $e) {
                    $issues[] = "Tenant {$tenant->id} database issue: " . $e->getMessage();
                    $this->error("   ❌ {$tenant->id}: {$e->getMessage()}");
                    $printedInIteration = true;
                }

                if ($printedInIteration) {
                    $this->newLine();
                }
            }

            $this->line("   ✅ Working tenant databases: {$workingCount}/{$tenants->count()}");
        } catch (\Exception $e) {
            $issues[] = "Failed to check tenant databases: " . $e->getMessage();
            $this->error('   ❌ Tenant database check: FAILED');
        }
        
        $this->newLine();
    }

    /**
     * Check configuration
     */
    protected function checkConfiguration(array &$issues): void
    {
        $this->info('⚙️  Checking Configuration...');
        
        // Check if config files exist
        $configs = [
            'artflow-tenancy' => config('artflow-tenancy'),
            'tenancy' => config('tenancy'),
        ];
        
        foreach ($configs as $name => $config) {
            if ($config) {
                $this->line("   ✅ Config '{$name}': OK");
            } else {
                $issues[] = "Missing configuration: {$name}";
                $this->error("   ❌ Config '{$name}': MISSING");
            }
        }
        
        // Check API key
        $apiKey = config('artflow-tenancy.api.api_key');
        if ($apiKey) {
            $this->line('   ✅ API key: Configured');
        } else {
            $issues[] = "API key not configured";
            $this->error('   ❌ API key: NOT CONFIGURED');
        }
        
        $this->newLine();
    }

    /**
     * Check stancl/tenancy integration
     */
    protected function checkStanclIntegration(array &$issues): void
    {
        $this->info('🔗 Checking stancl/tenancy Integration...');
        
        try {
            // Check if stancl classes are available
            $classes = [
                \Stancl\Tenancy\Database\Models\Tenant::class,
                \Stancl\Tenancy\Database\Models\Domain::class,
                \Stancl\Tenancy\Contracts\TenantDatabaseManager::class,
            ];
            
            foreach ($classes as $class) {
                $exists = false;
                if (str_contains($class, 'Contracts\\')) {
                    $exists = interface_exists($class);
                } else {
                    $exists = class_exists($class);
                }
                
                if ($exists) {
                    $shortName = basename(str_replace('\\', '/', $class));
                    $this->line("   ✅ {$shortName}: Available");
                } else {
                    $issues[] = "Missing stancl class: {$class}";
                    $this->error("   ❌ {$class}: MISSING");
                }
            }
        } catch (\Exception $e) {
            $issues[] = "stancl/tenancy integration check failed: " . $e->getMessage();
            $this->error('   ❌ Integration check: FAILED');
        }
        
        $this->newLine();
    }

    /**
     * Display health check results
     */
    protected function displayResults(array $issues): void
    {
        if (empty($issues)) {
            $this->info('🎉 All health checks passed! Tenancy system is healthy.');
            return;
        }

        $this->error('⚠️  Health check found ' . count($issues) . ' issue(s):');

        // Print categorized summary first
        if (!empty($this->missingDatabases) || !empty($this->migrationErrors) || !empty($this->seederErrors)) {
            $this->line('\n� Categorized tenant issues:');

            // Helper to print a small table
            $printTable = function (string $title, array $entries) {
                $this->line("\n{$title} (" . count($entries) . "):");
                $headers = ['UUID', 'Name', 'Domain', 'Issue', 'Suggestion'];
                $rows = array_map(fn($e) => [
                    substr($e['id'], 0, 8) . '...',
                    Str::limit($e['name'], 24),
                    Str::limit($e['domain'] ?? 'No domain', 20),
                    Str::limit($e['issue'], 60),
                    Str::limit($e['suggestion'], 60),
                ], $entries);

                $this->table($headers, $rows);
            };

            if (!empty($this->missingDatabases)) {
                $printTable('🔴 Missing Databases', $this->missingDatabases);
            }

            if (!empty($this->migrationErrors)) {
                $printTable('🟠 Migration errors / pending migrations', $this->migrationErrors);
            }

            if (!empty($this->seederErrors)) {
                $printTable('🟡 Seeder issues', $this->seederErrors);
            }
        }

        // Then print full list
        $this->line('\nFull issue list:');
        foreach ($issues as $issue) {
            $this->line("   • {$issue}");
        }

        $this->newLine();
        $this->warn('Please address these issues to ensure proper tenancy functionality.');
    }
}
