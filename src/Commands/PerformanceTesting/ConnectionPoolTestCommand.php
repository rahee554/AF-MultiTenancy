<?php

namespace ArtflowStudio\Tenancy\Commands\PerformanceTesting;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class ConnectionPoolTestCommand extends Command
{
    protected $signature = 'tenancy:test-connection-pool 
                            {--tenants=10 : Number of tenants}
                            {--iterations=100 : Number of iterations}
                            {--check-leaks : Check for connection leaks}';

    protected $description = '🔌 Test database connection pool behavior and detect leaks';

    protected array $results = [];

    public function handle(): int
    {
        $this->displayHeader();

        $tenants = Tenant::take($this->option('tenants'))->get();

        if ($tenants->isEmpty()) {
            $this->error('❌ No tenants found. Create test tenants first.');
            return 1;
        }

        // Test 1: Connection Pool Size
        $this->info('🔍 Test 1: Connection Pool Behavior');
        $this->testConnectionPoolBehavior($tenants);
        $this->newLine();

        // Test 2: Connection Reuse
        $this->info('🔍 Test 2: Connection Reuse Efficiency');
        $this->testConnectionReuse($tenants);
        $this->newLine();

        // Test 3: Persistent Connection Test
        $this->info('🔍 Test 3: Persistent Connection Check');
        $this->testPersistentConnections($tenants);
        $this->newLine();

        // Test 4: Connection Leak Detection
        if ($this->option('check-leaks')) {
            $this->info('🔍 Test 4: Connection Leak Detection');
            $this->testConnectionLeaks($tenants);
            $this->newLine();
        }

        // Test 5: Connection Cleanup
        $this->info('🔍 Test 5: Connection Cleanup After Tenant Switch');
        $this->testConnectionCleanup($tenants);
        $this->newLine();

        $this->displayResults();

        return 0;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║           🔌 CONNECTION POOL TEST SUITE                       ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function testConnectionPoolBehavior(iterable $tenants): void
    {
        $connectionCounts = [];
        $connectionNames = [];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            $connectionName = DB::connection()->getName();
            $connectionNames[] = $connectionName;
            $connectionCounts[] = count(DB::getConnections());
            
            // Execute a query to ensure connection is established
            DB::connection('tenant')->select('SELECT 1 as test');
            
            tenancy()->end();
        }

        $this->results['connection_pool'] = [
            'status' => 'PASSED',
            'total_tenants' => count($tenants),
            'unique_connections' => count(array_unique($connectionNames)),
            'max_concurrent_connections' => max($connectionCounts),
            'avg_connections' => round(array_sum($connectionCounts) / count($connectionCounts), 2),
            'connection_names' => array_unique($connectionNames),
        ];

        $this->line("   ✅ Unique Connections: " . count(array_unique($connectionNames)));
        $this->line("   ✅ Max Concurrent: " . max($connectionCounts));
        $this->line("   ✅ Avg Connections: " . round(array_sum($connectionCounts) / count($connectionCounts), 2));
    }

    private function testConnectionReuse(iterable $tenants): void
    {
        $reuseCount = 0;
        $newConnectionCount = 0;
        $previousConnections = [];

        foreach ($tenants as $tenant) {
            $beforeConnections = DB::getConnections();
            
            tenancy()->initialize($tenant);
            DB::connection('tenant')->select('SELECT 1 as test');
            
            $afterConnections = DB::getConnections();
            
            // Check if connection was reused
            $newConnections = array_diff_key($afterConnections, $beforeConnections);
            
            if (empty($newConnections)) {
                $reuseCount++;
            } else {
                $newConnectionCount++;
            }
            
            tenancy()->end();
        }

        $this->results['connection_reuse'] = [
            'status' => 'PASSED',
            'reused_connections' => $reuseCount,
            'new_connections' => $newConnectionCount,
            'reuse_rate' => round(($reuseCount / count($tenants)) * 100, 2) . '%',
        ];

        $this->line("   ✅ Reused Connections: {$reuseCount}");
        $this->line("   ✅ New Connections: {$newConnectionCount}");
        $this->line("   ✅ Reuse Rate: " . round(($reuseCount / count($tenants)) * 100, 2) . '%');
    }

    private function testPersistentConnections(iterable $tenants): void
    {
        $isPersistent = false;
        $checkResults = [];

        foreach ($tenants->take(3) as $tenant) {
            tenancy()->initialize($tenant);
            
            try {
                $pdo = DB::connection('tenant')->getPdo();
                $isPersistent = $pdo->getAttribute(\PDO::ATTR_PERSISTENT);
                $checkResults[] = $isPersistent;
            } catch (Exception $e) {
                $checkResults[] = null;
            }
            
            tenancy()->end();
        }

        $hasPersistent = in_array(true, $checkResults, true);

        $this->results['persistent_connections'] = [
            'status' => $hasPersistent ? 'WARNING' : 'PASSED',
            'persistent_detected' => $hasPersistent,
            'message' => $hasPersistent 
                ? '⚠️  Persistent connections detected (not recommended for multi-tenancy)'
                : '✅ No persistent connections (correct for multi-tenancy)',
        ];

        if ($hasPersistent) {
            $this->warn("   ⚠️  Persistent connections detected (PDO::ATTR_PERSISTENT = true)");
            $this->warn("   ⚠️  This can cause issues with multi-tenancy!");
            $this->comment("   💡 Set PDO::ATTR_PERSISTENT => false in config/database.php");
        } else {
            $this->line("   ✅ No persistent connections (correct configuration)");
        }
    }

