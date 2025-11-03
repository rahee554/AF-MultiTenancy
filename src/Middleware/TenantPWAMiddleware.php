<?php

namespace ArtflowStudio\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantPWAMiddleware
{
    /**
     * Handle an incoming request for PWA-enabled tenants
     *
     * This middleware handles:
     * - CSRF token refresh for PWAs
     * - Session renewal notifications
     * - 403/419 error responses with PWA-friendly headers
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the request is from a PWA (has pwa=1 query param or pwa header)
        $isPWA = $request->query('pwa') === '1' || 
                 $request->header('X-PWA-Request') === 'true' ||
                 $request->header('X-Requested-With') === 'PWA';
        
        // Process the request
        $response = $next($request);
        
        // If this is a PWA request, add special headers and handling
        if ($isPWA) {
            $response = $this->handlePWAResponse($request, $response);
        }
        
        return $response;
    }
    
    /**
     * Handle PWA-specific response modifications
     */
    private function handlePWAResponse(Request $request, Response $response): Response
    {
        // Add PWA-friendly headers
        $response->headers->set('X-PWA-Enabled', 'true');
        
        // Handle authentication errors (403)
        if ($response->getStatusCode() === 403) {
            $response = $this->handle403($request, $response);
        }
        
        // Handle CSRF token mismatch (419)
        if ($response->getStatusCode() === 419) {
            $response = $this->handle419($request, $response);
        }
        
        // Handle session expiration
        if ($this->isSessionExpired($request)) {
            $response->headers->set('X-PWA-Session-Expired', 'true');
        }
        
        // Add CSRF token to response for PWAs
        if ($request->session()->has('_token')) {
            $response->headers->set('X-CSRF-Token', $request->session()->token());
        }
        
        // Add session renewal info
        if ($request->session()->has('last_activity')) {
            $sessionLifetime = config('session.lifetime', 120);
            $lastActivity = $request->session()->get('last_activity');
            $timeRemaining = $sessionLifetime - ((time() - $lastActivity) / 60);
            
            $response->headers->set('X-PWA-Session-Remaining', (int)$timeRemaining);
        }
        
        return $response;
    }
    
    /**
     * Handle 403 Forbidden errors for PWA
     */
    private function handle403(Request $request, Response $response): Response
    {
        Log::warning('[PWA] 403 Forbidden access', [
            'url' => $request->fullUrl(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip()
        ]);
        
        // Add PWA-specific headers
        $response->headers->set('X-PWA-Auth-Error', 'forbidden');
        $response->headers->set('X-PWA-Action', 'refresh-and-retry');
        
        // If JSON request, modify response
        if ($request->expectsJson()) {
            $content = [
                'error' => 'Forbidden',
                'message' => 'Access forbidden. Please refresh your session.',
                'pwa_action' => 'refresh',
                'status' => 403
            ];
            
            $response->setContent(json_encode($content));
            $response->headers->set('Content-Type', 'application/json');
        }
        
        return $response;
    }
    
    /**
     * Handle 419 CSRF token mismatch for PWA
     */
    private function handle419(Request $request, Response $response): Response
    {
        Log::warning('[PWA] 419 CSRF Token Mismatch', [
            'url' => $request->fullUrl(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip()
        ]);
        
        // Generate new CSRF token
        $request->session()->regenerateToken();
        $newToken = $request->session()->token();
        
        // Add PWA-specific headers
        $response->headers->set('X-PWA-CSRF-Error', 'token-mismatch');
        $response->headers->set('X-PWA-New-Token', $newToken);
        $response->headers->set('X-PWA-Action', 'retry-with-new-token');
        
        // If JSON request, modify response
        if ($request->expectsJson()) {
            $content = [
                'error' => 'CSRF Token Mismatch',
                'message' => 'Your session token expired. Please retry your request.',
                'new_token' => $newToken,
                'pwa_action' => 'retry',
                'status' => 419
            ];
            
            $response->setContent(json_encode($content));
            $response->headers->set('Content-Type', 'application/json');
        }
        
        return $response;
    }
    
    /**
     * Check if session is expired or about to expire
     */
    private function isSessionExpired(Request $request): bool
    {
        if (!$request->session()->has('last_activity')) {
            return false;
        }
        
        $sessionLifetime = config('session.lifetime', 120) * 60; // Convert to seconds
        $lastActivity = $request->session()->get('last_activity');
        $timeSinceLastActivity = time() - $lastActivity;
        
        // Consider session expired if more than 90% of lifetime has passed
        $expirationThreshold = $sessionLifetime * 0.9;
        
        return $timeSinceLastActivity > $expirationThreshold;
    }
}
