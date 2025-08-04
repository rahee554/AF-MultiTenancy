<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use ArtflowStudio\Tenancy\Models\Tenant;

class HomepageRedirectMiddleware
{
    /**
     * Handle an incoming request for tenant homepage.
     * If tenant has homepage enabled, show it. Otherwise redirect to fallback.
     */
    public function handle($request, Closure $next)
    {
        $config = config('artflow-tenancy.homepage');
        $fallbackRedirect = $config['fallback_redirect'] ?? '/login';
        $viewPath = $config['view_path'] ?? 'tenants';

        // Check if we're in a tenant context
        $tenant = null;
        try {
            $tenant = app('tenant');
        } catch (\Exception $e) {
            return redirect($fallbackRedirect);
        }

        if (!$tenant || !($tenant instanceof Tenant)) {
            return redirect($fallbackRedirect);
        }

        // Check if homepage is enabled in config and for tenant
        $homepageEnabled = $config['enabled'] ?? true;
        if (!$homepageEnabled || !$tenant->hasHomepage()) {
            return redirect($fallbackRedirect);
        }

        $domain = $request->getHost();
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

        // If no homepage views exist, redirect to fallback
        return redirect($fallbackRedirect);
    }
}
