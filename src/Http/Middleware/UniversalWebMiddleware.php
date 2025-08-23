<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Universal Web Middleware
 * 
 * Simple middleware that works for both central and tenant domains.
 * Uses stancl/tenancy's standard middleware but doesn't fail on central domains.
 */
class UniversalWebMiddleware
{
    protected $tenancyMiddleware;

    public function __construct()
    {
        $this->tenancyMiddleware = new InitializeTenancyByDomain();
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // If it's a central domain, just continue
        if (in_array($domain, $centralDomains)) {
            return $next($request);
        }

        // Otherwise, try to initialize tenancy
        try {
            return $this->tenancyMiddleware->handle($request, $next);
        } catch (\Exception $e) {
            // If tenant not found, continue anyway (fall back to central behavior)
            return $next($request);
        }
    }
}
