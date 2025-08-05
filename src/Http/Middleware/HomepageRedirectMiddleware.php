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
     * If tenant has homepage view, show it. Otherwise redirect to fallback.
     */
    public function handle($request, Closure $next)
    {

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
    // If this is a central domain, skip and go to next middleware
    if (in_array($domain, (array)$centralDomains)) {
        return $next($request);
    }

        $config = config('artflow-tenancy.homepage');
        $fallbackRedirect = $config['fallback_redirect'] ?? '/login';
        $viewPath = $config['view_path'] ?? 'tenants';

        // Get the domain first
        $domain = $request->getHost();
        
        // Try to get the tenant from the current tenancy context
        $tenant = null;
        try {
            // Check if tenant is already initialized by stancl/tenancy
            if (function_exists('tenant')) {
                $tenant = tenant();
            }
            
            // If tenant() function doesn't exist or returns null, try app binding
            if (!$tenant) {
                $tenant = app()->bound('tenant') ? app('tenant') : null;
            }
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::info('HomepageRedirectMiddleware: Error getting tenant', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        // If we still don't have a tenant, try to find it by domain
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
