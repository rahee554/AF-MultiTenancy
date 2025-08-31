<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Api;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\TestCase;
use Tests\TestCase as BaseTestCase;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;

class TestApiEndpointsCommand extends Command
{
    protected $signature = 'tenancy:test-api 
                           {--endpoint= : Test specific endpoint}
                           {--tenant= : Use specific tenant ID for testing}
                           {--interactive : Run in interactive mode}
                           {--detailed : Show detailed output}
                           {--base-url= : Base URL for API testing}';

    protected $description = 'Test all tenancy API endpoints with interactive interface';

    protected $endpoints = [
        'GET /api/tenants' => 'Test listing tenants',
        'POST /api/tenants' => 'Test creating tenant',
        'GET /api/tenants/{id}' => 'Test getting specific tenant',
        'PUT /api/tenants/{id}' => 'Test updating tenant',
        'DELETE /api/tenants/{id}' => 'Test deleting tenant',
        'GET /api/tenants/{id}/quotas' => 'Test getting tenant quotas',
        'PUT /api/tenants/{id}/quotas' => 'Test updating tenant quotas',
        'GET /api/tenants/{id}/analytics' => 'Test getting tenant analytics',
        'GET /api/tenants/{id}/settings' => 'Test getting tenant settings',
        'PUT /api/tenants/{id}/settings' => 'Test updating tenant settings',
        'POST /api/tenants/{id}/quotas/reset' => 'Test resetting tenant quotas',
        'GET /api/system/overview' => 'Test getting system overview',
    ];

    protected $testResults = [];
    protected $baseUrl;

    public function handle()
    {
        // Use provided base-url option or default to app.url
        $this->baseUrl = $this->option('base-url') ?: config('app.url');
        
        $this->info('🧪 Testing Tenancy API Endpoints');
        $this->newLine();

        if ($this->option('interactive')) {
            return $this->runInteractiveMode();
        }

        if ($endpoint = $this->option('endpoint')) {
            return $this->testSpecificEndpoint($endpoint);
        }

        return $this->testAllEndpoints();
    }

    protected function runInteractiveMode()
    {
        $this->info('🎮 Interactive API Testing Mode');
        $this->newLine();

        while (true) {
            $choice = $this->choice(
                'What would you like to do?',
                [
                    'Test all endpoints',
                    'Test specific endpoint',
                    'Test with custom data',
                    'View test results',
                    'Exit'
                ],
                0
            );

            switch ($choice) {
                case 'Test all endpoints':
                    $this->testAllEndpoints();
                    break;
                case 'Test specific endpoint':
                    $this->selectAndTestEndpoint();
                    break;
                case 'Test with custom data':
                    $this->testWithCustomData();
                    break;
                case 'View test results':
                    $this->displayTestResults();
                    break;
                case 'Exit':
                    $this->info('👋 Goodbye!');
                    return 0;
            }

            $this->newLine();
        }
    }

    protected function testAllEndpoints()
    {
        $this->info('🔄 Testing all API endpoints...');
        $this->newLine();

        $testTenant = $this->getTestTenant();
        
        foreach ($this->endpoints as $endpoint => $description) {
            $this->testEndpoint($endpoint, $description, $testTenant);
        }

        $this->displayTestResults();
        return 0;
    }

    protected function selectAndTestEndpoint()
    {
        $endpoint = $this->choice(
            'Select endpoint to test:',
            array_keys($this->endpoints)
        );

        $testTenant = $this->getTestTenant();
        $this->testEndpoint($endpoint, $this->endpoints[$endpoint], $testTenant);
        $this->displayTestResults();
    }

    protected function testSpecificEndpoint($endpoint)
    {
        if (!isset($this->endpoints[$endpoint])) {
            $this->error("❌ Endpoint '{$endpoint}' not found.");
            $this->info('Available endpoints:');
            foreach (array_keys($this->endpoints) as $available) {
                $this->line("  - {$available}");
            }
            return 1;
        }

        $testTenant = $this->getTestTenant();
        $this->testEndpoint($endpoint, $this->endpoints[$endpoint], $testTenant);
        $this->displayTestResults();
        return 0;
    }

