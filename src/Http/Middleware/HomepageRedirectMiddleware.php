<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class HomepageRedirectMiddleware
{
    /**
     * Handle an incoming request for tenant homepage.
     * Only processes the root '/' route - all other routes pass through
     */
    public function handle($request, Closure $next)
    {
        // Only process the root route '/' - all other routes should pass through
        if ($request->path() !== '/') {
            return $next($request);
        }

        $centralDomains = config('tenancy.central_domains', []);
        $domain = $request->getHost();

        // If this is a central domain, skip and go to next middleware
        if (in_array($domain, (array)$centralDomains)) {
            return $next($request);
        }

        // Try to get the tenant from the current tenancy context
        $tenant = null;
        try {
            if (function_exists('tenant')) {
                $tenant = tenant();
            }
            if (!$tenant) {
                $tenant = app()->bound('tenant') ? app('tenant') : null;
            }
        } catch (\Exception $e) {
            Log::info('HomepageRedirectMiddleware: Error getting tenant', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        if (!$tenant) {
            try {
                $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                    $query->where('domain', $domain);
                })->first();
            } catch (\Exception $e) {
                Log::info('HomepageRedirectMiddleware: Error finding tenant by domain', [
                    'domain' => $domain,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $config = config('artflow-tenancy.homepage');
        $fallbackRedirect = $config['fallback_redirect'] ?? '/login';

        // If tenant exists but hasHomepage is false, redirect to fallback
        if ($tenant && method_exists($tenant, 'hasHomepage') && !$tenant->hasHomepage()) {
            Log::info('HomepageRedirectMiddleware: Tenant hasHomepage disabled, redirecting', [
                'domain' => $domain,
                'tenant_id' => $tenant->id,
                'fallback' => $fallbackRedirect
            ]);
            return redirect($fallbackRedirect);
        }

        // Continue with the homepage display logic for root route only
        return $this->handleHomepageDisplay($request, $next, $tenant, $domain);
    }

    /**
     * Handle the homepage display logic
     * CRITICAL FIX: This method was missing, causing the undefined method error
     */
    protected function handleHomepageDisplay($request, Closure $next, $tenant, $domain)
    {
        $config = config('artflow-tenancy.homepage');
        $viewPath = $config['view_path'] ?? 'tenants';
        $fallbackRedirect = $config['fallback_redirect'] ?? '/login';
        if (!$tenant) {
            try {
                $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                    $query->where('domain', $domain);
                })->first();
            } catch (\Exception $e) {
                Log::info('HomepageRedirectMiddleware: Error finding tenant by domain', [
                    'domain' => $domain,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // If we still don't have a tenant, redirect to fallback
        if (!$tenant || !($tenant instanceof Tenant)) {
            Log::info('HomepageRedirectMiddleware: No tenant found, redirecting', [
                'domain' => $domain,
                'fallback' => $fallbackRedirect
            ]);
            return redirect($fallbackRedirect);
        }

        $customViewPath = "{$viewPath}.{$domain}.home";

        // For domains with dots, we need to convert the path properly
        // Laravel view paths use dots as directory separators, but domain names contain dots
        // So we need to build the actual file path and check if it exists
        $domainPath = str_replace('.', DIRECTORY_SEPARATOR, $domain);
        $viewFilePath = resource_path("views/{$viewPath}/{$domain}/home.blade.php");
        $viewExists = file_exists($viewFilePath);

        // Log what we're checking
        Log::info('HomepageRedirectMiddleware: Checking views', [
            'domain' => $domain,
            'tenant_id' => $tenant->id,
            'custom_view_path' => $customViewPath,
            'view_file_path' => $viewFilePath,
            'view_exists' => $viewExists
        ]);

        // Always show the tenant homepage if the view file exists
        if ($viewExists) {
            // Add a custom namespace for this domain's views
            $domainViewPath = resource_path("views/{$viewPath}/{$domain}");
            View::addNamespace('tenant_' . str_replace('.', '_', $domain), $domainViewPath);
            
            $namespacedViewName = 'tenant_' . str_replace('.', '_', $domain) . '::home';
            
            return response()->view($namespacedViewName, [
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
        Log::info('HomepageRedirectMiddleware: No homepage views found, redirecting', [
            'domain' => $domain,
            'custom_view_path' => $customViewPath,
            'fallback_view_path' => "{$viewPath}.home",
            'fallback' => $fallbackRedirect
        ]);
        return redirect($fallbackRedirect);
    }
}
