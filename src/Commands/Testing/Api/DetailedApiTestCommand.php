<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Api;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Http\Controllers\Api\TenantApiController;
use ArtflowStudio\Tenancy\Services\TenantResourceQuotaService;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;
use Stancl\Tenancy\Database\Models\Tenant;
use Illuminate\Http\Request;

class DetailedApiTestCommand extends Command
{
    protected $signature = 'tenancy:detailed-api-test';
    protected $description = 'Detailed test with error inspection';

    public function handle()
    {
        $this->info('🔍 Detailed API Error Analysis');
        $this->newLine();

        try {
            $controller = app(TenantApiController::class);
            $this->info('✅ Controller instantiated');

            // Test the show method specifically
            $this->testShowMethod($controller);
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    protected function testIndexMethod($controller)
    {
        $this->info('🧪 Testing Index Method...');
        
        try {
            $request = new Request();
            $response = $controller->index($request);
            
            $this->info('Response Status: ' . $response->getStatusCode());
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            if ($response->getStatusCode() === 500) {
                $this->error('❌ 500 Error Details:');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->info('✅ Success: ' . ($data['success'] ? 'true' : 'false'));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Exception in index: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    protected function testShowMethod($controller)
    {
        $this->info('🧪 Testing Show Method...');
        
        try {
            $tenant = Tenant::first();
            if (!$tenant) {
                $this->error('❌ No tenant found for testing');
                return;
            }
            
            $this->info('Testing with tenant ID: ' . $tenant->id);
            
            $response = $controller->show($tenant->id);
            
            $this->info('Response Status: ' . $response->getStatusCode());
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            if ($response->getStatusCode() === 500) {
                $this->error('❌ 500 Error Details:');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->info('✅ Success: ' . ($data['success'] ? 'true' : 'false'));
                if (isset($data['data']['name'])) {
                    $this->info('Tenant name: ' . $data['data']['name']);
                }
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Exception in show: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
