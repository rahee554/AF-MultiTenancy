<?php

namespace ArtflowStudio\Tenancy\Tests;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class TenancySystemTest extends BaseTestCase
{
    use RefreshDatabase;

    protected TenantService $tenantService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantService = app(TenantService::class);
    }

    /** @test */
    public function it_can_create_tenant_with_database()
    {
        $tenant = $this->tenantService->createTenant(
            name: 'Test Company',
            domain: 'test-company.local',
            status: 'active'
        );

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Company', $tenant->name);
        $this->assertEquals('active', $tenant->status);
        
        // Check database was created
        $databaseName = $tenant->getDatabaseName();
        $this->assertTrue($this->databaseExists($databaseName));
        
        // Check domain was created
        $this->assertEquals('test-company.local', $tenant->domains()->first()->domain);
    }

    /** @test */
    public function it_can_initialize_tenant_context()
    {
        $tenant = $this->tenantService->createTenant(
            name: 'Context Test',
            domain: 'context-test.local'
        );

        // Test tenant context switching
        Tenancy::initialize($tenant);
        
        // Should be able to use the tenant database
        $connection = DB::connection('tenant');
        $this->assertNotNull($connection);
        
        Tenancy::end();
    }

    /** @test */
    public function it_can_migrate_tenant_database()
    {
        $tenant = $this->tenantService->createTenant(
            name: 'Migration Test',
            domain: 'migration-test.local'
        );

        // Run migrations
        $this->tenantService->migrateTenant($tenant);
        
        // Check tenant context and verify migrations ran
        $tenant->run(function () {
            $migrations = DB::table('migrations')->count();
            $this->assertGreaterThan(0, $migrations);
        });
    }

    /** @test */
    public function it_can_handle_multiple_tenants()
    {
        $tenants = [];
        
        // Create multiple tenants
        for ($i = 1; $i <= 3; $i++) {
            $tenants[] = $this->tenantService->createTenant(
                name: "Multi Tenant {$i}",
                domain: "multi-tenant-{$i}.local"
            );
        }

        $this->assertCount(3, $tenants);
        
        // Each should have its own database
        foreach ($tenants as $tenant) {
            $this->assertTrue($this->databaseExists($tenant->getDatabaseName()));
        }
    }

    /** @test */
    public function it_can_delete_tenant_and_database()
    {
        $tenant = $this->tenantService->createTenant(
            name: 'Delete Test',
            domain: 'delete-test.local'
        );

        $databaseName = $tenant->getDatabaseName();
        $this->assertTrue($this->databaseExists($databaseName));

        // Delete tenant
        $this->tenantService->deleteTenant($tenant);
        
        // Database should be gone
        $this->assertFalse($this->databaseExists($databaseName));
        
        // Tenant record should be gone
        $this->assertNull(Tenant::find($tenant->id));
    }

    /** @test */
    public function it_handles_database_connection_pooling()
    {
        $tenant = $this->tenantService->createTenant(
            name: 'Connection Test',
            domain: 'connection-test.local'
        );

        // Test multiple connections don't conflict
        for ($i = 0; $i < 5; $i++) {
            Tenancy::initialize($tenant);
            $connection = DB::connection('tenant');
            $this->assertNotNull($connection);
            Tenancy::end();
        }
    }

    /** @test */
    public function it_validates_tenant_status_workflow()
    {
        $tenant = $this->tenantService->createTenant(
            name: 'Status Test',
            domain: 'status-test.local',
            status: 'active'
        );

        $this->assertTrue($tenant->isActive());

        // Deactivate
        $this->tenantService->deactivateTenant($tenant);
        $tenant->refresh();
        $this->assertFalse($tenant->isActive());

        // Reactivate
        $this->tenantService->activateTenant($tenant);
        $tenant->refresh();
        $this->assertTrue($tenant->isActive());
    }

    /** @test */
    public function it_handles_custom_database_names()
    {
        $customDbName = 'custom_tenant_db_' . time();
        
        $tenant = $this->tenantService->createTenant(
            name: 'Custom DB Test',
            domain: 'custom-db.local',
            customDatabase: $customDbName
        );

        $this->assertEquals($customDbName, $tenant->getDatabaseName());
        $this->assertTrue($this->databaseExists($customDbName));
    }

    /** @test */
    public function it_prevents_sql_injection_in_database_names()
    {
        $this->expectException(\Exception::class);
        
        $this->tenantService->createTenant(
            name: 'SQL Inject Test',
            domain: 'sql-inject.local',
            customDatabase: 'test`; DROP DATABASE test; --'
        );
    }

    /** @test */
    public function it_handles_concurrent_tenant_creation()
    {
        $promises = [];
        
        // Simulate concurrent tenant creation
        for ($i = 1; $i <= 5; $i++) {
            $promises[] = function() use ($i) {
                return $this->tenantService->createTenant(
                    name: "Concurrent Tenant {$i}",
                    domain: "concurrent-{$i}.local"
                );
            };
        }

        $results = [];
        foreach ($promises as $promise) {
            $results[] = $promise();
        }

        $this->assertCount(5, $results);
        
        // All should have unique databases
        $databases = array_map(fn($t) => $t->getDatabaseName(), $results);
        $this->assertEquals(5, count(array_unique($databases)));
    }

    protected function databaseExists(string $databaseName): bool
    {
        try {
            $result = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test databases
        try {
            $testDatabases = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME LIKE '%test%' OR SCHEMA_NAME LIKE 'tenant_%'");
            foreach ($testDatabases as $db) {
                if (strpos($db->SCHEMA_NAME, 'tenant_') === 0 || strpos($db->SCHEMA_NAME, 'test') !== false) {
                    DB::statement("DROP DATABASE IF EXISTS `{$db->SCHEMA_NAME}`");
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        parent::tearDown();
    }
}
