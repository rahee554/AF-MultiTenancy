<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TestAuthContext extends Command
{
    protected $signature = 'af-tenancy:test-login {domain?} {email?}';
    protected $description = 'Test actual login process simulation';

    public function handle()
    {
        $domain = $this->argument('domain') ?? 'tenancy1.local';
        $email = $this->argument('email') ?? 'admin@tenant.local';
        
        $this->info("🔐 Testing Login Process Simulation");
        $this->info("Domain: {$domain}");
        $this->info("Email: {$email}");
        $this->info(str_repeat('=', 50));

        // 1. Initialize tenant context like middleware would
        $this->info("\n1️⃣ Initializing Tenant Context:");
        try {
            $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                $query->where('domain', $domain);
            })->first();

            if (!$tenant) {
                $this->error("❌ No tenant found for domain: {$domain}");
                return 1;
            }

            $this->info("✅ Tenant Found: {$tenant->name}");
            
            // Initialize tenancy like TenantAuthMiddleware does
            tenancy()->initialize($tenant);
            
            $this->info("✅ Tenant context initialized");
            $this->info("   Current DB: " . DB::connection()->getDatabaseName());
            
        } catch (\Exception $e) {
            $this->error("❌ Tenant initialization failed: " . $e->getMessage());
            return 1;
        }

        // 2. Test User Lookup
        $this->info("\n2️⃣ Testing User Lookup:");
        try {
            // Direct database query
            $userByQuery = User::where('email', $email)->first();
            if ($userByQuery) {
                $this->info("✅ User found via Query: {$userByQuery->name}");
                $this->info("   Connection: " . $userByQuery->getConnection()->getName());
                $this->info("   Database: " . $userByQuery->getConnection()->getDatabaseName());
            } else {
                $this->error("❌ User not found via Query");
            }

            // Auth provider lookup
            $provider = Auth::getProvider();
            $userByProvider = $provider->retrieveByCredentials(['email' => $email]);
            if ($userByProvider) {
                $this->info("✅ User found via Auth Provider: {$userByProvider->name}");
                $this->info("   Connection: " . $userByProvider->getConnection()->getName());
                $this->info("   Database: " . $userByProvider->getConnection()->getDatabaseName());
            } else {
                $this->error("❌ User not found via Auth Provider");
            }

        } catch (\Exception $e) {
            $this->error("❌ User lookup failed: " . $e->getMessage());
        }

        // 3. Test Authentication Attempt
        $this->info("\n3️⃣ Testing Authentication Attempt:");
        try {
            $credentials = ['email' => $email, 'password' => 'password'];
            
            // Check if user exists and password matches
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->info("✅ User exists in current context");
                
                // Check password
                if (Hash::check('password', $user->password)) {
                    $this->info("✅ Password matches");
                    
                    // Test auth attempt
                    if (Auth::attempt($credentials)) {
                        $this->info("✅ Auth::attempt() succeeded");
                        $this->info("   Authenticated User: " . Auth::user()->name);
                        $this->info("   Auth User DB: " . Auth::user()->getConnection()->getDatabaseName());
                    } else {
                        $this->error("❌ Auth::attempt() failed despite valid credentials");
                    }
                } else {
                    $this->error("❌ Password does not match");
                }
            } else {
                $this->error("❌ User does not exist in current context");
                
                // Show what users do exist
                $existingUsers = User::select('email', 'name')->get();
                $this->info("📋 Existing users in current context:");
                foreach ($existingUsers as $u) {
                    $this->info("   - {$u->name} ({$u->email})");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Authentication test failed: " . $e->getMessage());
        }

        // 4. Test Session Context
        $this->info("\n4️⃣ Testing Session Context:");
        try {
            // Start session for testing
            if (!Session::isStarted()) {
                Session::start();
            }
            
            $sessionId = Session::getId();
            $this->info("✅ Session ID: " . substr($sessionId, 0, 16) . "...");
            
            // Test session storage
            Session::put('test_tenant_id', $tenant->id);
            $retrievedTenantId = Session::get('test_tenant_id');
            
            if ($retrievedTenantId === $tenant->id) {
                $this->info("✅ Session storage working");
            } else {
                $this->error("❌ Session storage failed");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Session test failed: " . $e->getMessage());
        }

        // 5. Check Guard Configuration
        $this->info("\n5️⃣ Testing Guard Configuration:");
        try {
            $guard = Auth::guard();
            $this->info("✅ Current Guard: " . get_class($guard));
            
            $provider = Auth::getProvider();
            $this->info("✅ Provider: " . get_class($provider));
            
            // Check provider model
            if (method_exists($provider, 'getModel')) {
                $model = $provider->getModel();
                $instance = new $model;
                $this->info("✅ Provider Model: " . $model);
                $this->info("   Model Connection: " . $instance->getConnection()->getName());
                $this->info("   Model Database: " . $instance->getConnection()->getDatabaseName());
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Guard configuration test failed: " . $e->getMessage());
        }

        $this->info("\n🎯 Summary:");
        $this->info("- Tenant Context: " . (tenant() ? '✅ Active' : '❌ Inactive'));
        $this->info("- Current Database: " . DB::connection()->getDatabaseName());
        $this->info("- Auth Provider Model Database: " . (new (config('auth.providers.users.model')))->getConnection()->getDatabaseName());

        return 0;
    }
}
