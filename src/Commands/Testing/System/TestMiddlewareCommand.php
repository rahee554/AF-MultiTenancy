<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class TestMiddlewareCommand extends Command
{
    protected $signature = 'af-tenancy:test-middleware';
    protected $description = 'Test if the simplified tenant middleware is registered correctly';

    public function handle()
    {
        $this->info('🧪 Testing Middleware Registration...');
        $this->newLine();

        // Get the router instance
        $router = app('router');
        
        // Check if our middleware is registered
        $middlewareMap = $router->getMiddleware();
        
        $this->info('📋 Registered Middleware:');
        $this->line('────────────────────────────────────');
        
        $found = [];
        $expected = [
            'tenant' => 'ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware',
            'smart.tenant' => 'ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware',
            'tenant.auth' => 'ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware',
            'tenancy.api' => 'ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware',
            'central.tenant' => 'ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware',
            'smart.domain' => 'ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware',
            'tenant.homepage' => 'ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware',
        ];

        foreach ($expected as $alias => $className) {
            if (isset($middlewareMap[$alias])) {
                $registered = $middlewareMap[$alias];
                if ($registered === $className) {
                    $this->info("✅ {$alias} → {$className}");
                    $found[] = $alias;
                } else {
                    $this->error("❌ {$alias} → {$registered} (expected: {$className})");
                }
            } else {
                $this->error("❌ {$alias} → NOT REGISTERED");
            }
        }

        $this->newLine();
        
        // Check middleware groups
        $this->info('📦 Middleware Groups:');
        $this->line('────────────────────────────────────');
        
        $groups = $router->getMiddlewareGroups();
        if (isset($groups['tenant'])) {
            $this->info('✅ tenant group:');
            foreach ($groups['tenant'] as $middleware) {
                $this->line("   → {$middleware}");
            }
        } else {
            $this->error('❌ tenant group not found');
        }

        $this->newLine();
        
        // Test middleware class exists
        $this->info('🔍 Class Existence Check:');
        $this->line('────────────────────────────────────');
        
        $testClasses = [
            'ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware',
            'ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware',
            'ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware',
            'ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware',
        ];

        foreach ($testClasses as $class) {
            if (class_exists($class)) {
                $this->info("✅ {$class}");
            } else {
                $this->error("❌ {$class} - NOT FOUND");
            }
        }

        $this->newLine();
        
        // Summary
        $foundCount = count($found);
        $expectedCount = count($expected);
        
        if ($foundCount === $expectedCount) {
            $this->info("🎉 SUCCESS: All {$foundCount}/{$expectedCount} middleware registered correctly!");
            $this->newLine();
            $this->info('💡 You can now use the simplified middleware in your routes:');
            $this->line('   Route::middleware([\'tenant\'])->group(function () {');
            $this->line('       // Your tenant routes here');
            $this->line('   });');
        } else {
            $this->error("❌ ISSUES FOUND: Only {$foundCount}/{$expectedCount} middleware registered correctly");
            $this->newLine();
            $this->warn('🔧 Try running these commands to fix:');
            $this->line('   php artisan route:clear');
            $this->line('   php artisan config:clear');
            $this->line('   php artisan cache:clear');
        }

        return $foundCount === $expectedCount ? 0 : 1;
    }
}
