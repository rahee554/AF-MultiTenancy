<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use ArtflowStudio\Tenancy\Models\Tenant;

class TestSystemCommand extends Command
{
    protected $signature = 'tenancy:test-system 
                            {--skip-commands : Skip command testing}
                            {--skip-api : Skip API endpoint testing}
                            {--api-key= : API key for testing endpoints}
                            {--base-url= : Base URL for API testing}';

    protected $description = 'Comprehensive system test for all commands and API endpoints';

    public function handle(): int
    {
        $this->info('🧪 Starting Comprehensive System Test');
        $this->info('=====================================');
        $this->newLine();

        $results = [
            'commands' => [],
            'api_endpoints' => [],
            'overall_status' => true
        ];

        // Test commands
        if (!$this->option('skip-commands')) {
            $this->info('🔧 Testing All Commands...');
            $results['commands'] = $this->testAllCommands();
            $this->newLine();
        }

        // Test API endpoints
        if (!$this->option('skip-api')) {
            $this->info('🌐 Testing API Endpoints...');
            $results['api_endpoints'] = $this->testApiEndpoints();
            $this->newLine();
        }

        // Display final summary
        $this->displayFinalSummary($results);

        return $results['overall_status'] ? 0 : 1;
    }

    private function testAllCommands(): array
    {
        $commands = [
            'tenant:manage health' => 'System health check',
            'tenant:manage list' => 'List all tenants',
            'tenancy:test-performance --concurrent-users=10 --requests-per-user=5' => 'Performance test',
        ];

        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($commands as $command => $description) {
            $this->info("Testing: {$description}");
            
            try {
                $exitCode = Artisan::call($command);
                if ($exitCode === 0) {
                    $this->line("  ✅ PASSED: {$command}");
                    $results[$command] = ['status' => 'passed', 'description' => $description];
                    $passed++;
                } else {
                    $this->line("  ❌ FAILED: {$command} (exit code: {$exitCode})");
                    $results[$command] = ['status' => 'failed', 'description' => $description, 'exit_code' => $exitCode];
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->line("  ❌ FAILED: {$command} - {$e->getMessage()}");
                $results[$command] = ['status' => 'failed', 'description' => $description, 'error' => $e->getMessage()];
                $failed++;
            }
        }

        // Test command table
        $this->newLine();
        $this->table(['Command', 'Status', 'Description'], array_map(function ($command, $result) {
            return [
                $command,
                $result['status'] === 'passed' ? '✅ PASSED' : '❌ FAILED',
                $result['description']
            ];
        }, array_keys($results), $results));

        $this->info("📊 Commands Summary: {$passed} passed, {$failed} failed");
        return $results;
    }

    private function testApiEndpoints(): array
    {
        $apiKey = $this->option('api-key') ?: env('API_KEY');
        $baseUrl = $this->option('base-url') ?: env('APP_URL', 'http://localhost');

        if (!$apiKey) {
            $this->warn('⚠️  No API key provided. Set --api-key option or API_KEY in .env');
            return [];
        }

        $endpoints = [
            'GET /api/tenants' => 'List all tenants',
            'GET /api/health' => 'Health check endpoint',
            'GET /api/tenants/stats' => 'Tenant statistics',
        ];

        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($endpoints as $endpoint => $description) {
            [$method, $path] = explode(' ', $endpoint, 2);
            $url = rtrim($baseUrl, '/') . $path;
            
            $this->info("Testing: {$description} ({$method} {$path})");
            
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ])->timeout(10)->$method($url);

                if ($response->successful()) {
                    $this->line("  ✅ PASSED: {$endpoint} (Status: {$response->status()})");
                    $results[$endpoint] = [
                        'status' => 'passed',
                        'description' => $description,
                        'http_status' => $response->status(),
                        'response_time' => $response->transferStats->getTransferTime() ?? 0
                    ];
                    $passed++;
                } else {
                    $this->line("  ❌ FAILED: {$endpoint} (Status: {$response->status()})");
                    $results[$endpoint] = [
                        'status' => 'failed',
                        'description' => $description,
                        'http_status' => $response->status(),
                        'error' => $response->body()
                    ];
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->line("  ❌ FAILED: {$endpoint} - {$e->getMessage()}");
                $results[$endpoint] = [
                    'status' => 'failed',
                    'description' => $description,
                    'error' => $e->getMessage()
                ];
                $failed++;
            }
        }

        // API endpoints table
        $this->newLine();
        $this->table(['Endpoint', 'Status', 'Description', 'Details'], array_map(function ($endpoint, $result) {
            $details = isset($result['http_status']) ? "HTTP {$result['http_status']}" : 
                      (isset($result['error']) ? substr($result['error'], 0, 50) . '...' : '');
            
            return [
                $endpoint,
                $result['status'] === 'passed' ? '✅ PASSED' : '❌ FAILED',
                $result['description'],
                $details
            ];
        }, array_keys($results), $results));

        $this->info("📊 API Endpoints Summary: {$passed} passed, {$failed} failed");
        return $results;
    }

    private function displayFinalSummary(array $results): void
    {
        $this->newLine();
        $this->info('🎯 Final Test Summary');
        $this->info('====================');

        $totalPassed = 0;
        $totalFailed = 0;

        // Count command results
        foreach ($results['commands'] as $result) {
            if ($result['status'] === 'passed') {
                $totalPassed++;
            } else {
                $totalFailed++;
            }
        }

        // Count API results
        foreach ($results['api_endpoints'] as $result) {
            if ($result['status'] === 'passed') {
                $totalPassed++;
            } else {
                $totalFailed++;
            }
        }

        $overallStatus = $totalFailed === 0 ? '✅ ALL TESTS PASSED' : "❌ {$totalFailed} TESTS FAILED";
        $results['overall_status'] = $totalFailed === 0;

        $this->table(['Category', 'Total', 'Passed', 'Failed'], [
            ['Commands', count($results['commands']), 
             count(array_filter($results['commands'], fn($r) => $r['status'] === 'passed')),
             count(array_filter($results['commands'], fn($r) => $r['status'] === 'failed'))],
            ['API Endpoints', count($results['api_endpoints']), 
             count(array_filter($results['api_endpoints'], fn($r) => $r['status'] === 'passed')),
             count(array_filter($results['api_endpoints'], fn($r) => $r['status'] === 'failed'))],
            ['TOTAL', $totalPassed + $totalFailed, $totalPassed, $totalFailed]
        ]);

        $this->newLine();
        $this->info("🏆 Overall Status: {$overallStatus}");

        if ($totalFailed > 0) {
            $this->newLine();
            $this->warn('⚠️  Some tests failed. Please check the details above and fix any issues.');
        } else {
            $this->newLine();
            $this->info('🎉 All tests passed! Your system is working correctly.');
        }
    }
}
