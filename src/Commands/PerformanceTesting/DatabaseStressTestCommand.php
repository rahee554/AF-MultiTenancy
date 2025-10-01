<?php

namespace ArtflowStudio\Tenancy\Commands\PerformanceTesting;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class DatabaseStressTestCommand extends Command
{
    protected $signature = 'tenancy:stress-test-database 
                            {--tenants=5 : Number of tenants to stress test}
                            {--connections=50 : Number of concurrent connections per tenant}
                            {--duration=60 : Test duration in seconds}
                            {--query-type=mixed : Query type (simple|complex|mixed)}';

    protected $description = '💪 Database stress testing for multi-tenancy';

    protected array $metrics = [];
    protected bool $shouldStop = false;

    public function handle(): int
    {
        $this->displayHeader();
        
        $tenants = Tenant::take($this->option('tenants'))->get();
        
        if ($tenants->isEmpty()) {
            $this->error('❌ No tenants found. Create test tenants first.');
            return 1;
        }

        $this->info("🚀 Starting stress test with {$tenants->count()} tenants...");
        $this->newLine();

        $startTime = time();
        $duration = $this->option('duration');
        $connections = $this->option('connections');

        $progressBar = $this->output->createProgressBar($duration);
        $progressBar->setFormat(' %current%/%max% seconds [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Initializing...');

        while ((time() - $startTime) < $duration && !$this->shouldStop) {
            foreach ($tenants as $tenant) {
                $this->runStressTest($tenant, $connections);
            }
            
            $elapsed = time() - $startTime;
            $progressBar->setProgress($elapsed);
            $progressBar->setMessage($this->getStatusMessage());
            
            usleep(100000); // 100ms
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults();

        return 0;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║              💪 DATABASE STRESS TEST                          ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        $this->comment('📊 Configuration:');
        $this->line("   • Tenants: {$this->option('tenants')}");
        $this->line("   • Connections per Tenant: {$this->option('connections')}");
        $this->line("   • Duration: {$this->option('duration')}s");
        $this->line("   • Query Type: {$this->option('query-type')}");
        $this->newLine();
    }

    private function runStressTest(Tenant $tenant, int $connections): void
    {
        $tenantId = $tenant->id;
        
        if (!isset($this->metrics[$tenantId])) {
            $this->metrics[$tenantId] = [
                'queries_executed' => 0,
                'queries_failed' => 0,
                'total_query_time' => 0,
                'min_query_time' => PHP_FLOAT_MAX,
                'max_query_time' => 0,
                'errors' => [],
            ];
        }

        try {
            tenancy()->initialize($tenant);
            
            for ($i = 0; $i < $connections; $i++) {
                $queryStart = microtime(true);
                
                try {
                    $query = $this->generateQuery();
                    DB::connection('tenant')->select($query);
                    
                    $queryTime = (microtime(true) - $queryStart) * 1000;
                    
                    $this->metrics[$tenantId]['queries_executed']++;
                    $this->metrics[$tenantId]['total_query_time'] += $queryTime;
                    $this->metrics[$tenantId]['min_query_time'] = min($this->metrics[$tenantId]['min_query_time'], $queryTime);
                    $this->metrics[$tenantId]['max_query_time'] = max($this->metrics[$tenantId]['max_query_time'], $queryTime);
                    
                } catch (Exception $e) {
                    $this->metrics[$tenantId]['queries_failed']++;
                    $this->metrics[$tenantId]['errors'][] = $e->getMessage();
                }
            }
            
            tenancy()->end();
            
        } catch (Exception $e) {
            $this->metrics[$tenantId]['queries_failed'] += $connections;
            $this->metrics[$tenantId]['errors'][] = "Tenant initialization failed: {$e->getMessage()}";
        }
    }

    private function generateQuery(): string
    {
        $queryType = $this->option('query-type');
        
        return match($queryType) {
            'simple' => 'SELECT 1 as test',
            'complex' => 'SELECT t1.*, t2.* FROM information_schema.tables t1 
                          JOIN information_schema.columns t2 ON t1.table_name = t2.table_name 
                          WHERE t1.table_schema = DATABASE() 
                          LIMIT 100',
            'mixed' => rand(0, 1) === 0 
                ? 'SELECT 1 as test'
                : 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()',
            default => 'SELECT 1 as test',
        };
    }

    private function getStatusMessage(): string
    {
        $totalQueries = array_sum(array_column($this->metrics, 'queries_executed'));
        $totalFailed = array_sum(array_column($this->metrics, 'queries_failed'));
        
        return "Queries: {$totalQueries} | Failed: {$totalFailed}";
    }

    private function displayResults(): void
    {
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                     📊 STRESS TEST RESULTS                    ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $totalQueries = 0;
        $totalFailed = 0;
        $totalTime = 0;

        foreach ($this->metrics as $tenantId => $metrics) {
            $totalQueries += $metrics['queries_executed'];
            $totalFailed += $metrics['queries_failed'];
            $totalTime += $metrics['total_query_time'];
        }

        // Summary Table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tenants Tested', count($this->metrics)],
                ['Total Queries Executed', number_format($totalQueries)],
                ['Total Queries Failed', $totalFailed > 0 ? "❌ " . number_format($totalFailed) : "✅ 0"],
                ['Success Rate', round(($totalQueries / ($totalQueries + $totalFailed)) * 100, 2) . '%'],
                ['Total Query Time', round($totalTime, 2) . 'ms'],
                ['Avg Query Time', round($totalTime / $totalQueries, 4) . 'ms'],
                ['Queries Per Second', round($totalQueries / $this->option('duration'), 2)],
            ]
        );

