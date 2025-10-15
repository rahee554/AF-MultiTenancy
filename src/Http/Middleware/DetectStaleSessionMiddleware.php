<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detect and handle stale sessions caused by tenant database recreation
 * 
 * This middleware prevents 403 Forbidden errors when:
 * 1. A tenant database is deleted and recreated
 * 2. User still has old session data
 * 3. Session references non-existent user_id from old database
 * 
 * Solution: Detect stale sessions and force re-authentication
 */
class DetectStaleSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check when:
        // 1. User is authenticated (has session)
        // 2. Tenancy is initialized
        // 3. Not on login/logout routes (to avoid loops)
        if (Auth::check() && tenancy()->initialized && !$this->isAuthRoute($request)) {
            $tenant = tenant();
            
            if ($this->hasStaleSession($request, $tenant)) {
                Log::warning('Stale session detected after tenant database recreation', [
                    'tenant_id' => $tenant->id,
                    'user_id' => Auth::id(),
                    'domain' => $request->getHost(),
                ]);
                
                // Force logout and clear session
                $this->handleStaleSession($request);
                
                // Redirect to login with helpful message
                return redirect()->route('login')->with('warning', 
                    'Your session has expired. Please log in again.'
                );
            }
        }
        
        return $next($request);
    }
    
    /**
     * Check if the current session is stale
     * (references user that doesn't exist in tenant database)
     */
    private function hasStaleSession(Request $request, $tenant): bool
    {
        try {
            // If user is authenticated, check if the user exists in tenant database
            if (Auth::check()) {
                $userId = Auth::id();
                
                // Check if this user exists in the current tenant database
                $userExists = DB::connection('tenant')
                    ->table('users')
                    ->where('id', $userId)
                    ->exists();
                
                if (!$userExists) {
                    // User ID from session doesn't exist in tenant database
                    // This means database was recreated or session is from different tenant
                    return true;
                }
                
                // Additional check: Verify session tenant_id matches current tenant
                $sessionTenantId = $request->session()->get('tenant_id');
                if ($sessionTenantId && $sessionTenantId !== $tenant->id) {
                    // Session is from a different tenant
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            // If we can't check (database error), assume session might be stale
            Log::error('Error checking stale session', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id ?? 'unknown',
            ]);
            
            // Don't force logout on database errors
            return false;
        }
    }
    
    /**
     * Handle a stale session by logging out and clearing data
     */
    private function handleStaleSession(Request $request): void
    {
        try {
            // Clear authentication
            Auth::logout();
            
            // Invalidate the session
            $request->session()->invalidate();
            
            // Regenerate CSRF token
            $request->session()->regenerateToken();
            
            // Clear any tenant-specific session data
            $request->session()->forget([
                'tenant_id',
                'tenant_domain',
                '_tenant_scoped',
                'intended', // Clear intended URL to avoid redirect loops
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error handling stale session', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Check if current route is authentication-related
     * (to avoid redirect loops)
     */
    private function isAuthRoute(Request $request): bool
    {
        $authRoutes = [
            'login',
            'logout',
            'register',
            'password.request',
            'password.reset',
            'password.email',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ];
        
        $currentRoute = $request->route()?->getName();
        
        if (!$currentRoute) {
            // Check by path
            $path = $request->path();
            return str_contains($path, 'login') ||
                   str_contains($path, 'logout') ||
                   str_contains($path, 'register') ||
                   str_contains($path, 'password') ||
                   str_contains($path, 'verify');
        }
        
        return in_array($currentRoute, $authRoutes);
    }
}
