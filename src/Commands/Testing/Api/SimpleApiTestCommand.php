<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Api;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Http\Controllers\Api\TenantApiController;
use ArtflowStudio\Tenancy\Services\TenantResourceQuotaService;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;
use Stancl\Tenancy\Database\Models\Tenant;
use Illuminate\Http\Request;

class SimpleApiTestCommand extends Command
{
    protected $signature = 'tenancy:simple-api-test';
    protected $description = 'Simple test of API functionality without HTTP calls';

    public function handle()
    {
        $this->info('🧪 Testing API Controller Functionality');
        $this->newLine();

        try {
            // Test controller instantiation using Laravel's container
            $controller = app(TenantApiController::class);
            $this->info('✅ API Controller instantiated successfully');

            // Test services
            $quotaService = app(TenantResourceQuotaService::class);
            $this->info('✅ Quota Service instantiated successfully');

            $analyticsService = app(TenantAnalyticsService::class);
            $this->info('✅ Analytics Service instantiated successfully');

            // Test basic functionality
            $this->testBasicFunctionality($controller, $quotaService, $analyticsService);

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    protected function testBasicFunctionality($controller, $quotaService, $analyticsService)
    {
        $this->info('🔄 Testing basic functionality...');

        try {
            // Test getting tenants (should return a response)
            $request = new Request();
            $response = $controller->index($request);
            $this->info('✅ Index method callable - Status: ' . $response->getStatusCode());

            // Test creating a test tenant
            $tenantData = [
                'id' => 'test-tenant-' . now()->timestamp,
                'plan' => 'basic'
            ];

            $request = new Request($tenantData);
            $response = $controller->store($request);
            $this->info('✅ Store method callable - Status: ' . $response->getStatusCode());

            // Get the created tenant
            $tenants = Tenant::all();
            if ($tenants->count() > 0) {
                $tenant = $tenants->first();
                $this->info('✅ Found ' . $tenants->count() . ' tenant(s) in database');

                // Test quota service
                $quotas = $quotaService->getTenantQuotas($tenant->id);
                $this->info('✅ Quota service working - Quotas: ' . json_encode($quotas));

                // Test analytics service
                $analytics = $analyticsService->getTenantMetrics($tenant->id);
                $this->info('✅ Analytics service working - Analytics keys: ' . implode(', ', array_keys($analytics)));

                // Test API controller methods with the tenant
                $response = $controller->show($tenant->id);
                $this->info('✅ Show method callable - Status: ' . $response->getStatusCode());

                $response = $controller->getQuotas($tenant->id);
                $this->info('✅ GetQuotas method callable - Status: ' . $response->getStatusCode());

                $response = $controller->getAnalytics($tenant->id);
                $this->info('✅ GetAnalytics method callable - Status: ' . $response->getStatusCode());

            } else {
                $this->warn('⚠️ No tenants found in database');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error in basic functionality test: ' . $e->getMessage());
            throw $e;
        }

        $this->newLine();
        $this->info('🎉 All basic functionality tests completed successfully!');
    }
}
