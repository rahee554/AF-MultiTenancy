<?php

namespace ArtflowStudio\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TestTenantAuthentication extends Command
{
    protected $signature = 'af-tenancy:test-auth {domain?}';
    protected $description = 'Test tenant authentication and database context';

    public function handle()
    {
        $domain = $this->argument('domain') ?? 'tenancy1.local';
        
        $this->info("🔐 Testing Tenant Authentication Context");
        $this->info("Domain: {$domain}");
        $this->info(str_repeat('=', 50));

        // 1. Test Central Database Users
        $this->info("\n1️⃣ Testing Central Database Context:");
        try {
            $centralUsers = DB::connection('mysql')->table('users')->count();
            $this->info("✅ Central DB Users: {$centralUsers}");
        } catch (\Exception $e) {
            $this->error("❌ Central DB Error: " . $e->getMessage());
        }

        // 2. Find Tenant
        $this->info("\n2️⃣ Finding Tenant by Domain:");
        try {
            $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                $query->where('domain', $domain);
            })->first();

            if (!$tenant) {
                $this->error("❌ No tenant found for domain: {$domain}");
                return 1;
            }

            $this->info("✅ Tenant Found: {$tenant->id} ({$tenant->name})");
            $this->info("   Database: {$tenant->database}");
        } catch (\Exception $e) {
            $this->error("❌ Tenant lookup error: " . $e->getMessage());
            return 1;
        }

        // 3. Test Tenant Database Context
        $this->info("\n3️⃣ Testing Tenant Database Context:");
        try {
            // Initialize tenant context properly
            tenancy()->initialize($tenant);
            
            // Check if we're in tenant context
            if (tenant() && tenant()->id === $tenant->id) {
                $this->info("✅ Tenant context initialized successfully");
                
                // Count users in tenant database
                $tenantUsers = User::count();
                $this->info("✅ Tenant DB Users: {$tenantUsers}");
                
                // Show user details
                if ($tenantUsers > 0) {
                    $users = User::select('id', 'name', 'email')->get();
                    $this->info("📋 Tenant Users:");
                    foreach ($users as $user) {
                        $this->info("   - {$user->id}: {$user->name} ({$user->email})");
                    }
                } else {
                    $this->warn("⚠️  No users in tenant database - authentication will fail!");
                }
                
            } else {
                $this->error("❌ Failed to initialize tenant context");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Tenant DB Error: " . $e->getMessage());
        }

        // 4. Test Authentication Process Simulation
        $this->info("\n4️⃣ Testing Authentication Simulation:");
        try {
            if (tenant()) {
                // Try to find a user for authentication test
                $testUser = User::first();
                if ($testUser) {
                    $this->info("✅ Authentication would work with: {$testUser->email}");
                    $this->info("   Database Connection: " . DB::connection()->getDatabaseName());
                } else {
                    $this->warn("⚠️  No users available for authentication");
                    
                    // Offer to create test user
                    if ($this->confirm('Create a test user for authentication?')) {
                        $user = User::create([
                            'name' => 'Test User',
                            'email' => 'test@tenant.local',  
                            'password' => Hash::make('password'),
                        ]);
                        $this->info("✅ Created test user: test@tenant.local / password");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("❌ Auth simulation error: " . $e->getMessage());
        }

        // 5. Verify Database Isolation
        $this->info("\n5️⃣ Database Isolation Test:");
        try {
            // Get current database name
            $currentDb = DB::connection()->getDatabaseName();
            $expectedDb = $tenant->database;
            
            if ($currentDb === $expectedDb) {
                $this->info("✅ Database isolation working: {$currentDb}");
            } else {
                $this->error("❌ Database isolation FAILED!");
                $this->error("   Expected: {$expectedDb}");
                $this->error("   Actual: {$currentDb}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Database isolation test error: " . $e->getMessage());
        }

        // 6. Middleware Test
        $this->info("\n6️⃣ Middleware Registration Test:");
        $router = app('router');
        
        $middleware = [
            'tenant.auth' => \ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware::class,
            'tenant' => [\ArtflowStudio\Tenancy\Http\Middleware\SimpleTenantMiddleware::class],
            'tenant.homepage' => \ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware::class,
        ];

        foreach ($middleware as $name => $class) {
            if (is_array($class)) {
                $group = $router->getMiddlewareGroups()[$name] ?? null;
                if ($group) {
                    $this->info("✅ Middleware group '{$name}': " . count($group) . " middleware(s)");
                } else {
                    $this->error("❌ Middleware group '{$name}' not found");
                }
            } else {
                $alias = $router->getMiddleware()[$name] ?? null;
                if ($alias === $class) {
                    $this->info("✅ Middleware '{$name}': {$class}");
                } else {
                    $this->error("❌ Middleware '{$name}' not properly registered");
                }
            }
        }

        $this->info("\n🎯 Summary:");
        $this->info("- Central DB Users: {$centralUsers}");
        $this->info("- Tenant DB Users: " . (isset($tenantUsers) ? $tenantUsers : 'Unknown'));
        $this->info("- Tenant Context: " . (tenant() ? '✅ Active' : '❌ Inactive'));
        $this->info("- Database: " . (isset($currentDb) ? $currentDb : 'Unknown'));

        if (isset($tenantUsers) && $tenantUsers > 0) {
            $this->info("\n🎉 Authentication should work properly in tenant context!");
        } else {
            $this->warn("\n⚠️  Create users in tenant database for authentication to work!");
        }

        return 0;
    }
}