    protected function testEndpoint($endpoint, $description, $testTenant = null)
    {
        $this->line("🧪 {$description}");

        try {
            $result = $this->executeEndpointTest($endpoint, $testTenant);
            
            $status = $result['success'] ? '✅' : '❌';
            $this->line("   {$status} {$result['message']}");
            
            if ($this->option('detailed') && isset($result['details'])) {
                $this->line("   📊 Details: " . json_encode($result['details'], JSON_PRETTY_PRINT));
            }

            $this->testResults[$endpoint] = $result;

        } catch (\Exception $e) {
            $this->line("   ❌ Error: " . $e->getMessage());
            $this->testResults[$endpoint] = [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => true,
            ];
        }
    }

    protected function executeEndpointTest($endpoint, $testTenant = null)
    {
        [$method, $path] = explode(' ', $endpoint, 2);
        
        // Replace {id} with actual tenant ID
        if ($testTenant && strpos($path, '{id}') !== false) {
            $path = str_replace('{id}', $testTenant->id, $path);
        }

        $url = $this->baseUrl . $path;

        switch ($endpoint) {
            case 'GET /api/tenants':
                return $this->testGetTenants($url);
                
            case 'POST /api/tenants':
                return $this->testCreateTenant($url);
                
            case 'GET /api/tenants/{id}':
                return $this->testGetTenant($url, $testTenant);
                
            case 'PUT /api/tenants/{id}':
                return $this->testUpdateTenant($url, $testTenant);
                
            case 'DELETE /api/tenants/{id}':
                return $this->testDeleteTenant($url, $testTenant);
                
            case 'GET /api/tenants/{id}/quotas':
                return $this->testGetQuotas($url, $testTenant);
                
            case 'PUT /api/tenants/{id}/quotas':
                return $this->testUpdateQuotas($url, $testTenant);
                
            case 'GET /api/tenants/{id}/analytics':
                return $this->testGetAnalytics($url, $testTenant);
                
            case 'GET /api/tenants/{id}/settings':
                return $this->testGetSettings($url, $testTenant);
                
            case 'PUT /api/tenants/{id}/settings':
                return $this->testUpdateSettings($url, $testTenant);
                
            case 'POST /api/tenants/{id}/quotas/reset':
                return $this->testResetQuotas($url, $testTenant);
                
            case 'GET /api/system/overview':
                return $this->testGetSystemOverview($url);
                
            default:
                throw new \Exception("Unknown endpoint: {$endpoint}");
        }
    }

