<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class CheckRouteConfigCommand extends Command
{
    protected $signature = 'af-tenancy:check-routes';
    protected $description = 'Check route configuration for proper tenant middleware application';

    public function handle()
    {
        $this->info('🔍 Checking Route Configuration...');
        $this->newLine();

        $routes = Route::getRoutes();
        $tenantRoutes = [];
        $nonTenantRoutes = [];
        $authRoutes = [];
        $assetRoutes = [];

        foreach ($routes as $route) {
            $middleware = $route->middleware();
            $uri = $route->uri();
            $name = $route->getName() ?? 'unnamed';
            $methods = implode('|', $route->methods());

            // Categorize routes
            if (in_array('tenant', $middleware) || in_array('smart.tenant', $middleware)) {
                $tenantRoutes[] = [
                    'uri' => $uri,
                    'name' => $name,
                    'methods' => $methods,
                    'middleware' => $middleware
                ];
            } elseif (str_contains($uri, 'login') || str_contains($uri, 'register') || str_contains($uri, 'password')) {
                $authRoutes[] = [
                    'uri' => $uri,
                    'name' => $name,
                    'methods' => $methods,
                    'middleware' => $middleware
                ];
            } elseif (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)/', $uri)) {
                $assetRoutes[] = [
                    'uri' => $uri,
                    'name' => $name,
                    'methods' => $methods,
                    'middleware' => $middleware
                ];
            } else {
                $nonTenantRoutes[] = [
                    'uri' => $uri,
                    'name' => $name,
                    'methods' => $methods,
                    'middleware' => $middleware
                ];
            }
        }

        // Display tenant routes
        $this->info('🏢 Tenant Routes (' . count($tenantRoutes) . ' routes):');
        $this->line('────────────────────────────────────────────');
        foreach (array_slice($tenantRoutes, 0, 10) as $route) {
            $middlewareStr = implode(', ', $route['middleware']);
            $this->line("✅ {$route['methods']} {$route['uri']} ({$route['name']})");
            $this->line("   Middleware: {$middlewareStr}");
        }
        if (count($tenantRoutes) > 10) {
            $this->line("... and " . (count($tenantRoutes) - 10) . " more tenant routes");
        }
        $this->newLine();

        // Display auth routes
        $this->info('🔐 Authentication Routes (' . count($authRoutes) . ' routes):');
        $this->line('────────────────────────────────────────────');
        foreach ($authRoutes as $route) {
            $middlewareStr = implode(', ', $route['middleware']);
            $tenantMiddleware = ['tenant', 'smart.tenant', 'tenant.auth'];
            $hasTenant = false;
            foreach ($tenantMiddleware as $middleware) {
                if (in_array($middleware, $route['middleware'])) {
                    $hasTenant = true;
                    break;
                }
            }
            $status = $hasTenant ? '✅' : '❌';
            $this->line("{$status} {$route['methods']} {$route['uri']} ({$route['name']})");
            $this->line("   Middleware: {$middlewareStr}");
            if (!$hasTenant) {
                $this->warn("   ⚠️  Auth route missing tenant middleware!");
            }
        }
        $this->newLine();

        // Check for common issues
        $this->info('🔍 Issue Detection:');
        $this->line('────────────────────────────────────────────');

        $issues = [];

        // Check for auth routes without tenant middleware
        $authWithoutTenant = array_filter($authRoutes, function($route) {
            $tenantMiddleware = ['tenant', 'smart.tenant', 'tenant.auth'];
            foreach ($tenantMiddleware as $middleware) {
                if (in_array($middleware, $route['middleware'])) {
                    return false; // Has tenant middleware
                }
            }
            return true; // Missing tenant middleware
        });

        if (!empty($authWithoutTenant)) {
            $issues[] = "❌ " . count($authWithoutTenant) . " auth routes missing tenant middleware";
        }

        // Check for duplicate tenant middleware
        $duplicateTenant = array_filter($tenantRoutes, function($route) {
            $tenantCount = 0;
            foreach ($route['middleware'] as $middleware) {
                if (in_array($middleware, ['tenant', 'smart.tenant', 'tenant.auth'])) {
                    $tenantCount++;
                }
            }
            return $tenantCount > 1;
        });

        if (!empty($duplicateTenant)) {
            $issues[] = "⚠️  " . count($duplicateTenant) . " routes have duplicate tenant middleware";
        }

        // Display issues or success
        if (empty($issues)) {
            $this->info('✅ No configuration issues detected!');
            $this->info('✅ All auth routes have tenant middleware');
            $this->info('✅ No duplicate middleware detected');
        } else {
            foreach ($issues as $issue) {
                $this->warn($issue);
            }
        }

        $this->newLine();

        // Summary
        $this->info('📊 Route Summary:');
        $this->line('────────────────────────────────────────────');
        $this->info("🏢 Tenant Routes: " . count($tenantRoutes));
        $this->info("🔐 Auth Routes: " . count($authRoutes));
        $this->info("🌐 Other Routes: " . count($nonTenantRoutes));
        $this->info("📁 Asset Routes: " . count($assetRoutes));
        $totalRoutes = 0;
        foreach ($routes as $route) {
            $totalRoutes++;
        }
        
        $this->info("📋 Total Routes: " . $totalRoutes);

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('• Auth routes should have tenant middleware for proper tenant context');
        $this->line('• Avoid applying tenant middleware multiple times to the same route');
        $this->line('• Assets are automatically handled by SimpleTenantMiddleware');

        return empty($issues) ? 0 : 1;
    }
}
