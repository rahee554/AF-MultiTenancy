<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;

class MasterTestCommand extends Command
{
    protected $signature = 'tenancy:test-all 
                            {--auto : Run all tests automatically without interaction}
                            {--quick : Run quick tests only}
                            {--detailed : Show detailed output}';

    protected $description = 'Interactive master test command for all tenancy system tests';

    private array $testSuite = [];
    
    public function handle(): int
    {
        $this->initializeTestSuite();
        
        $this->info('🧪 Tenancy Master Test Suite');
        $this->info('🚀 Comprehensive testing system for multi-tenant application');
        $this->newLine();

        if ($this->option('auto')) {
            return $this->runAllTests();
        }

        return $this->showInteractiveMenu();
    }

    private function initializeTestSuite(): void
    {
        $this->testSuite = [
            'all' => [
                'name' => 'Run All Tests',
                'description' => 'Execute complete test suite (may take several minutes)',
                'commands' => ['health', 'system', 'performance', 'concurrency', 'isolation', 'database', 'diagnostics', 'connection-pool']
            ],
            'health' => [
                'name' => 'Health Check',
                'description' => 'System health and configuration verification',
                'command' => 'tenancy:health-check',
                'arguments' => [],
                'options' => ['--detailed']
            ],
            'system' => [
                'name' => 'System Tests',
                'description' => 'Core system functionality and middleware',
                'command' => 'tenancy:test-system',
                'arguments' => [],
                'options' => ['--progress']
            ],
            'performance' => [
                'name' => 'Performance Tests',
                'description' => 'Database isolation and performance validation',
                'command' => 'tenancy:test-performance',
                'arguments' => [],
                'options' => []
            ],
            'concurrency' => [
                'name' => 'Concurrency Tests',
                'description' => 'Multi-tenant concurrent operations',
                'command' => 'tenancy:test-performance',
                'arguments' => [],
                'options' => ['--concurrent-users=50', '--operations=500']
            ],
            'isolation' => [
                'name' => 'Isolation Tests',
                'description' => 'Tenant data isolation verification',
                'command' => 'tenancy:test-isolation',
                'arguments' => [],
                'options' => []
            ],
            'comprehensive' => [
                'name' => 'Comprehensive Tests',
                'description' => 'Full tenancy system validation',
                'command' => 'tenancy:test',
                'arguments' => [],
                'options' => ['--detailed']
            ],
            'database' => [
                'name' => 'Database Tests',
                'description' => 'Database privileges and configuration validation',
                'command' => 'tenant:check-privileges',
                'arguments' => [],
                'options' => ['--test-root']
            ],
            'diagnostics' => [
                'name' => 'Performance Diagnostics',
                'description' => 'Comprehensive performance and configuration diagnostics',
                'command' => 'tenancy:diagnose-performance',
                'arguments' => [],
                'options' => ['--check-mysql', '--detailed']
            ],
            'connection-pool' => [
                'name' => 'Connection Pool Tests',
                'description' => 'Test multi-tenant connection pooling system',
                'command' => 'tenancy:connection-pool',
                'arguments' => ['action' => 'test'],
                'options' => []
            ],
            'stress' => [
                'name' => 'Stress Tests',
                'description' => 'High-load stress testing (WARNING: Resource intensive)',
                'command' => 'tenancy:stress-test',
                'arguments' => [],
                'options' => ['--users=100', '--duration=300']
            ],
            'backup' => [
                'name' => 'Backup Tests',
                'description' => 'Backup and restore functionality',
                'command' => 'tenant:backup-manager',
                'arguments' => [],
                'options' => []
            ],
            'redis' => [
                'name' => 'Redis Tests',
                'description' => 'Redis caching and tenant-scoped operations',
                'command' => 'tenancy:test-redis',
                'arguments' => [],
                'options' => []
            ],
            'middleware' => [
                'name' => 'Middleware Tests',
                'description' => 'Tenant middleware and context switching',
                'command' => 'af-tenancy:test-middleware',
                'arguments' => [],
                'options' => []
            ],
            'tenant-management' => [
                'name' => 'Tenant Management',
                'description' => 'Test tenant creation and deletion systems',
                'command' => 'tenancy:test-tenants',
                'arguments' => ['action' => 'stats'],
                'options' => []
            ],
            'create-tenants' => [
                'name' => 'Create Test Tenants',
                'description' => 'Generate test tenants for development',
                'command' => 'tenancy:test-tenants',
                'arguments' => ['action' => 'create'],
                'options' => ['--count=5', '--migrate']
            ]
        ];
    }

    private function showInteractiveMenu(): int
    {
        while (true) {
            $this->displayMenu();
            
            $testKeys = array_keys($this->testSuite);
            $testLabels = array_map(function($key) {
                return $this->testSuite[$key]['name'];
            }, $testKeys);

            $selectedLabel = $this->choice('Select a test to run', array_merge($testLabels, ['Exit']));
            
            if ($selectedLabel === 'Exit') {
                $this->info('👋 Goodbye!');
                return 0;
            }

            $selectedKey = $testKeys[array_search($selectedLabel, $testLabels)];
            
            if ($selectedKey === 'all') {
                $this->runAllTests();
            } else {
                $this->runSingleTest($selectedKey);
            }

            $this->newLine();
            if (!$this->confirm('Continue with another test?', true)) {
                break;
            }
        }

        return 0;
    }