        $this->newLine();

        // Per-Tenant Results
        $this->info('📋 Per-Tenant Results:');
        $this->newLine();

        $perTenantData = [];
        foreach ($this->metrics as $tenantId => $metrics) {
            $avgTime = $metrics['queries_executed'] > 0 
                ? round($metrics['total_query_time'] / $metrics['queries_executed'], 4)
                : 0;

            $perTenantData[] = [
                substr($tenantId, 0, 8) . '...',
                number_format($metrics['queries_executed']),
                number_format($metrics['queries_failed']),
                $avgTime . 'ms',
                round($metrics['min_query_time'], 4) . 'ms',
                round($metrics['max_query_time'], 4) . 'ms',
            ];
        }

        $this->table(
            ['Tenant ID', 'Executed', 'Failed', 'Avg Time', 'Min Time', 'Max Time'],
            $perTenantData
        );

        // Display errors if any
        $hasErrors = false;
        foreach ($this->metrics as $tenantId => $metrics) {
            if (!empty($metrics['errors'])) {
                $hasErrors = true;
                break;
            }
        }

        if ($hasErrors) {
            $this->newLine();
            $this->warn('⚠️  Errors Detected:');
            $this->newLine();

            foreach ($this->metrics as $tenantId => $metrics) {
                if (!empty($metrics['errors'])) {
                    $this->error("Tenant {$tenantId}:");
                    $uniqueErrors = array_unique($metrics['errors']);
                    foreach (array_slice($uniqueErrors, 0, 5) as $error) {
                        $this->line("   • {$error}");
                    }
                    if (count($metrics['errors']) > 5) {
                        $this->line("   ... and " . (count($metrics['errors']) - 5) . " more errors");
                    }
                    $this->newLine();
                }
            }
        }

        // Final verdict
        $this->newLine();
        if ($totalFailed === 0) {
            $this->info('🎉 Stress test completed successfully with no failures!');
        } else {
            $failureRate = round(($totalFailed / ($totalQueries + $totalFailed)) * 100, 2);
            $this->warn("⚠️  Stress test completed with {$failureRate}% failure rate");
        }
        
        $this->newLine();
    }
}
