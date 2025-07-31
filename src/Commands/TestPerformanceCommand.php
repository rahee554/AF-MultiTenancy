<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TestPerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:test-performance 
                            {--concurrent-users=10 : Number of concurrent users to simulate}
                            {--duration=30 : Test duration in seconds}
                            {--requests-per-user=10 : Requests per user}';

    /**
     * The console command description.
     */
    protected $description = 'Test tenant performance with concurrent users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $concurrentUsers = (int) $this->option('concurrent-users');
        $duration = (int) $this->option('duration');
        $requestsPerUser = (int) $this->option('requests-per-user');

        $this->info('🚀 Starting Tenancy Performance Test');
        $this->line("Concurrent Users: {$concurrentUsers}");
        $this->line("Duration: {$duration} seconds");
        $this->line("Requests per User: {$requestsPerUser}");
        $this->newLine();

        // Get test tenants
        $tenants = Tenant::where('name', 'LIKE', '%Test%')->limit(5)->get();
        
        if ($tenants->isEmpty()) {
            $this->error('No test tenants found. Run: php artisan tenancy:create-test-tenants');
            return 1;
        }

        $this->info("Found {$tenants->count()} test tenants for performance testing");

        // Performance metrics
        $metrics = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'response_times' => [],
            'memory_usage' => [],
            'connection_times' => []
        ];

        $startTime = microtime(true);
        $this->info('Starting performance test...');

        // Simulate concurrent users
        for ($user = 1; $user <= $concurrentUsers; $user++) {
            $this->simulateUser($user, $tenants, $requestsPerUser, $metrics);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Display results
        $this->displayResults($metrics, $totalTime, $concurrentUsers);

        return 0;
    }

    /**
     * Simulate a user making requests.
     */
    protected function simulateUser(int $userId, $tenants, int $requests, array &$metrics): void
    {
        for ($i = 1; $i <= $requests; $i++) {
            $tenant = $tenants->random();
            
            $requestStart = microtime(true);
            $memoryStart = memory_get_usage();
            
            try {
                // Test tenant switching performance
                $connectionStart = microtime(true);
                tenancy()->initialize($tenant);
                $connectionTime = (microtime(true) - $connectionStart) * 1000; // ms
                
                // Simulate some database operations
                $result = DB::table('users')->count();
                
                tenancy()->end();
                
                $requestTime = (microtime(true) - $requestStart) * 1000; // ms
                $memoryUsed = memory_get_usage() - $memoryStart;
                
                $metrics['total_requests']++;
                $metrics['successful_requests']++;
                $metrics['response_times'][] = $requestTime;
                $metrics['memory_usage'][] = $memoryUsed;
                $metrics['connection_times'][] = $connectionTime;
                
            } catch (\Exception $e) {
                $metrics['total_requests']++;
                $metrics['failed_requests']++;
                $this->warn("Request failed for user {$userId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Display performance test results.
     */
    protected function displayResults(array $metrics, float $totalTime, int $concurrentUsers): void
    {
        $this->newLine();
        $this->info('📊 Performance Test Results');
        $this->line(str_repeat('=', 50));

        // Basic metrics
        $this->table([
            'Metric', 'Value'
        ], [
            ['Total Requests', $metrics['total_requests']],
            ['Successful Requests', $metrics['successful_requests']],
            ['Failed Requests', $metrics['failed_requests']],
            ['Success Rate', round(($metrics['successful_requests'] / $metrics['total_requests']) * 100, 2) . '%'],
            ['Total Time', round($totalTime, 2) . 's'],
            ['Requests/Second', round($metrics['total_requests'] / $totalTime, 2)],
        ]);

        if (!empty($metrics['response_times'])) {
            // Response time statistics
            $responseTimes = $metrics['response_times'];
            sort($responseTimes);
            
            $this->newLine();
            $this->info('⚡ Response Time Analysis');
            $this->table([
                'Metric', 'Value (ms)'
            ], [
                ['Average', round(array_sum($responseTimes) / count($responseTimes), 2)],
                ['Median', round($responseTimes[count($responseTimes) / 2], 2)],
                ['95th Percentile', round($responseTimes[count($responseTimes) * 0.95], 2)],
                ['99th Percentile', round($responseTimes[count($responseTimes) * 0.99], 2)],
                ['Min', round(min($responseTimes), 2)],
                ['Max', round(max($responseTimes), 2)],
            ]);
        }

        if (!empty($metrics['connection_times'])) {
            // Connection time statistics
            $connectionTimes = $metrics['connection_times'];
            sort($connectionTimes);
            
            $this->newLine();
            $this->info('🔌 Database Connection Performance');
            $this->table([
                'Metric', 'Value (ms)'
            ], [
                ['Avg Connection Time', round(array_sum($connectionTimes) / count($connectionTimes), 2)],
                ['95th Percentile', round($connectionTimes[count($connectionTimes) * 0.95], 2)],
                ['Max Connection Time', round(max($connectionTimes), 2)],
            ]);
        }

        if (!empty($metrics['memory_usage'])) {
            // Memory usage statistics
            $memoryUsage = $metrics['memory_usage'];
            
            $this->newLine();
            $this->info('💾 Memory Usage Analysis');
            $this->table([
                'Metric', 'Value'
            ], [
                ['Avg Memory per Request', $this->formatBytes(array_sum($memoryUsage) / count($memoryUsage))],
                ['Max Memory per Request', $this->formatBytes(max($memoryUsage))],
                ['Total Memory Used', $this->formatBytes(array_sum($memoryUsage))],
                ['Current Memory Usage', $this->formatBytes(memory_get_usage())],
                ['Peak Memory Usage', $this->formatBytes(memory_get_peak_usage())],
            ]);
        }

        // Performance verdict
        $this->newLine();
        $this->performanceVerdict($metrics, $totalTime);
    }

    /**
     * Provide performance verdict.
     */
    protected function performanceVerdict(array $metrics, float $totalTime): void
    {
        $avgResponseTime = array_sum($metrics['response_times']) / count($metrics['response_times']);
        $avgConnectionTime = array_sum($metrics['connection_times']) / count($metrics['connection_times']);
        $successRate = ($metrics['successful_requests'] / $metrics['total_requests']) * 100;
        $requestsPerSecond = $metrics['total_requests'] / $totalTime;

        $this->info('🎯 Performance Verdict');
        
        // Response time verdict
        if ($avgResponseTime < 50) {
            $this->line('<fg=green>✅ Excellent response times (< 50ms average)</fg=green>');
        } elseif ($avgResponseTime < 100) {
            $this->line('<fg=yellow>⚠️ Good response times (< 100ms average)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Poor response times (> 100ms average)</fg=red>');
        }

        // Connection time verdict
        if ($avgConnectionTime < 10) {
            $this->line('<fg=green>✅ Excellent connection performance (< 10ms average)</fg=green>');
        } elseif ($avgConnectionTime < 50) {
            $this->line('<fg=yellow>⚠️ Good connection performance (< 50ms average)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Poor connection performance (> 50ms average)</fg=red>');
        }

        // Success rate verdict
        if ($successRate >= 99) {
            $this->line('<fg=green>✅ Excellent reliability (' . round($successRate, 1) . '% success rate)</fg=green>');
        } elseif ($successRate >= 95) {
            $this->line('<fg=yellow>⚠️ Good reliability (' . round($successRate, 1) . '% success rate)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Poor reliability (' . round($successRate, 1) . '% success rate)</fg=red>');
        }

        // Throughput verdict
        if ($requestsPerSecond >= 100) {
            $this->line('<fg=green>✅ High throughput (' . round($requestsPerSecond, 1) . ' req/s)</fg=green>');
        } elseif ($requestsPerSecond >= 50) {
            $this->line('<fg=yellow>⚠️ Moderate throughput (' . round($requestsPerSecond, 1) . ' req/s)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Low throughput (' . round($requestsPerSecond, 1) . ' req/s)</fg=red>');
        }

        $this->newLine();
        $this->comment('💡 Tips for better performance:');
        $this->line('  • Use Redis for caching (CACHE_DRIVER=redis)');
        $this->line('  • Enable persistent connections');
        $this->line('  • Optimize database queries');
        $this->line('  • Consider connection pooling for high loads');
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