    private function testConnectionLeaks(iterable $tenants): void
    {
        $iterations = $this->option('iterations');
        $leaks = [];
        
        $initialConnections = count(DB::getConnections());
        $memoryStart = memory_get_usage(true);

        $progressBar = $this->output->createProgressBar($iterations);
        $progressBar->setFormat('   [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Testing...');

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($tenants as $tenant) {
                tenancy()->initialize($tenant);
                DB::connection('tenant')->select('SELECT 1 as test');
                tenancy()->end();
            }

            $currentConnections = count(DB::getConnections());
            $currentMemory = memory_get_usage(true);
            
            if ($currentConnections > $initialConnections) {
                $leaks[] = [
                    'iteration' => $i,
                    'connection_count' => $currentConnections,
                    'leaked' => $currentConnections - $initialConnections,
                ];
            }

            $progressBar->setProgress($i + 1);
            $progressBar->setMessage("Connections: {$currentConnections}");
        }

        $progressBar->finish();
        $this->newLine();

        $finalConnections = count(DB::getConnections());
        $memoryEnd = memory_get_usage(true);
        $memoryIncrease = $memoryEnd - $memoryStart;

        $this->results['connection_leaks'] = [
            'status' => empty($leaks) ? 'PASSED' : 'FAILED',
            'initial_connections' => $initialConnections,
            'final_connections' => $finalConnections,
            'leaked_connections' => $finalConnections - $initialConnections,
            'iterations_tested' => $iterations,
            'memory_increase' => $this->formatBytes($memoryIncrease),
            'leaks_detected' => count($leaks),
        ];

        if (empty($leaks)) {
            $this->line("   ✅ No connection leaks detected after {$iterations} iterations");
        } else {
            $this->error("   ❌ Connection leaks detected: " . ($finalConnections - $initialConnections) . " leaked connections");
            $this->line("   Memory increase: " . $this->formatBytes($memoryIncrease));
        }
    }

    private function testConnectionCleanup(iterable $tenants): void
    {
        $cleanupResults = [];

        foreach ($tenants as $tenant) {
            $beforeCount = count(DB::getConnections());
            
            tenancy()->initialize($tenant);
            DB::connection('tenant')->select('SELECT 1 as test');
            $duringCount = count(DB::getConnections());
            
            tenancy()->end();
            $afterCount = count(DB::getConnections());
            
            // Explicitly disconnect
            DB::purge('tenant');
            $afterPurgeCount = count(DB::getConnections());
            
            $cleanupResults[] = [
                'tenant' => substr($tenant->id, 0, 8) . '...',
                'before' => $beforeCount,
                'during' => $duringCount,
                'after' => $afterCount,
                'after_purge' => $afterPurgeCount,
                'cleaned' => $afterPurgeCount <= $beforeCount,
            ];
        }

        $allCleaned = array_reduce($cleanupResults, fn($carry, $item) => $carry && $item['cleaned'], true);

        $this->results['connection_cleanup'] = [
            'status' => $allCleaned ? 'PASSED' : 'WARNING',
            'all_connections_cleaned' => $allCleaned,
            'tenants_tested' => count($cleanupResults),
            'cleanup_details' => $cleanupResults,
        ];

        if ($allCleaned) {
            $this->line("   ✅ All connections properly cleaned up after tenant switch");
        } else {
            $this->warn("   ⚠️  Some connections not properly cleaned up");
        }

        // Show detailed table
        $tableData = [];
        foreach ($cleanupResults as $result) {
            $tableData[] = [
                $result['tenant'],
                $result['before'],
                $result['during'],
                $result['after'],
                $result['after_purge'],
                $result['cleaned'] ? '✅' : '❌',
            ];
        }

        $this->newLine();
        $this->table(
            ['Tenant', 'Before', 'During', 'After', 'After Purge', 'Cleaned'],
            $tableData
        );
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                  📊 FINAL RESULTS                             ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $passed = 0;
        $failed = 0;
        $warnings = 0;

        foreach ($this->results as $testName => $result) {
            $status = $result['status'];
            if ($status === 'PASSED') {
                $passed++;
            } elseif ($status === 'FAILED') {
                $failed++;
            } elseif ($status === 'WARNING') {
                $warnings++;
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tests', count($this->results)],
                ['Passed', "✅ {$passed}"],
                ['Warnings', $warnings > 0 ? "⚠️  {$warnings}" : "✅ 0"],
                ['Failed', $failed > 0 ? "❌ {$failed}" : "✅ 0"],
            ]
        );

        $this->newLine();

        // Recommendations
        if ($warnings > 0 || $failed > 0) {
            $this->warn('📋 Recommendations:');
            $this->newLine();

            if (isset($this->results['persistent_connections']) && 
                $this->results['persistent_connections']['persistent_detected']) {
                $this->comment('   • Disable persistent connections in config/database.php');
                $this->comment('     Set PDO::ATTR_PERSISTENT => false');
            }

            if (isset($this->results['connection_leaks']) && 
                $this->results['connection_leaks']['leaked_connections'] > 0) {
                $this->comment('   • Review connection management in tenancy initialization');
                $this->comment('   • Ensure DB::purge() is called after tenant context ends');
            }

            $this->newLine();
        } else {
            $this->info('🎉 All connection pool tests passed! Your multi-tenancy setup is optimized.');
        }

        $this->newLine();
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
