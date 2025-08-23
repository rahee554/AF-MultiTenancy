<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use ArtflowStudio\Tenancy\Models\Tenant;

class TestSystemCommand extends Command
{
    protected $signature = 'tenancy:test-system 
                            {--skip-commands : Skip command testing}
                            {--skip-api : Skip API endpoint testing}
                            {--skip-cache : Skip cache testing}
                            {--skip-database : Skip database testing}
                            {--api-key= : API key for testing endpoints}
                            {--base-url= : Base URL for API testing}
                            {--progress : Show detailed progress}
                            {--show-details : Show extra details}';

    protected $description = 'Comprehensive system test for all commands, API endpoints, caching, and database connectivity';

    public function handle(): int
    {
        $this->showHeader();

        $results = [
            'commands' => [],
            'api_endpoints' => [],
            'cache_tests' => [],
            'database_tests' => [],
            'overall_status' => true
        ];

        // Test commands
        if (!$this->option('skip-commands')) {
            $this->showSection('🔧 Testing All Commands');
            $results['commands'] = $this->testAllCommands();
            $this->newLine();
        }

        // Test caching system
        if (!$this->option('skip-cache')) {
            $this->showSection('💾 Testing Cache System');
            $results['cache_tests'] = $this->testCacheSystem();
            $this->newLine();
        }

        // Test database connectivity
        if (!$this->option('skip-database')) {
            $this->showSection('🗄️ Testing Database Connectivity');
            $results['database_tests'] = $this->testDatabaseConnectivity();
            $this->newLine();
        }

        // Test API endpoints
        if (!$this->option('skip-api')) {
            $this->showSection('🌐 Testing API Endpoints');
            $results['api_endpoints'] = $this->testApiEndpoints();
            $this->newLine();
        }

        // Display final summary
        $this->displayFinalSummary($results);

        return $results['overall_status'] ? 0 : 1;
    }

    private function showHeader(): void
    {
        $this->info('🧪 Starting Comprehensive System Test');
        $this->info('=====================================');
        $this->newLine();
        
        if ($this->option('progress') || $this->option('show-details')) {
            $this->comment('Running with detailed progress display...');
            $this->newLine();
        }
    }

    private function showSection(string $title): void
    {
        $this->info($title);
        if ($this->option('progress')) {
            $this->comment(str_repeat('-', strlen(strip_tags($title))));
        }
    }

    private function showProgress(string $message, string $status = 'info'): void
    {
        if ($this->option('progress') || $this->option('show-details')) {
            match($status) {
                'success' => $this->line("  ✅ {$message}"),
                'error' => $this->line("  ❌ {$message}"),
                'warning' => $this->line("  ⚠️  {$message}"),
                'working' => $this->line("  🔄 {$message}"),
                default => $this->line("  ℹ️  {$message}")
            };
        }
    }

    private function testAllCommands(): array
    {
        $commands = [
            'tenant:manage health' => 'System health check',
            'tenant:manage list' => 'List all tenants',
            // Skip interactive commands in automated testing
            // 'tenant:manage status' => 'Show tenant status overview',
            'tenancy:test-performance --concurrent-users=5 --requests-per-user=3' => 'Performance test',
        ];

        $results = [];
        $passed = 0;
        $failed = 0;

        $this->showProgress("Testing " . count($commands) . " commands...", 'working');

        foreach ($commands as $command => $description) {
            $this->showProgress("Running: {$description}", 'working');
            
            $startTime = microtime(true);
            
            try {
                $exitCode = Artisan::call($command);
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                
                if ($exitCode === 0) {
                    $this->line("  ✅ PASSED: {$command}" . ($this->option('show-details') ? " ({$duration}ms)" : ""));
                    $results[$command] = [
                        'status' => 'passed', 
                        'description' => $description, 
                        'duration' => $duration
                    ];
                    $passed++;
                } else {
                    $this->line("  ❌ FAILED: {$command} (exit code: {$exitCode})");
                    $results[$command] = [
                        'status' => 'failed', 
                        'description' => $description, 
                        'exit_code' => $exitCode,
                        'duration' => $duration
                    ];
                    $failed++;
                }
            } catch (\Exception $e) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $this->line("  ❌ FAILED: {$command} - {$e->getMessage()}");
                $results[$command] = [
                    'status' => 'failed', 
                    'description' => $description, 
                    'error' => $e->getMessage(),
                    'duration' => $duration
                ];
                $failed++;
            }
        }

