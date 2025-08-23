<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Auth;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantSanctumService;
use Stancl\Tenancy\Database\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Test Sanctum Integration Command
 * 
 * Tests Laravel Sanctum integration with multi-tenancy
 */
class TestSanctumCommand extends Command
{
    protected $signature = 'tenancy:test-sanctum 
                          {--tenant= : Specific tenant ID to test}
                          {--verbose : Show detailed output}';

    protected $description = 'Test Laravel Sanctum integration with tenancy';

    protected $sanctumService;

    public function __construct(TenantSanctumService $sanctumService)
    {
        parent::__construct();
        $this->sanctumService = $sanctumService;
    }

    public function handle()
    {
        $this->info('🔐 Testing Sanctum Integration');
        $this->line('');

        // Get test tenant
        $tenant = $this->getTestTenant();
        if (!$tenant) {
            $this->error('❌ No test tenant available. Please create a tenant first.');
            return 1;
        }

        $this->info("Testing with tenant: {$tenant->id}");
        $this->line('');

        $results = [];
        $results[] = $this->testSanctumConfiguration($tenant);
        $results[] = $this->testTokenStatistics($tenant);
        $results[] = $this->testMiddlewareConfiguration($tenant);
        $results[] = $this->testTokenCleanup($tenant);

        // Display results
        $this->displayResults($results);

        return $this->hasFailures($results) ? 1 : 0;
    }

    /**
     * Get test tenant
     */
    protected function getTestTenant(): ?Tenant
    {
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return Tenant::first();
    }

    /**
     * Test Sanctum configuration
     */
    protected function testSanctumConfiguration(Tenant $tenant): array
    {
        $this->info('Testing Sanctum configuration...');
        
        try {
            $this->sanctumService->configureSanctumForTenant($tenant);
            
            return [
                'name' => 'Sanctum Configuration',
                'status' => 'passed',
                'message' => 'Sanctum configured successfully for tenant',
                'details' => []
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Sanctum Configuration',
                'status' => 'failed',
                'message' => 'Failed to configure Sanctum',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test token statistics
     */
    protected function testTokenStatistics(Tenant $tenant): array
    {
        $this->info('Testing token statistics...');
        
        try {
            $stats = $this->sanctumService->getTenantTokenStats($tenant);
            
            if (!is_array($stats)) {
                return [
                    'name' => 'Token Statistics',
                    'status' => 'failed',
                    'message' => 'Invalid statistics format returned'
                ];
            }

            $expectedKeys = ['total_tokens', 'active_tokens', 'expired_tokens', 'recent_tokens'];
            $missingKeys = array_diff($expectedKeys, array_keys($stats));
            
            if (empty($missingKeys)) {
                return [
                    'name' => 'Token Statistics',
                    'status' => 'passed',
                    'message' => 'Token statistics retrieved successfully',
                    'details' => [
                        'Total tokens' => $stats['total_tokens'],
                        'Active tokens' => $stats['active_tokens'],
                        'Expired tokens' => $stats['expired_tokens'],
                        'Recent tokens (7 days)' => $stats['recent_tokens']
                    ]
                ];
            } else {
                return [
                    'name' => 'Token Statistics',
                    'status' => 'failed',
                    'message' => 'Missing required statistics keys: ' . implode(', ', $missingKeys)
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Token Statistics',
                'status' => 'failed',
                'message' => 'Failed to retrieve token statistics',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test middleware configuration
     */
    protected function testMiddlewareConfiguration(Tenant $tenant): array
    {
        $this->info('Testing middleware configuration...');
        
        try {
            $middleware = $this->sanctumService->getTenantSanctumMiddleware($tenant);
            
            if (!is_array($middleware)) {
                return [
                    'name' => 'Middleware Configuration',
                    'status' => 'failed',
                    'message' => 'Invalid middleware configuration format'
                ];
            }

            if (in_array('auth:sanctum', $middleware)) {
                return [
                    'name' => 'Middleware Configuration',
                    'status' => 'passed',
                    'message' => 'Sanctum middleware configured correctly',
                    'details' => [
                        'Middleware stack' => implode(', ', $middleware)
                    ]
                ];
            } else {
                return [
                    'name' => 'Middleware Configuration',
                    'status' => 'failed',
                    'message' => 'auth:sanctum middleware not found in configuration'
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Middleware Configuration',
                'status' => 'failed',
                'message' => 'Failed to retrieve middleware configuration',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test token cleanup functionality
     */
    protected function testTokenCleanup(Tenant $tenant): array
    {
        $this->info('Testing token cleanup...');
        
        try {
            $deletedCount = $this->sanctumService->cleanupExpiredTokens($tenant);
            
            return [
                'name' => 'Token Cleanup',
                'status' => 'passed',
                'message' => 'Token cleanup executed successfully',
                'details' => [
                    'Expired tokens cleaned' => $deletedCount
                ]
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Token Cleanup',
                'status' => 'failed',
                'message' => 'Failed to execute token cleanup',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Display test results
     */
    protected function displayResults(array $results): void
    {
        $this->line('');
        $this->info('📊 Sanctum Test Results');
        $this->line('========================');

        $passed = 0;
        $failed = 0;

        foreach ($results as $result) {
            $icon = $result['status'] === 'passed' ? '✅' : '❌';
            $this->line("{$icon} {$result['name']}: {$result['message']}");
            
            if (isset($result['details']) && $this->option('verbose')) {
                foreach ($result['details'] as $key => $value) {
                    $this->line("   - {$key}: {$value}");
                }
            }
            
            if (isset($result['error']) && $this->option('verbose')) {
                $this->line("   Error: {$result['error']}");
            }
            
            if ($result['status'] === 'passed') {
                $passed++;
            } else {
                $failed++;
            }
            
            $this->line('');
        }

        $this->info("Summary: {$passed} passed, {$failed} failed");
        
        if ($failed === 0) {
            $this->info('🎉 All Sanctum tests passed!');
        } else {
            $this->warn('⚠️ Some Sanctum tests failed. Please review the results.');
        }
    }

    /**
     * Check if there are failures
     */
    protected function hasFailures(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['status'] !== 'passed') {
                return true;
            }
        }
        return false;
    }
}
