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
 * Detect and AUTO-FIX stale sessions caused by tenant database recreation
 * 
 * This middleware prevents 403 Forbidden errors and seamless authentication by:
 * 1. Detecting when session data is stale (tenant DB recreated)
 * 2. AUTOMATICALLY clearing stale cache and session data
 * 3. Allowing the request to continue normally
 * 4. User experiences NO interruption - seamless flow
 * 
 * Instead of logging out and forcing re-authentication, we proactively fix the data.
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
                Log::info('Stale session detected - AUTO-FIXING', [
                    'tenant_id' => $tenant->id,
                    'user_id' => Auth::id(),
                    'domain' => $request->getHost(),
                ]);
                
                // AUTO-FIX: Clear stale cache and sessions instead of logging out
                $this->autoFixStaleSession($request, $tenant);
                
                // Continue request normally - user doesn't notice anything happened
                Log::info('Stale session AUTO-FIXED - continuing normally', [
                    'tenant_id' => $tenant->id,
                    'user_id' => Auth::id(),
                ]);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Check if the current session is stale
     * (references user that doesn't exist in tenant database or tenant mismatch)
     */
    private function hasStaleSession(Request $request, $tenant): bool
    {
        try {
            // If user is authenticated, check if the user exists
            if (Auth::check()) {
                $userId = Auth::id();
                
                // First, check if user exists in default (main) database
                // (Main database users like admins/app-admins are stored here)
                $userInMain = DB::connection('mysql')
                    ->table('users')
                    ->where('id', $userId)
                    ->exists();
                
                if ($userInMain) {
                    // User is in main database (likely admin/app admin)
                    // So session is valid - not stale
                    return false;
                }
                
                // If not in main DB, check if in tenant database
                // (Tenant-specific users like customers/partners)
                $userInTenant = DB::connection('tenant')
                    ->table('users')
                    ->where('id', $userId)
                    ->exists();
                
                if (!$userInTenant) {
                    return true;
                }
                
                // Additional check: Verify session tenant_id matches current tenant
                $sessionTenantId = $request->session()->get('tenant_id');
                if ($sessionTenantId && $sessionTenantId !== $tenant->id) {
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            // If we can't check (database error), assume session is NOT stale
            // Don't interrupt user flow on database errors
            Log::warning('Error checking stale session', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id ?? 'unknown',
            ]);
            
            return false;
        }
    }
    
    /**
     * AUTO-FIX stale session by clearing tenant cache and session data
     * User experiences no interruption - this happens silently
     */
    private function autoFixStaleSession(Request $request, $tenant): void
    {
        try {
            $tenantId = $tenant->id;
            $domains = $tenant->domains;
            
            // 1. Clear tenant-specific cache
            if (config('cache.default') === 'redis') {
                try {
                    $redis = \Illuminate\Support\Facades\Redis::connection();
                    $pattern = "tenant_{$tenantId}_*";
                    $keys = $redis->keys($pattern);
                    if (!empty($keys)) {
                        $redis->del($keys);
                    }
                } catch (\Exception $e) {
                    // Silently fail - don't interrupt user
                }
            } else {
                // Database cache driver
                try {
                    DB::table('cache')->where('key', 'like', "tenant_{$tenantId}_%")->delete();
                    DB::table('cache')->where('key', 'like', "laravel_cache:tenant_{$tenantId}_%")->delete();
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
            
            // 2. Clear sessions related to this tenant
            if (config('session.driver') === 'database') {
                try {
                    DB::table(config('session.table', 'sessions'))
                        ->where('payload', 'like', "%{$tenantId}%")
                        ->delete();
                    
                    // Also clear by domain
                    foreach ($domains as $domain) {
                        DB::table(config('session.table', 'sessions'))
                            ->where('payload', 'like', "%{$domain->domain}%")
                            ->delete();
                    }
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
            
            // 3. Clear general cache
            try {
                Cache::flush();
            } catch (\Exception $e) {
                // Silently fail - cache flush might not support tagging
            }
            
            // 4. Regenerate session to get fresh data from tenant DB
            try {
                $request->session()->regenerate();
            } catch (\Exception $e) {
                // Silently fail
            }
            
        } catch (\Exception $e) {
            Log::warning("Auto-fix stale session had errors, but continuing anyway", [
                'tenant_id' => $tenant->id,
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
