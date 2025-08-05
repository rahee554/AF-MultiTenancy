<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Models\Domain;
use Illuminate\Support\Facades\DB;

class DebugTenantConnectionCommand extends Command
{
    protected $signature = 'af-tenancy:debug-connection {domain}';
    protected $description = 'Debug tenant connection and database issues';

    public function handle()
    {
        $domainName = $this->argument('domain');
        
        $this->info("🔍 Debugging tenant connection for domain: {$domainName}");
        $this->newLine();

        // 1. Check if domain exists
        $this->info('1. Checking domain existence...');
        $domain = Domain::where('domain', $domainName)->first();
        
        if (!$domain) {
            $this->error("❌ Domain '{$domainName}' not found in domains table");
            return 1;
        }
        
        $this->info("✅ Domain found: {$domain->domain}");
        $this->info("   Tenant ID: {$domain->tenant_id}");
        
        // 2. Check if tenant exists
        $this->info('2. Checking tenant existence...');
        $tenant = $domain->tenant;
        
        if (!$tenant) {
            $this->error("❌ Tenant not found for domain");
            return 1;
        }
        
        $this->info("✅ Tenant found: {$tenant->name}");
        $this->info("   Database: {$tenant->database}");
        $this->info("   Status: {$tenant->status}");
        $this->info("   Has Homepage: " . ($tenant->has_homepage ? 'Yes' : 'No'));

        // 3. Test central database connection
        $this->info('3. Testing central database connection...');
        try {
            $centralConnection = config('tenancy.database.central_connection', 'mysql');
            $userCount = DB::connection($centralConnection)->table('users')->count();
            $this->info("✅ Central database connected: {$userCount} users found");
        } catch (\Exception $e) {
            $this->error("❌ Central database error: " . $e->getMessage());
        }

        // 4. Test tenant database connection
        $this->info('4. Testing tenant database connection...');
        try {
            // Initialize tenancy
            $tenant->makeCurrent();
            
            $this->info("✅ Tenant initialized successfully");
            
            // Test tenant database
            $tenantUserCount = DB::table('users')->count();
            $this->info("✅ Tenant database connected: {$tenantUserCount} users found");
            
            // Check current database name
            $currentDb = DB::connection()->getDatabaseName();
            $this->info("   Current database: {$currentDb}");
            
        } catch (\Exception $e) {
            $this->error("❌ Tenant database error: " . $e->getMessage());
        }

        // 5. Test tenancy functions
        $this->info('5. Testing tenancy helper functions...');
        try {
            if (function_exists('tenant')) {
                $currentTenant = tenant();
                if ($currentTenant) {
                    $this->info("✅ tenant() function works: {$currentTenant->name}");
                } else {
                    $this->warn("⚠️  tenant() function returns null");
                }
            } else {
                $this->error("❌ tenant() function not available");
            }
        } catch (\Exception $e) {
            $this->error("❌ Tenancy function error: " . $e->getMessage());
        }

        // 6. Check authentication state
        $this->info('6. Checking authentication state...');
        try {
            if (auth()->check()) {
                $user = auth()->user();
                $this->info("✅ User authenticated: {$user->email}");
                $this->info("   User ID: {$user->id}");
                $this->info("   Database: " . DB::connection()->getDatabaseName());
            } else {
                $this->info("ℹ️  No user authenticated");
            }
        } catch (\Exception $e) {
            $this->error("❌ Authentication check error: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('🎯 Debug Summary:');
        $this->line("Domain: {$domainName}");
        $this->line("Tenant: {$tenant->name}");
        $this->line("Database: {$tenant->database}");
        $this->line("Current DB: " . (DB::connection()->getDatabaseName() ?? 'Unknown'));

        return 0;
    }
}
