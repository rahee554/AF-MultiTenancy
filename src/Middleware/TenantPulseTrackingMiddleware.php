<?php

namespace ArtflowStudio\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use ArtflowStudio\Tenancy\Services\TenantPulseService;
use Stancl\Tenancy\Facades\Tenancy;

class TenantPulseTrackingMiddleware
{
    public function __construct(
        private TenantPulseService $pulseService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        // Only track if we're in a tenant context
        if (Tenancy::initialized()) {
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage(true);
            
            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsage = $endMemory - $startMemory;

            // Record request metrics
            $this->pulseService->recordRequestMetrics($responseTime, $memoryUsage);
            
            // Record database metrics
            $this->pulseService->recordDatabaseMetrics();
            
            // Record cache metrics
            $this->pulseService->recordCacheMetrics();

            // Record HTTP response metrics
            $this->pulseService->recordTenantMetric(
                'http_response', 
                'status_code', 
                $response->getStatusCode()
            );

            // Record route metrics
            if ($route = $request->route()) {
                $this->pulseService->recordTenantMetric(
                    'route_hit', 
                    $route->getName() ?? $route->uri(), 
                    1
                );
            }
        }

        return $response;
    }
}