    private function displayMenu(): void
    {
        $this->newLine();
        $this->info('📋 Available Tests:');
        $this->newLine();

        $tenantCount = Tenant::count();
        $this->line("📊 Current tenant count: <fg=green>{$tenantCount}</>");
        $this->newLine();

        foreach ($this->testSuite as $key => $test) {
            $icon = match($key) {
                'all' => '🚀',
                'health' => '🏥',
                'system' => '⚙️',
                'performance' => '📈',
                'concurrency' => '🔄',
                'isolation' => '🔒',
                'comprehensive' => '🔍',
                'database' => '🗄️',
                'diagnostics' => '🔧',
                'connection-pool' => '🔗',
                'stress' => '💪',
                'backup' => '💾',
                'redis' => '🔴',
                'middleware' => '🛡️',
                'tenant-management' => '👥',
                'create-tenants' => '🏗️',
                default => '🧪'
            };

            $this->line("  {$icon} <fg=cyan>{$test['name']}</> - {$test['description']}");
        }

        $this->newLine();
    }

    private function runAllTests(): int
    {
        $this->info('🚀 Running Complete Test Suite...');
        $this->newLine();

        // Get all available tests except 'all' and 'create-tenants' (which is for setup)
        $tests = array_keys(array_filter($this->testSuite, function($test, $key) {
            return $key !== 'all' && $key !== 'create-tenants';
        }, ARRAY_FILTER_USE_BOTH));
        
        $results = [];

        $progressBar = $this->output->createProgressBar(count($tests));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->start();

        foreach ($tests as $testKey) {
            $progressBar->setMessage("Running {$this->testSuite[$testKey]['name']}...");
            
            $result = $this->runSingleTest($testKey, false);
            $results[$testKey] = $result;
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Display comprehensive results
        $this->displayTestResults($results);

        return $this->allTestsPassed($results) ? 0 : 1;
    }

    private function runSingleTest(string $testKey, bool $interactive = true): int
    {
        if (!isset($this->testSuite[$testKey])) {
            $this->error("❌ Unknown test: {$testKey}");
            return 1;
        }

        $test = $this->testSuite[$testKey];
        
        if ($interactive) {
            $this->info("🧪 Running: {$test['name']}");
            $this->line("📝 {$test['description']}");
            $this->newLine();
        }

        try {
            $arguments = $test['arguments'] ?? [];
            $options = $test['options'] ?? [];
            
            if ($this->option('detailed')) {
                $options[] = '--detailed';
            }

            if ($this->option('quick') && in_array('--duration=300', $options)) {
                // Replace with shorter duration for quick tests
                $options = array_filter($options, fn($opt) => !str_starts_with($opt, '--duration'));
                $options[] = '--duration=60';
            }

            // Build the final parameters array for Laravel call
            $params = $this->buildCallParameters($arguments, $options);

            if ($interactive) {
                $this->info("🧪 Running: {$test['name']}");
                $this->line("📝 {$test['description']}");
                $this->newLine();
                
                // Show the actual command output when running interactively
                $exitCode = $this->call($test['command'], $params);
                
                $this->newLine();
                if ($exitCode === 0) {
                    $this->info("✅ {$test['name']} completed successfully");
                } else {
                    $this->error("❌ {$test['name']} failed with exit code {$exitCode}");
                }
            } else {
                // When running in batch mode (--auto), use callSilent for cleaner output
                $exitCode = $this->callSilent($test['command'], $params);
            }

            return $exitCode;

        } catch (\Exception $e) {
            if ($interactive) {
                $this->error("❌ {$test['name']} failed: " . $e->getMessage());
            }
            return 1;
        }
    }

    private function buildCallParameters(array $arguments, array $options): array
    {
        $params = [];
        
        // Add arguments (positional parameters)
        foreach ($arguments as $index => $argument) {
            if (is_string($index)) {
                // Named argument
                $params[$index] = $argument;
            } else {
                // Positional argument - Laravel expects them as numbered keys starting from 0
                $params[$index] = $argument;
            }
        }
        
        // Add options (with -- prefix handling)
        foreach ($options as $option) {
            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                // Remove -- prefix if present, Laravel call() method expects clean option names
                $cleanKey = ltrim($key, '-');
                $params['--' . $cleanKey] = $value;
            } else {
                // Boolean option
                $cleanKey = ltrim($option, '-');
                $params['--' . $cleanKey] = true;
            }
        }
        
        return $params;
    }

    private function parseOptions(array $options): array
    {
        $parsed = [];
        foreach ($options as $option) {
            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                // Keep the -- prefix for options
                $parsed[$key] = $value;
            } else {
                // Keep the -- prefix for boolean options
                $parsed[$option] = true;
            }
        }
        return $parsed;
    }

    private function displayTestResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Test Suite Results:');
        $this->newLine();

        $passed = 0;
        $failed = 0;

        foreach ($results as $testKey => $exitCode) {
            $testName = $this->testSuite[$testKey]['name'];
            $status = $exitCode === 0 ? '✅ PASS' : '❌ FAIL';
            
            if ($exitCode === 0) {
                $passed++;
            } else {
                $failed++;
            }

            $this->line("  {$status} {$testName}");
        }

        $this->newLine();
        $total = $passed + $failed;
        
        if ($failed === 0) {
            $this->info("🎉 All tests passed! ({$passed}/{$total})");
        } else {
            $this->warn("⚠️  {$passed}/{$total} tests passed, {$failed} failed");
        }

        $this->newLine();
        $this->comment('💡 For detailed analysis, run: php artisan tenancy:health-check --detailed');
    }

    private function allTestsPassed(array $results): bool
    {
        return !in_array(1, $results, true);
    }
}
