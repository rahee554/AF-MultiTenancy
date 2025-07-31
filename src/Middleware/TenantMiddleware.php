<?php

namespace ArtflowStudio\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     * This middleware combines tenancy initialization and database switching.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a central domain
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        $currentDomain = $request->getHost();
        
        if (in_array($currentDomain, $centralDomains)) {
            // Business routes are not allowed on central domains
            abort(404, 'Business features are only available on tenant domains.');
        }

        // Initialize tenancy using stancl/tenancy
        app(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class)
            ->handle($request, function ($request) {
                // Do nothing here - just initialize tenancy
            });

        // If tenancy was initialized, switch to tenant database and check status
        if (tenancy()->initialized) {
            $tenant = tenant();
            
            if ($tenant) {
                // Check tenant status first
                switch ($tenant->status ?? 'active') {
                    case 'blocked':
                        return response()->view('errors.tenant-blocked', ['tenant' => $tenant], 403);
                        
                    case 'suspended':
                        return response()->view('errors.tenant-suspended', ['tenant' => $tenant], 503);
                        
                    case 'inactive':
                        return response()->view('errors.tenant-inactive', ['tenant' => $tenant], 503);
                }
                
                // Switch to tenant database if status is active
                if (isset($tenant->database_name)) {
                    Config::set('database.connections.mysql.database', $tenant->database_name);
                    DB::purge('mysql');
                    DB::reconnect('mysql');
                }
            }
        }

        return $next($request);
    }
}
