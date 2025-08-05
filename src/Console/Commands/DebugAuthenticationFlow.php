<?php

namespace ArtflowStudio\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DebugAuthenticationFlow extends Command
{
    protected $signature = 'af-tenancy:debug-auth {domain?}';
    protected $description = 'Debug the full authentication flow to find issues';

    public function handle()
    {
        $domain = $this->argument('domain') ?? 'tenancy1.local';
        
        $this->info("🐛 Debugging Authentication Flow");
        $this->info("Domain: {$domain}");
        $this->info(str_repeat('=', 60));

        // 1. Test Middleware Chain
        $this->info("\n1️⃣ Testing Middleware Chain:");
        $this->debugMiddlewareChain();

        // 2. Test Route Resolution
        $this->info("\n2️⃣ Testing Route Resolution:");
        $this->debugRouteResolution($domain);

        // 3. Test Auth Configuration
        $this->info("\n3️⃣ Testing Auth Configuration:");
        $this->debugAuthConfiguration();

        // 4. Test User Provider
        $this->info("\n4️⃣ Testing User Provider:");
        $this->debugUserProvider($domain);

        // 5. Test Database Connections
        $this->info("\n5️⃣ Testing Database Connections:");
        $this->debugDatabaseConnections($domain);

        return 0;
    }

    protected function debugMiddlewareChain()
    {
        $router = app('router');
        
        // Check login route middleware
        $routes = $router->getRoutes();
        foreach ($routes as $route) {
            if ($route->getName() === 'login') {
                $this->info("✅ Login route found:");
                $this->info("   URI: " . $route->uri());
                $this->info("   Methods: " . implode(', ', $route->methods()));
                $this->info("   Middleware: " . implode(', ', $route->gatherMiddleware()));
                $this->info("   Action: " . $route->getActionName());
                break;
            }
        }

        // Check middleware registration
        $middleware = $router->getMiddleware();
        $this->info("\n📋 Registered Middleware:");
        foreach (['tenant.auth', 'tenant', 'tenant.homepage'] as $name) {
            if (isset($middleware[$name])) {
                $this->info("   ✅ {$name}: {$middleware[$name]}");
            } else {
                $this->error("   ❌ {$name}: NOT REGISTERED");
            }
        }

        // Check middleware groups
        $groups = $router->getMiddlewareGroups();
        $this->info("\n📋 Middleware Groups:");
        foreach (['tenant', 'web'] as $group) {
            if (isset($groups[$group])) {
                $this->info("   ✅ {$group}: " . count($groups[$group]) . " middleware(s)");
                foreach ($groups[$group] as $mw) {
                    $this->info("      - {$mw}");
                }
            } else {
                $this->error("   ❌ {$group}: NOT FOUND");
            }
        }
    }

    protected function debugRouteResolution($domain)
    {
        // Simulate route resolution
        $request = Request::create("http://{$domain}/login", 'GET');
        $request->headers->set('HOST', $domain);

        try {
            $route = app('router')->getRoutes()->match($request);
            $this->info("✅ Route matched: " . $route->getName());
            $this->info("   Middleware: " . implode(', ', $route->gatherMiddleware()));
        } catch (\Exception $e) {
            $this->error("❌ Route resolution failed: " . $e->getMessage());
        }
    }

    protected function debugAuthConfiguration()
    {
        $authConfig = config('auth');
        $this->info("📋 Auth Configuration:");
        $this->info("   Default Guard: " . $authConfig['defaults']['guard']);
        $this->info("   Default Provider: " . $authConfig['defaults']['passwords']);
        
        $webGuard = $authConfig['guards']['web'] ?? null;
        if ($webGuard) {
            $this->info("   Web Guard Driver: " . $webGuard['driver']);
            $this->info("   Web Guard Provider: " . $webGuard['provider']);
        }
        
        $usersProvider = $authConfig['providers']['users'] ?? null;
        if ($usersProvider) {
            $this->info("   Users Provider Driver: " . $usersProvider['driver']);
            $this->info("   Users Provider Model: " . $usersProvider['model']);
        }
    }

    protected function debugUserProvider($domain)
    {
        // Test without tenant context
        $this->info("🔍 Testing User Provider (Central Context):");
        try {
            $centralUsers = User::count();
            $this->info("   Central DB Users: {$centralUsers}");
            $centralDb = DB::connection()->getDatabaseName();
            $this->info("   Central DB Name: {$centralDb}");
        } catch (\Exception $e) {
            $this->error("   Error: " . $e->getMessage());
        }

        // Test with tenant context
        $this->info("\n🔍 Testing User Provider (Tenant Context):");
        try {
            $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                $query->where('domain', $domain);
            })->first();

            if ($tenant) {
                tenancy()->initialize($tenant);
                $tenantUsers = User::count();
                $this->info("   Tenant DB Users: {$tenantUsers}");
                $tenantDb = DB::connection()->getDatabaseName();
                $this->info("   Tenant DB Name: {$tenantDb}");
                
                // Test specific user lookup
                $testUser = User::where('email', 'admin@tenant.local')->first();
                if ($testUser) {
                    $this->info("   ✅ Test user found: {$testUser->email}");
                } else {
                    $this->warn("   ⚠️  Test user not found");
                }
            }
        } catch (\Exception $e) {
            $this->error("   Error: " . $e->getMessage());
        }
    }

    protected function debugDatabaseConnections($domain)
    {
        $this->info("🗄️ Database Connection Debug:");
        
        // Test default connection
        try {
            $defaultConn = DB::connection();
            $this->info("   Default Connection: " . $defaultConn->getName());
            $this->info("   Default Database: " . $defaultConn->getDatabaseName());
        } catch (\Exception $e) {
            $this->error("   Default Connection Error: " . $e->getMessage());
        }

        // Test with tenant initialization
        try {
            $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                $query->where('domain', $domain);
            })->first();

            if ($tenant) {
                $this->info("\n   Before tenant initialization:");
                $this->info("   Connection: " . DB::connection()->getName());
                $this->info("   Database: " . DB::connection()->getDatabaseName());
                
                tenancy()->initialize($tenant);
                
                $this->info("\n   After tenant initialization:");
                $this->info("   Connection: " . DB::connection()->getName());
                $this->info("   Database: " . DB::connection()->getDatabaseName());
                $this->info("   Tenant ID: " . (tenant() ? tenant()->id : 'None'));
            }
        } catch (\Exception $e) {
            $this->error("   Tenant Connection Error: " . $e->getMessage());
        }

        // Test auth connection specifically
        try {
            $authModel = config('auth.providers.users.model');
            $this->info("\n   Auth Model: {$authModel}");
            
            // Create instance and check connection
            $model = new $authModel;
            $connection = $model->getConnection();
            $this->info("   Auth Model Connection: " . $connection->getName());
            $this->info("   Auth Model Database: " . $connection->getDatabaseName());
        } catch (\Exception $e) {
            $this->error("   Auth Model Error: " . $e->getMessage());
        }
    }
}
