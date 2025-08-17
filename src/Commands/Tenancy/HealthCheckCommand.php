<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
            
            if ($this->option('detailed')) {
                foreach ($tenants as $tenant) {
                    try {
                        $tenant->run(function () {
                            DB::connection()->getPdo();
                        });
                        $domain = $tenant->domains->first()?->domain ?? 'No domain';
                        $this->line("   ✅ {$tenant->id} ({$domain}): OK");
                    } catch (\Exception $e) {
                        $issues[] = "Tenant {$tenant->id} database issue: " . $e->getMessage();
                        $this->error("   ❌ {$tenant->id}: FAILED");
                    }
                }
            } else {
                $workingCount = 0;
                foreach ($tenants as $tenant) {
                    try {
                        $tenant->run(function () {
                            DB::connection()->getPdo();
                        });
                        $workingCount++;
                    } catch (\Exception $e) {
                        $issues[] = "Tenant {$tenant->id} database issue";
                    }
                }
                $this->line("   ✅ Working tenant databases: {$workingCount}/{$tenants->count()}");
            }
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
        } else {
            $this->error('⚠️  Health check found ' . count($issues) . ' issue(s):');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
            $this->warn('Please address these issues to ensure proper tenancy functionality.');
        }
    }
}
