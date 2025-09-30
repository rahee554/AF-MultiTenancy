<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Stancl\Tenancy\Facades\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class TenantHomepageMiddleware
{
    /**
     * Handle an incoming request for tenant homepage functionality
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost();
        $centralDomains = config('artflow-tenancy.central_domains', []);

        // Check if this is a central domain
        if (in_array($domain, $centralDomains)) {
            // For central domains, continue with default behavior
            return $next($request);
        }

        // Try to find and initialize tenant
        try {
            $domainRecord = \Stancl\Tenancy\Database\Models\Domain::where('domain', $domain)->first();
            if ($domainRecord && $domainRecord->tenant) {
                $tenant = $domainRecord->tenant;
                
                // Initialize tenancy manually if not already done
                if (!Tenancy::initialized()) {
                    tenancy()->initialize($tenant);
                }

                // Auto-create homepage directory if it doesn't exist
                $this->ensureHomepageDirectoryExists($tenant);

                // Check if tenant-specific homepage exists
                $primaryDomain = $tenant->domains()->first();
                $domainName = $primaryDomain ? $primaryDomain->domain : $tenant->id;
                $tenantHomepage = "tenants.{$domainName}.home";
                
                if (view()->exists($tenantHomepage)) {
                    // Return tenant-specific homepage directly
                    return response(view($tenantHomepage, compact('tenant')));
                }
            }
        } catch (\Exception $e) {
            // Log error but continue
            logger("Tenant identification error for domain {$domain}: " . $e->getMessage());
        }

        // Fall back to default behavior
        return $next($request);
    }

    /**
     * Ensure the homepage directory exists for the tenant using domain name
     */
    protected function ensureHomepageDirectoryExists($tenant): void
    {
        // Get primary domain for directory naming
        $primaryDomain = $tenant->domains()->first();
        $domainName = $primaryDomain ? $primaryDomain->domain : $tenant->id;
        
        // Use domain name for better readability
        $homepageDir = resource_path("views/tenants/{$domainName}");
        
        if (!is_dir($homepageDir)) {
            // Create the tenant-specific homepage directory
            mkdir($homepageDir, 0755, true);
            
            // Create a default homepage view for this tenant
            file_put_contents(
                "{$homepageDir}/home.blade.php",
                $this->getDefaultHomepageTemplate($tenant)
            );
            
            // Log the directory creation
            logger("Homepage directory created for tenant {$tenant->name} at: {$homepageDir}");
        }
    }

    /**
     * Get the default homepage template content
     */
    protected function getDefaultHomepageTemplate($tenant): string
    {
        return "@extends('layouts.app')

@section('title', '{$tenant->name} - Home')

@section('content')
<div class=\"min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100\">
    <div class=\"container mx-auto px-4 py-8\">
        <div class=\"text-center mb-12\">
            <h1 class=\"text-4xl font-bold text-gray-900 mb-4\">
                🏠 Welcome to {{ \$tenant->name }}
            </h1>
            <p class=\"text-xl text-gray-600 max-w-2xl mx-auto\">
                Auto-generated tenant homepage with domain-based directory structure
            </p>
        </div>

        <div class=\"grid md:grid-cols-3 gap-8 max-w-6xl mx-auto\">
            <!-- Package Features Card -->
            <div class=\"bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300\">
                <div class=\"text-center\">
                    <div class=\"w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4\">
                        <svg class=\"w-8 h-8 text-blue-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z\"></path>
                        </svg>
                    </div>
                    <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">ArtFlow Tenancy</h3>
                    <p class=\"text-gray-600 mb-4\">Powered by ArtFlow Studio multi-tenancy package with auto-directory creation</p>
                    <a href=\"#\" class=\"text-blue-600 font-medium hover:text-blue-800 transition-colors\">
                        Learn More →
                    </a>
                </div>
            </div>

            <!-- Tenant Management Card -->
            <div class=\"bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300\">
                <div class=\"text-center\">
                    <div class=\"w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4\">
                        <svg class=\"w-8 h-8 text-green-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z\"></path>
                        </svg>
                    </div>
                    <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">Tenant Access</h3>
                    <p class=\"text-gray-600 mb-4\">Access your tenant dashboard and management features</p>
                    @auth
                        <a href=\"{{ route('admin::dashboard') }}\" class=\"text-green-600 font-medium hover:text-green-800 transition-colors\">
                            Dashboard →
                        </a>
                    @else
                        <a href=\"{{ route('login') }}\" class=\"text-green-600 font-medium hover:text-green-800 transition-colors\">
                            Login →
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Admin Panel Card -->
            <div class=\"bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300\">
                <div class=\"text-center\">
                    <div class=\"w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4\">
                        <svg class=\"w-8 h-8 text-purple-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2z\"></path>
                        </svg>
                    </div>
                    <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">Admin Panel</h3>
                    <p class=\"text-gray-600 mb-4\">Access the ArtFlow tenancy admin interface</p>
                    <a href=\"{{ config('artflow-tenancy.route.prefix', 'af-tenancy') }}/admin\" class=\"text-purple-600 font-medium hover:text-purple-800 transition-colors\">
                        Admin Panel →
                    </a>
                </div>
            </div>
        </div>

        <!-- Tenant Information Footer -->
        <div class=\"mt-16 text-center\">
            <div class=\"bg-white rounded-xl shadow-md p-6 max-w-4xl mx-auto\">
                <h4 class=\"text-lg font-semibold text-gray-900 mb-4\">Tenant Information</h4>
                <div class=\"grid md:grid-cols-2 gap-6\">
                    <div class=\"space-y-3 text-left\">
                        <div class=\"flex justify-between\">
                            <strong class=\"text-gray-600\">Tenant Name:</strong>
                            <span class=\"text-gray-800\">{{ \$tenant->name ?? 'N/A' }}</span>
                        </div>
                        <div class=\"flex justify-between\">
                            <strong class=\"text-gray-600\">Tenant ID:</strong>
                            <span class=\"font-mono text-sm text-gray-800\">{{ \$tenant->id ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class=\"space-y-3 text-left\">
                        <div class=\"flex justify-between\">
                            <strong class=\"text-gray-600\">Domain:</strong>
                            <span class=\"text-gray-800\">{{ \$tenant->domains->first()->domain ?? 'N/A' }}</span>
                        </div>
                        <div class=\"flex justify-between\">
                            <strong class=\"text-gray-600\">Database:</strong>
                            <span class=\"text-gray-800\">{{ \$tenant->database ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <div class=\"mt-4 pt-4 border-t border-gray-200\">
                    <p class=\"text-sm text-gray-500\">
                        Generated by ArtFlow Studio Tenancy Package - Directory: tenants/{{ \$tenant->domains->first()->domain ?? \$tenant->id }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection";
    }
}