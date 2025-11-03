<?php

namespace ArtflowStudio\Tenancy\Commands\PWA;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantPWAService;
use Illuminate\Console\Command;
use Exception;

class TestPWACommand extends Command
{
    protected $signature = 'tenant:pwa:test 
                            {--tenant= : Tenant ID to test PWA for}
                            {--all : Test PWA for all enabled tenants}
                            {--interactive : Interactive mode to select tenant}
                            {--detailed : Show detailed test output}';

    protected $description = 'Test PWA functionality for tenant(s)';

    protected TenantPWAService $pwaService;

    public function __construct(TenantPWAService $pwaService)
    {
        parent::__construct();
        $this->pwaService = $pwaService;
    }

    public function handle(): int
    {
        $this->info('🧪 PWA Functionality Tests');
        $this->newLine();

        try {
            // Determine which tenant(s) to test
            if ($this->option('all')) {
                return $this->testAllTenants();
            } elseif ($this->option('tenant')) {
                return $this->testTenant($this->option('tenant'));
            } elseif ($this->option('interactive')) {
                return $this->interactiveMode();
            } else {
                $this->error('❌ Please specify --tenant=ID, --all, or --interactive');
                return 1;
            }
        } catch (Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function interactiveMode(): int
    {
        $this->info('📋 Select tenant to test PWA:');
        $this->newLine();

        // Only show tenants with PWA enabled
        $tenants = Tenant::where('pwa_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with PWA enabled found');
            $this->comment('💡 Enable PWA first: php artisan tenant:pwa:enable --interactive');
            return 1;
        }

        // Build choices array
        $choices = [];
        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'no-domain';
            $choices[$tenant->id] = "ID: {$tenant->id} | {$tenant->name} | {$domain}";
        }
        $choices['all'] = '🌐 Test ALL enabled tenants';
        $choices['cancel'] = '❌ Cancel';

        $this->newLine();
        $selected = $this->choice('Select tenant', $choices);

        if ($selected === 'cancel') {
            $this->info('Operation cancelled');
            return 0;
        }

        if ($selected === 'all') {
            return $this->testAllTenants();
        }

        return $this->testTenant($selected);
    }

    private function testTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant not found: {$tenantId}");
            return 1;
        }

        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        
        $this->info("🧪 Testing PWA for: {$tenant->name} ({$domain})");
        $this->newLine();

        // Check if PWA is enabled
        if (!$tenant->pwa_enabled) {
            $this->error('❌ PWA is not enabled for this tenant');
            $this->comment('💡 Enable PWA first: php artisan tenant:pwa:enable --tenant=' . $tenant->id);
            return 1;
        }

        // Run tests
        $results = $this->pwaService->testPWA($tenant);

        // Display test results
        $this->displayTestResults($results);

        // Overall result
        $this->newLine();
        if ($results['overall']) {
            $this->info('✅ All PWA tests passed!');
            $this->newLine();
            $this->comment('💡 Your PWA is fully functional and ready for use.');
            return 0;
        } else {
            $this->error('❌ Some PWA tests failed!');
            $this->newLine();
            $this->comment('💡 Fix the issues and run tests again.');
            $this->comment('💡 You can regenerate PWA files: php artisan tenant:pwa:enable --tenant=' . $tenant->id);
            return 1;
        }
    }

    private function testAllTenants(): int
    {
        $tenants = Tenant::where('pwa_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with PWA enabled found');
            return 1;
        }

        $this->info("🧪 Testing PWA for {$tenants->count()} tenant(s)...");
        $this->newLine();

        $passedCount = 0;
        $failedCount = 0;
        $summaryRows = [];

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'unknown';
            
            $this->line("🧪 Testing {$tenant->name} ({$domain})...");
            
            $results = $this->pwaService->testPWA($tenant);
            
            $passedTests = 0;
            $totalTests = count($results['tests']);
            
            foreach ($results['tests'] as $test) {
                if ($test['passed']) {
                    $passedTests++;
                }
            }
            
            $status = $results['overall'] ? '✅ Passed' : '❌ Failed';
            $testScore = "{$passedTests}/{$totalTests}";
            
            if ($results['overall']) {
                $passedCount++;
            } else {
                $failedCount++;
            }
            
            $summaryRows[] = [
                $tenant->id,
                $tenant->name,
                $domain,
                $testScore,
                $status
            ];
            
            // Show individual test results if detailed
            if ($this->option('detailed') && !$results['overall']) {
                foreach ($results['tests'] as $test) {
                    if (!$test['passed']) {
                        $this->line("   ❌ {$test['name']}: {$test['message']}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info('📊 Test Results Summary:');
        $this->table(
            ['ID', 'Name', 'Domain', 'Tests Passed', 'Overall Status'],
            $summaryRows
        );

        $this->newLine();
        $this->info('📈 Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tested', $tenants->count()],
                ['✅ Passed', $passedCount],
                ['❌ Failed', $failedCount]
            ]
        );

        return $failedCount > 0 ? 1 : 0;
    }

    private function displayTestResults(array $results): void
    {
        $rows = [];
        
        foreach ($results['tests'] as $testName => $test) {
            $status = $test['passed'] ? '✅ Pass' : '❌ Fail';
            $rows[] = [
                $test['name'],
                $status,
                $test['message']
            ];
        }
        
        $this->table(
            ['Test', 'Status', 'Details'],
            $rows
        );
    }
}
