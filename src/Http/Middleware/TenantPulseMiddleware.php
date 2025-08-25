<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pulse\Facades\Pulse;
use Stancl\Tenancy\Facades\Tenancy;

class TenantPulseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        // Only track if we're in a tenant context
        if (Tenancy::initialized()) {
            $tenant = Tenancy::tenant();
            $tenantName = $tenant->name ?? $tenant->id;
            $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            // Record tenant request
            Pulse::record(
                type: 'tenant_requests',
                key: $tenantName,
                value: 1
            )->count();
            
            // Record request duration
            Pulse::record(
                type: 'tenant_request_duration',
                key: $tenantName,
                value: $duration
            )->avg()->max();
            
            // Record by HTTP method
            Pulse::record(
                type: 'tenant_requests_by_method',
                key: "{$tenantName}:{$request->method()}",
                value: 1
            )->count();
            
            // Record by status code
            Pulse::record(
                type: 'tenant_responses_by_status',
                key: "{$tenantName}:{$response->status()}",
                value: 1
            )->count();
            
            // Record slow requests (over 1 second)
            if ($duration > 1000) {
                Pulse::record(
                    type: 'tenant_slow_requests',
                    key: $tenantName,
                    value: $duration
                )->count()->avg()->max();
            }
        }
        
        return $response;
    }
}
