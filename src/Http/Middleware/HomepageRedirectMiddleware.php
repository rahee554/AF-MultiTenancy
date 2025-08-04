<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Facades\Tenancy;
use ArtflowStudio\Tenancy\Models\Tenant;

class HomepageRedirectMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to root path requests
        if ($request->getPathInfo() !== '/') {
            return $next($request);
        }

        // Check if homepage feature is globally enabled
        if (!config('artflow-tenancy.homepage.enabled', true)) {
            return $next($request);
        }

        // Check if we're in a tenant context
        if (!Tenancy::initialized()) {
            return $next($request);
        }

        // Get current tenant
        $tenant = Tenancy::tenant();
        
        if (!$tenant || !($tenant instanceof Tenant)) {
            return $next($request);
        }

        // If tenant doesn't have homepage enabled, redirect to configured fallback
        if (!$tenant->hasHomepage()) {
            $fallbackRedirect = config('artflow-tenancy.homepage.fallback_redirect', '/login');
            return redirect($fallbackRedirect);
        }

        // If tenant has homepage, try to load custom homepage view
        $domain = $request->getHost();
        $viewPath = config('artflow-tenancy.homepage.view_path', 'tenants');
        $customViewPath = "{$viewPath}.{$domain}.home";
        
        // Check if custom tenant homepage view exists
        if (view()->exists($customViewPath)) {
            return response()->view($customViewPath, [
                'tenant' => $tenant,
                'domain' => $domain
            ]);
        }
        
        // Fallback to default tenant homepage if exists
        if (view()->exists("{$viewPath}.home")) {
            return response()->view("{$viewPath}.home", [
                'tenant' => $tenant,
                'domain' => $domain
            ]);
        }

        // If no custom homepage views exist, continue to regular homepage
        return $next($request);
    }
}