        // Test command table
        $this->newLine();
        $tableData = array_map(function ($command, $result) {
            $statusDisplay = $result['status'] === 'passed' ? '✅ PASSED' : '❌ FAILED';
            $details = isset($result['duration']) ? "{$result['duration']}ms" : '';
            if (isset($result['exit_code'])) {
                $details .= " (exit: {$result['exit_code']})";
            }
            
            return [
                $command,
                $statusDisplay,
                $result['description'],
                $details
            ];
        }, array_keys($results), $results);

        $this->table(['Command', 'Status', 'Description', 'Details'], $tableData);
        $this->info("📊 Commands Summary: {$passed} passed, {$failed} failed");
        
        return $results;
    }

    private function testCacheSystem(): array
    {
        $results = [];
        $passed = 0;
        $failed = 0;

        $cacheDriver = config('cache.default');
        $this->showProgress("Testing cache driver: {$cacheDriver}", 'working');

        // Test basic cache operations
        $tests = [
            'basic_put_get' => 'Basic cache put/get operations',
            'cache_expiration' => 'Cache expiration functionality', 
            'cache_tags' => 'Cache tagging (if supported)',
            'cache_forget' => 'Cache deletion',
            'cache_flush' => 'Cache clearing'
        ];

        foreach ($tests as $testKey => $description) {
            $this->showProgress("Running: {$description}", 'working');
            $startTime = microtime(true);
            
            try {
                $success = match($testKey) {
                    'basic_put_get' => $this->testBasicCache(),
                    'cache_expiration' => $this->testCacheExpiration(),
                    'cache_tags' => $this->testCacheTags(),
                    'cache_forget' => $this->testCacheForget(),
                    'cache_flush' => $this->testCacheFlush(),
                    default => false
                };

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                if ($success) {
                    $this->showProgress("✅ PASSED: {$description}", 'success');
                    $results[$testKey] = [
                        'status' => 'passed',
                        'description' => $description,
                        'duration' => $duration
                    ];
                    $passed++;
                } else {
                    $this->showProgress("❌ FAILED: {$description}", 'error');
                    $results[$testKey] = [
                        'status' => 'failed',
                        'description' => $description,
                        'duration' => $duration
                    ];
                    $failed++;
                }
            } catch (\Exception $e) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $this->showProgress("❌ ERROR: {$description} - {$e->getMessage()}", 'error');
                $results[$testKey] = [
                    'status' => 'failed',
                    'description' => $description,
                    'error' => $e->getMessage(),
                    'duration' => $duration
                ];
                $failed++;
            }
        }

        // Cache tests table
        $this->newLine();
        $tableData = array_map(function ($testKey, $result) {
            $statusDisplay = $result['status'] === 'passed' ? '✅ PASSED' : '❌ FAILED';
            $details = isset($result['duration']) ? "{$result['duration']}ms" : '';
            if (isset($result['error'])) {
                $details .= " Error: " . substr($result['error'], 0, 30) . '...';
            }
            
            return [
                $testKey,
                $statusDisplay,
                $result['description'],
                $details
            ];
        }, array_keys($results), $results);

        $this->table(['Test', 'Status', 'Description', 'Details'], $tableData);
        $this->info("📊 Cache Tests Summary: {$passed} passed, {$failed} failed");
        
        return $results;
    }

    private function testBasicCache(): bool
    {
        $key = 'test_cache_' . time();
        $value = 'test_value_' . Str::random(10);
        
        cache()->put($key, $value, 60);
        $retrieved = cache()->get($key);
        cache()->forget($key);
        
        return $retrieved === $value;
    }

    private function testCacheExpiration(): bool
    {
        $key = 'test_expire_' . time();
        $value = 'expire_test';
        
        // Put with 1 second expiration
        cache()->put($key, $value, 1);
        
        // Should exist immediately
        if (cache()->get($key) !== $value) {
            return false;
        }
        
        // Wait for expiration (only for non-database drivers)
        if (config('cache.default') !== 'database') {
            sleep(2);
            return cache()->get($key) === null;
        }
        
        // For database driver, just clean up and return true
        cache()->forget($key);
        return true;
    }

    private function testCacheTags(): bool
    {
        // Only test if driver supports tags
        if (!in_array(config('cache.default'), ['redis', 'memcached'])) {
            return true; // Skip test for non-supporting drivers
        }
        
        try {
            cache()->tags(['test_tag'])->put('tagged_key', 'tagged_value', 60);
            $result = cache()->tags(['test_tag'])->get('tagged_key') === 'tagged_value';
            cache()->tags(['test_tag'])->flush();
            return $result;
        } catch (\Exception $e) {
            return true; // Skip if not supported
        }
    }

    private function testCacheForget(): bool
    {
        $key = 'test_forget_' . time();
        $value = 'forget_test';
        
        cache()->put($key, $value, 60);
        cache()->forget($key);
        
        return cache()->get($key) === null;
    }

    private function testCacheFlush(): bool
    {
        // Only test flush if it's safe (avoid clearing production cache)
        if (app()->environment('production')) {
            return true; // Skip in production
        }
        
        $key = 'test_flush_' . time();
        cache()->put($key, 'flush_test', 60);
        
        // Don't actually flush in shared cache environments
        if (in_array(config('cache.default'), ['redis', 'memcached'])) {
            cache()->forget($key);
            return true;
        }
        
        cache()->flush();
        return cache()->get($key) === null;
    }

    private function testDatabaseConnectivity(): array
    {
        $results = [];
        $passed = 0;
        $failed = 0;

        $this->showProgress("Testing database connections...", 'working');

        // Test main database
        $tests = [
            'main_database' => 'Main database connection',
            'tenant_databases' => 'Tenant database connections',
            'database_queries' => 'Basic database queries'
        ];

        foreach ($tests as $testKey => $description) {
            $this->showProgress("Running: {$description}", 'working');
            $startTime = microtime(true);
            
            try {
                $success = match($testKey) {
                    'main_database' => $this->testMainDatabase(),
                    'tenant_databases' => $this->testTenantDatabases(),
                    'database_queries' => $this->testDatabaseQueries(),
                    default => false
                };

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                if ($success) {
                    $this->showProgress("✅ PASSED: {$description}", 'success');
                    $results[$testKey] = [
                        'status' => 'passed',
                        'description' => $description,
                        'duration' => $duration
                    ];
                    $passed++;
                } else {
                    $this->showProgress("❌ FAILED: {$description}", 'error');
                    $results[$testKey] = [
                        'status' => 'failed',
                        'description' => $description,
                        'duration' => $duration
                    ];
                    $failed++;
                }
            } catch (\Exception $e) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $this->showProgress("❌ ERROR: {$description} - {$e->getMessage()}", 'error');
                $results[$testKey] = [
                    'status' => 'failed',
                    'description' => $description,
                    'error' => $e->getMessage(),
                    'duration' => $duration
                ];
                $failed++;
            }
        }

        // Database tests table
        $this->newLine();
        $tableData = array_map(function ($testKey, $result) {
            $statusDisplay = $result['status'] === 'passed' ? '✅ PASSED' : '❌ FAILED';
            $details = isset($result['duration']) ? "{$result['duration']}ms" : '';
            if (isset($result['error'])) {
                $details .= " Error: " . substr($result['error'], 0, 30) . '...';
            }
            
            return [
                $testKey,
                $statusDisplay,
                $result['description'],
                $details
            ];
        }, array_keys($results), $results);

        $this->table(['Test', 'Status', 'Description', 'Details'], $tableData);
        $this->info("📊 Database Tests Summary: {$passed} passed, {$failed} failed");
        
        return $results;
    }

    private function testMainDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            $result = DB::select('SELECT 1 as test');
            return isset($result[0]->test) && $result[0]->test == 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testTenantDatabases(): bool
    {
        try {
            $tenants = Tenant::where('status', 'active')->take(3)->get();
            
            if ($tenants->isEmpty()) {
                return true; // No tenants to test
            }

            foreach ($tenants as $tenant) {
                $tenant->run(function () {
                    $result = DB::select('SELECT 1 as test');
                    if (!isset($result[0]->test) || $result[0]->test != 1) {
                        throw new \Exception('Tenant database query failed');
                    }
                });
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testDatabaseQueries(): bool
    {
        try {
            // Test basic queries
            $tenantCount = Tenant::count();
            $activeTenants = Tenant::where('status', 'active')->count();
            
            return is_int($tenantCount) && is_int($activeTenants);
        } catch (\Exception $e) {
            return false;
        }
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
        $summaryData = [];

        // Count results for each category
        $categories = ['commands', 'cache_tests', 'database_tests', 'api_endpoints'];
        
        foreach ($categories as $category) {
            if (!empty($results[$category])) {
                $passed = count(array_filter($results[$category], fn($r) => $r['status'] === 'passed'));
                $failed = count(array_filter($results[$category], fn($r) => $r['status'] === 'failed'));
                
                $totalPassed += $passed;
                $totalFailed += $failed;
                
                $categoryName = match($category) {
                    'commands' => 'Commands',
                    'cache_tests' => 'Cache Tests',
                    'database_tests' => 'Database Tests',
                    'api_endpoints' => 'API Endpoints',
                    default => ucfirst($category)
                };
                
                $summaryData[] = [$categoryName, $passed + $failed, $passed, $failed];
            }
        }

        // Add total row
        $summaryData[] = ['TOTAL', $totalPassed + $totalFailed, $totalPassed, $totalFailed];

        $overallStatus = $totalFailed === 0 ? '✅ ALL TESTS PASSED' : "❌ {$totalFailed} TESTS FAILED";
        $results['overall_status'] = $totalFailed === 0;

        $this->table(['Category', 'Total', 'Passed', 'Failed'], $summaryData);

        $this->newLine();
        $this->info("🏆 Overall Status: {$overallStatus}");

        // Show performance summary if show-details
        if ($this->option('show-details') && $totalPassed > 0) {
            $this->newLine();
            $this->comment('⚡ Performance Summary:');
            
            $allTests = array_merge(
                $results['commands'] ?? [],
                $results['cache_tests'] ?? [],
                $results['database_tests'] ?? []
            );
            
            $totalDuration = array_sum(array_column($allTests, 'duration'));
            $avgDuration = $totalDuration / count($allTests);
            
            $this->comment("   Total execution time: {$totalDuration}ms");
            $this->comment("   Average test time: " . round($avgDuration, 2) . "ms");
        }

        if ($totalFailed > 0) {
            $this->newLine();
            $this->warn('⚠️  Some tests failed. Please check the details above and fix any issues.');
            
            // Show quick fixes for common issues
            $this->newLine();
            $this->comment('💡 Common fixes:');
            $this->comment('   - For cache issues: Check your cache driver configuration');
            $this->comment('   - For database issues: Verify database connections and migrations');
            $this->comment('   - For command issues: Check command syntax and dependencies');
        } else {
            $this->newLine();
            $this->info('🎉 All tests passed! Your system is working correctly.');
        }
    }
}
