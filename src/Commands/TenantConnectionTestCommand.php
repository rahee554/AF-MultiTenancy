<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class TenantConnectionTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:test-connections 
                            {--timeout=30 : Connection timeout in seconds}
                            {--retry=3 : Number of retry attempts}
                            {--detailed : Show detailed connection info}';

    /**
     * The console command description.
     */
    protected $description = 'Test database connections for all tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $retries = (int) $this->option('retry');
        $detailed = $this->option('detailed');

        $this->info('🔌 Tenant Connection Test Suite');
        $this->newLine();

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants found to test');
            return 0;
        }

        $this->info("Testing connections for {$tenants->count()} tenants");
        $this->info("Timeout: {$timeout}s, Retries: {$retries}");
        $this->newLine();

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        $progressBar = $this->output->createProgressBar($tenants->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% - %message%');

        foreach ($tenants as $tenant) {
            $progressBar->setMessage("Testing {$tenant->name}...");
            
            $connectionResult = $this->testTenantConnection($tenant, $timeout, $retries, $detailed);
            $results[] = $connectionResult;
            
            if ($connectionResult['status'] === 'success') {
                $successCount++;
            } else {
                $failureCount++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayConnectionResults($results, $successCount, $failureCount, $detailed);

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Test connection for a specific tenant
     */
    protected function testTenantConnection($tenant, int $timeout, int $retries, bool $detailed): array
    {
        $result = [
            'tenant' => $tenant,
            'status' => 'unknown',
            'response_time' => null,
            'database_name' => null,
            'error' => null,
            'attempts' => 0,
            'details' => []
        ];

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $result['attempts'] = $attempt;
            
            try {
                $startTime = microtime(true);
                
                // Test connection within tenant context
                $tenant->run(function () use (&$result, $detailed) {
                    // Basic connection test
                    $dbName = DB::select("SELECT DATABASE() as db_name")[0]->db_name;
                    $result['database_name'] = $dbName;
                    
                    // Test basic query
                    $tableCount = DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [$dbName])[0]->count;
                    $result['details']['table_count'] = $tableCount;
                    
                    if ($detailed) {
                        // Additional connection details
                        $connectionId = DB::select("SELECT CONNECTION_ID() as id")[0]->id;
                        $result['details']['connection_id'] = $connectionId;
                        
                        $charset = DB::select("SELECT @@character_set_database as charset")[0]->charset;
                        $result['details']['charset'] = $charset;
                        
                        $version = DB::select("SELECT VERSION() as version")[0]->version;
                        $result['details']['mysql_version'] = $version;
                    }
                });

                $endTime = microtime(true);
                $result['response_time'] = round(($endTime - $startTime) * 1000, 2); // ms
                $result['status'] = 'success';
                break;

            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
                $result['status'] = 'failed';
                
                if ($attempt < $retries) {
                    sleep(1); // Wait before retry
                }
            }
        }

        return $result;
    }

    /**
     * Display connection test results
     */
    protected function displayConnectionResults(array $results, int $successCount, int $failureCount, bool $detailed): void
    {
        // Summary
        $this->info('📊 Connection Test Summary');
        $this->table(['Metric', 'Value'], [
            ['Total Tenants', count($results)],
            ['Successful Connections', $successCount],
            ['Failed Connections', $failureCount],
            ['Success Rate', $successCount ? round(($successCount / count($results)) * 100, 1) . '%' : '0%']
        ]);
        $this->newLine();

        // Successful connections
        if ($successCount > 0) {
            $this->info('✅ Successful Connections:');
            $successfulResults = array_filter($results, fn($r) => $r['status'] === 'success');
            
            $tableData = [];
            foreach ($successfulResults as $result) {
                $row = [
                    $result['tenant']->name,
                    $result['database_name'],
                    $result['response_time'] . 'ms',
                    $result['attempts'] . '/' . $this->option('retry'),
                ];
                
                if ($detailed) {
                    $row[] = $result['details']['table_count'] . ' tables';
                }
                
                $tableData[] = $row;
            }
            
            $headers = ['Tenant', 'Database', 'Response Time', 'Attempts'];
            if ($detailed) {
                $headers[] = 'Tables';
            }
            
            $this->table($headers, $tableData);
            $this->newLine();
        }

        // Failed connections
        if ($failureCount > 0) {
            $this->error('❌ Failed Connections:');
            $failedResults = array_filter($results, fn($r) => $r['status'] === 'failed');
            
            $tableData = [];
            foreach ($failedResults as $result) {
                $tableData[] = [
                    $result['tenant']->name,
                    $result['attempts'] . '/' . $this->option('retry'),
                    substr($result['error'], 0, 60) . (strlen($result['error']) > 60 ? '...' : ''),
                ];
            }
            
            $this->table(['Tenant', 'Attempts', 'Error'], $tableData);
            $this->newLine();
        }

        // Performance analysis
        if ($successCount > 0) {
            $this->analyzePerformance($results);
        }

        // Recommendations
        $this->displayRecommendations($failureCount, $results);
    }

    /**
     * Analyze connection performance
     */
    protected function analyzePerformance(array $results): void
    {
        $successfulResults = array_filter($results, fn($r) => $r['status'] === 'success');
        $responseTimes = array_map(fn($r) => $r['response_time'], $successfulResults);
        
        if (empty($responseTimes)) return;
        
        $avgTime = round(array_sum($responseTimes) / count($responseTimes), 2);
        $minTime = min($responseTimes);
        $maxTime = max($responseTimes);
        
        $this->info('⚡ Performance Analysis:');
        $this->table(['Metric', 'Value'], [
            ['Average Response Time', $avgTime . 'ms'],
            ['Fastest Connection', $minTime . 'ms'],
            ['Slowest Connection', $maxTime . 'ms'],
            ['Performance Rating', $this->getPerformanceRating($avgTime)]
        ]);
        $this->newLine();
    }

    /**
     * Get performance rating based on average response time
     */
    protected function getPerformanceRating(float $avgTime): string
    {
        if ($avgTime < 10) return '🚀 Excellent (< 10ms)';
        if ($avgTime < 50) return '✅ Good (< 50ms)';
        if ($avgTime < 100) return '⚠️  Fair (< 100ms)';
        if ($avgTime < 500) return '🐌 Slow (< 500ms)';
        return '❌ Very Slow (> 500ms)';
    }

    /**
     * Display recommendations based on test results
     */
    protected function displayRecommendations(int $failureCount, array $results): void
    {
        $this->info('💡 Recommendations:');
        
        if ($failureCount === 0) {
            $this->line('  ✅ All connections are working perfectly!');
            $this->line('  • Continue monitoring connection health regularly');
            $this->line('  • Consider setting up automated health checks');
        } else {
            $this->line('  ⚠️  Connection issues detected:');
            $this->line('  • Run: php artisan tenancy:fix-databases to repair broken databases');
            $this->line('  • Check MySQL server status and configuration');
            $this->line('  • Verify tenant database permissions');
            $this->line('  • Consider increasing connection timeout for slow networks');
        }
        
        // Performance recommendations
        $successfulResults = array_filter($results, fn($r) => $r['status'] === 'success');
        if (!empty($successfulResults)) {
            $avgTime = array_sum(array_map(fn($r) => $r['response_time'], $successfulResults)) / count($successfulResults);
            
            if ($avgTime > 100) {
                $this->line('  🐌 Performance recommendations:');
                $this->line('  • Optimize MySQL configuration');
                $this->line('  • Consider connection pooling');
                $this->line('  • Check network latency');
                $this->line('  • Review database indexes');
            }
        }
        
        $this->newLine();
        $this->line('  🔧 Related commands:');
        $this->line('  • tenancy:fix-databases - Fix broken tenant databases');
        $this->line('  • tenancy:validate - Full system validation');
        $this->line('  • tenancy:test-isolation - Test tenant data isolation');
    }
}
