<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request for API authentication
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for X-API-Key header
        $apiKey = $request->header('X-API-Key');
        $expectedKey = env('TENANT_API_KEY');
        
        // If API key is configured, validate it
        if ($expectedKey) {
            if (!$apiKey || $apiKey !== $expectedKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or missing API key. Please include X-API-Key header.',
                    'code' => 401,
                    'timestamp' => now()->toISOString()
                ], 401);
            }
            
            return $next($request);
        }
        
        // Development mode - allow localhost without API key if no key is configured
        if (app()->environment(['local', 'development']) && in_array($request->ip(), ['127.0.0.1', '::1', 'localhost'])) {
            return $next($request);
        }
        
        // Production mode - require API key always
        return response()->json([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'API key required. Please set TENANT_API_KEY in your environment and include X-API-Key header.',
            'code' => 401,
            'timestamp' => now()->toISOString()
        ], 401);
    }
}
