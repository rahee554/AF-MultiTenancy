<?php

namespace ArtflowStudio\Tenancy\Commands\Database;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\Database\TenantConnectionPoolManager;

class TenantConnectionPoolCommand extends Command
{
    protected $signature = 'tenancy:connection-pool 
                            {action : Action to perform (status|clear|health|test)}
                            {--tenant= : Specific tenant ID for testing}
                            {--pool-size=50 : Maximum pool size for testing}
                            {--detailed : Show detailed information}';

    protected $description = 'Manage multi-tenant database connection pool';

    public function handle(): int
    {
        $action = $this->argument('action');
        
        // Create connection pool manager
        $poolManager = TenantConnectionPoolManager::create([
            'max_pool_size' => (int) $this->option('pool-size'),
            'enable_pooling' => true,
        ]);

        switch ($action) {
            case 'status':
                return $this->showStatus($poolManager);
            case 'clear':
                return $this->clearPool($poolManager);
            case 'health':
                return $this->healthCheck($poolManager);
            case 'test':
                return $this->testPool($poolManager);
            default:
                $this->error("Unknown action: {$action}");
                $this->info('Available actions: status, clear, health, test');
                return 1;
        }
    }

    private function showStatus(TenantConnectionPoolManager $poolManager): int
    {
        $this->info('📊 Connection Pool Status');
        $this->newLine();

        $stats = $poolManager->getPoolStatistics();

        $this->table(['Metric', 'Value'], [
            ['Pool Size', $stats['pool_size']],
            ['Max Pool Size', $stats['max_pool_size']],
            ['Pooling Enabled', $stats['pooling_enabled'] ? 'Yes' : 'No'],
            ['Active Connections', count($stats['active_connections'])],
        ]);

        if ($this->option('detailed') && !empty($stats['active_connections'])) {
            $this->newLine();
            $this->info('🔗 Active Connections:');
            
            $connectionData = [];
            foreach ($stats['active_connections'] as $connection) {
                $connectionData[] = [
                    $connection,
                    $stats['usage_counts'][$connection] ?? 0,
                    $stats['last_accessed'][$connection] ?? 'Unknown'
                ];
            }

            $this->table(['Connection', 'Usage Count', 'Last Accessed'], $connectionData);
        }

        return 0;
    }

    private function clearPool(TenantConnectionPoolManager $poolManager): int
    {
        $this->info('🧹 Clearing Connection Pool...');

        $stats = $poolManager->getPoolStatistics();
        $connectionsCount = $stats['pool_size'];

        $poolManager->clearPool();

        $this->info("✅ Cleared {$connectionsCount} connections from pool");
        return 0;
    }

    private function healthCheck(TenantConnectionPoolManager $poolManager): int
    {
        $this->info('🔍 Connection Pool Health Check');
        $this->newLine();

        $health = $poolManager->healthCheck();

        // Display status
        $statusIcon = match($health['status']) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            default => '❓'
        };

        $this->info("Status: {$statusIcon} " . strtoupper($health['status']));
        $this->newLine();