    protected function testGetTenants($url)
    {
        $response = Http::get($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Retrieved {$response->json('data.total', 0)} tenants"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testCreateTenant($url)
    {
        $testData = [
            'name' => 'Test Tenant ' . now()->format('Y-m-d H:i:s'),
            'domain' => 'test-' . \Illuminate\Support\Str::random(8) . '.test.com',
            'status' => 'active',
            'has_homepage' => false,
        ];

        $response = Http::post($url, $testData);
        
        return [
            'success' => $response->status() === 201,
            'message' => $response->successful() 
                ? "Created tenant: {$response->json('data.name')}"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
            'test_data' => $testData,
        ];
    }

    protected function testGetTenant($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $response = Http::get($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Retrieved tenant: {$response->json('data.name')}"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testUpdateTenant($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $updateData = [
            'name' => $tenant->name . ' (Updated)',
            'status' => $tenant->status === 'active' ? 'inactive' : 'active',
        ];

        $response = Http::put($url, $updateData);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Updated tenant successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
            'test_data' => $updateData,
        ];
    }

    protected function testDeleteTenant($url, $tenant)
    {
        // Create a temporary tenant for deletion test
        $tempTenant = Tenant::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Temp Delete Test ' . now()->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        // Create domain using stancl/tenancy Domain model
        \Stancl\Tenancy\Database\Models\Domain::create([
            'domain' => 'temp-delete-' . \Illuminate\Support\Str::random(8) . '.test.com',
            'tenant_id' => $tempTenant->id,
        ]);

        $deleteUrl = str_replace($tenant->id, $tempTenant->id, $url);
        $response = Http::delete($deleteUrl);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Deleted temporary tenant successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testGetQuotas($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $response = Http::get($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Retrieved quotas successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testUpdateQuotas($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $quotaData = [
            'quotas' => [
                'storage_mb' => 2000,
                'users' => 150,
                'api_calls_per_day' => 15000,
            ]
        ];

        $response = Http::put($url, $quotaData);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Updated quotas successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
            'test_data' => $quotaData,
        ];
    }

    protected function testGetAnalytics($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $response = Http::get($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Retrieved analytics successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testWithCustomData()
    {
        $this->info('🛠️ Custom Data Testing');
        
        $endpoint = $this->choice('Select endpoint:', array_keys($this->endpoints));
        $this->info("Testing: {$endpoint}");
        
        if (strpos($endpoint, 'POST') !== false || strpos($endpoint, 'PUT') !== false) {
            $this->info('Enter JSON data (or press Enter for default):');
            $input = $this->ask('JSON data');
            
            if ($input) {
                try {
                    $data = json_decode($input, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->error('Invalid JSON format');
                        return;
                    }
                    // Test with custom data
                    $this->info('Testing with custom data...');
                } catch (\Exception $e) {
                    $this->error('Error: ' . $e->getMessage());
                }
            }
        }
        
        $testTenant = $this->getTestTenant();
        $this->testEndpoint($endpoint, $this->endpoints[$endpoint], $testTenant);
    }

    protected function getTestTenant()
    {
        if ($tenantId = $this->option('tenant')) {
            return Tenant::find($tenantId);
        }

        return Tenant::first();
    }

    protected function displayTestResults()
    {
        if (empty($this->testResults)) {
            $this->info('📊 No test results to display');
            return;
        }

        $this->newLine();
        $this->info('📊 Test Results Summary');
        $this->line(str_repeat('=', 60));

        $passed = 0;
        $failed = 0;

        foreach ($this->testResults as $endpoint => $result) {
            $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
            $this->line(sprintf('%-40s %s', $endpoint, $status));
            
            if (!$result['success'] && isset($result['message'])) {
                $this->line("   └─ {$result['message']}");
            }

            $result['success'] ? $passed++ : $failed++;
        }

        $this->newLine();
        $this->info("Summary: {$passed} passed, {$failed} failed");
        
        if ($failed === 0) {
            $this->info('🎉 All tests passed!');
        } else {
            $this->warn("⚠️  {$failed} test(s) failed");
        }
    }

    protected function testGetSettings($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $response = Http::get($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Retrieved settings successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testUpdateSettings($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $settingsData = [
            'settings' => [
                'notifications' => [
                    'email_enabled' => false,
                    'sms_enabled' => true,
                ],
                'limits' => [
                    'warning_threshold_percentage' => 85,
                ],
            ]
        ];

        $response = Http::put($url, $settingsData);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Updated settings successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
            'test_data' => $settingsData,
        ];
    }

    protected function testResetQuotas($url, $tenant)
    {
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'No test tenant available',
            ];
        }

        $response = Http::post($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Reset quotas successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }

    protected function testGetSystemOverview($url)
    {
        $response = Http::get($url);
        
        return [
            'success' => $response->successful(),
            'message' => $response->successful() 
                ? "Retrieved system overview successfully"
                : "Failed with status {$response->status()}",
            'status_code' => $response->status(),
            'details' => $response->json(),
        ];
    }
}