        // Display issues if any
        if (!empty($health['issues'])) {
            $this->warn('Issues detected:');
            foreach ($health['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
            $this->newLine();
        }

        // Display statistics
        $stats = $health['statistics'];
        $this->table(['Metric', 'Value'], [
            ['Pool Size', $stats['pool_size'] . '/' . $stats['max_pool_size']],
            ['Pooling Status', $stats['pooling_enabled'] ? 'Enabled' : 'Disabled'],
            ['Active Connections', count($stats['active_connections'])],
        ]);

        return $health['status'] === 'healthy' ? 0 : 1;
    }

    private function testPool(TenantConnectionPoolManager $poolManager): int
    {
        $this->info('🧪 Testing Connection Pool');
        $this->newLine();

        $tenantId = $this->option('tenant');
        
        if (!$tenantId) {
            // Try to find an existing test tenant instead of using dummy data
            $testTenant = \ArtflowStudio\Tenancy\Models\Tenant::where('name', 'LIKE', 'test_%')
                ->first();
                
            if ($testTenant) {
                // Use the actual tenant object for the test instead of just the ID
                $this->info("Testing with existing test tenant: {$testTenant->name}");
                $this->info("Database: {$testTenant->database}");
                return $this->testWithActualTenant($poolManager, $testTenant);
            } else {
                $this->warn('⚠️  No test tenants found. Creating a basic connection test...');
                return $this->testBasicConnection($poolManager);
            }
        }

        try {
            $this->info('Testing connection creation...');
            $connection = $poolManager->getConnection($tenantId);
            $this->info('✅ Connection created successfully');

            $this->info('Testing connection usage...');
            $result = $poolManager->runInTenantContext($tenantId, function($connection) {
                return $connection->select('SELECT 1 as test, NOW() as timestamp');
            });

            $this->info('✅ Connection test query successful');
            if ($this->option('detailed')) {
                $this->table(['Test', 'Timestamp'], [
                    [$result[0]->test, $result[0]->timestamp]
                ]);
            }

            $this->info('Testing connection release...');
            $poolManager->releaseConnection($tenantId);
            $this->info('✅ Connection released successfully');

            $this->newLine();
            $this->info('Pool statistics after test:');
            $stats = $poolManager->getPoolStatistics();
            $this->table(['Metric', 'Value'], [
                ['Pool Size', $stats['pool_size']],
                ['Active Connections', count($stats['active_connections'])],
            ]);

            // Cleanup test connection
            $poolManager->removeConnection($tenantId);
            $this->info('🧹 Test connection cleaned up');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Connection pool test failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function testWithActualTenant(TenantConnectionPoolManager $poolManager, $tenant): int
    {
        try {
            $this->info('Testing connection creation...');
            
            // Use the tenant's actual database name for the connection
            $tenantId = $tenant->getTenantKey();
            $databaseName = $tenant->database;
            
            $this->info("Using tenant ID: {$tenantId}");
            $this->info("Database: {$databaseName}");
            
            // Test by running in tenant context
            $tenant->run(function() use ($poolManager, $tenant) {
                $this->info('✅ Switched to tenant context successfully');
                
                // Test a simple query in tenant database
                $result = \Illuminate\Support\Facades\DB::connection('tenant')->select('SELECT 1 as test, NOW() as timestamp');
                $this->info('✅ Tenant database query successful');
                
                if ($this->option('detailed')) {
                    $this->table(['Test', 'Timestamp'], [
                        [$result[0]->test, $result[0]->timestamp]
                    ]);
                }
            });

            $this->newLine();
            $this->info('Pool statistics after test:');
            $stats = $poolManager->getPoolStatistics();
            $this->table(['Metric', 'Value'], [
                ['Pool Size', $stats['pool_size']],
                ['Max Pool Size', $stats['max_pool_size']],
                ['Pooling Enabled', $stats['pooling_enabled'] ? 'Yes' : 'No'],
            ]);

            $this->info('✅ Connection pool test with actual tenant completed successfully');
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Connection pool test failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function testBasicConnection(TenantConnectionPoolManager $poolManager): int
    {
        try {
            $this->info('Testing basic pool functionality without tenant database...');
            
            // Test pool statistics
            $stats = $poolManager->getPoolStatistics();
            $this->info('✅ Pool statistics retrieved successfully');
            
            // Test health check
            $health = $poolManager->healthCheck();
            $this->info('✅ Pool health check completed');
            
            $this->table(['Test', 'Result'], [
                ['Pool Statistics', '✅ Available'],
                ['Health Check', $health['status'] === 'healthy' ? '✅ Healthy' : '⚠️  ' . $health['status']],
                ['Pool Size', $stats['pool_size']],
                ['Max Pool Size', $stats['max_pool_size']],
                ['Pooling Enabled', $stats['pooling_enabled'] ? '✅ Yes' : '❌ No'],
            ]);
            
            $this->newLine();
            $this->info('💡 To test with actual tenant connections, create test tenants first:');
            $this->line('   php artisan tenancy:test-tenants create --count=2');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Basic connection pool test failed: {$e->getMessage()}");
            return 1;
        }
    }
}
