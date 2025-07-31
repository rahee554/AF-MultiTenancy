<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-aware Sanctum Service
 * 
 * Manages Laravel Sanctum integration with multi-tenancy
 */
class TenantSanctumService
{
    /**
     * Configure Sanctum for current tenant
     */
    public function configureSanctumForTenant(?Tenant $tenant = null): void
    {
        if (!$tenant) {
            $tenant = tenant();
        }

        if (!$tenant) {
            Log::warning('TenantSanctumService: No tenant found for Sanctum configuration');
            return;
        }

        // Set tenant-specific token table
        $this->setTenantTokenTable($tenant);
        
        // Configure token expiration per tenant
        $this->configureTenantTokenExpiration($tenant);
        
        // Set tenant-specific token guards
        $this->configureTenantGuards($tenant);

        Log::info("Sanctum configured for tenant: {$tenant->id}");
    }

    /**
     * Set tenant-specific token table
     */
    protected function setTenantTokenTable(Tenant $tenant): void
    {
        // Use tenant database for tokens
        if ($tenant instanceof TenantWithDatabase) {
            $connection = $tenant->tenancy_db_connection ?? 'tenant';
            
            // Configure PersonalAccessToken model to use tenant connection
            PersonalAccessToken::resolveConnection($connection);
            
            // Update Sanctum configuration
            Config::set('sanctum.personal_access_tokens.model', PersonalAccessToken::class);
        }
    }

    /**
     * Configure tenant-specific token expiration
     */
    protected function configureTenantTokenExpiration(Tenant $tenant): void
    {
        $tenantData = $tenant->data ?? [];
        
        // Default expiration times
        $defaultExpiration = config('sanctum.expiration', null);
        $defaultStatefulExpiration = config('sanctum.rt_expiration', null);
        
        // Check for tenant-specific expiration settings
        $tenantExpiration = $tenantData['sanctum_expiration'] ?? $defaultExpiration;
        $tenantStatefulExpiration = $tenantData['sanctum_rt_expiration'] ?? $defaultStatefulExpiration;
        
        // Apply tenant-specific settings
        Config::set('sanctum.expiration', $tenantExpiration);
        Config::set('sanctum.rt_expiration', $tenantStatefulExpiration);
        
        Log::debug("Sanctum expiration configured for tenant {$tenant->id}", [
            'expiration' => $tenantExpiration,
            'rt_expiration' => $tenantStatefulExpiration
        ]);
    }

    /**
     * Configure tenant-specific guards
     */
    protected function configureTenantGuards(Tenant $tenant): void
    {
        $tenantData = $tenant->data ?? [];
        
        // Default Sanctum guards
        $defaultGuards = config('sanctum.guard', ['web']);
        
        // Check for tenant-specific guard configuration
        $tenantGuards = $tenantData['sanctum_guards'] ?? $defaultGuards;
        
        // Apply tenant-specific guards
        Config::set('sanctum.guard', $tenantGuards);
        
        Log::debug("Sanctum guards configured for tenant {$tenant->id}", [
            'guards' => $tenantGuards
        ]);
    }

    /**
     * Create tenant-specific API token
     */
    public function createTenantToken($user, string $name, array $abilities = ['*'], ?Tenant $tenant = null): string
    {
        if (!$tenant) {
            $tenant = tenant();
        }

        if (!$tenant) {
            throw new \Exception('No tenant context found for token creation');
        }

        // Ensure Sanctum is configured for this tenant
        $this->configureSanctumForTenant($tenant);
        
        // Add tenant context to token
        $tokenData = [
            'tenant_id' => $tenant->id,
            'created_at' => now(),
        ];
        
        // Create the token with tenant metadata
        $token = $user->createToken($name, $abilities);
        
        // Store additional tenant metadata if needed
        $this->storeTenantTokenMetadata($token->accessToken, $tenant, $tokenData);
        
        Log::info("API token created for tenant {$tenant->id}", [
            'user_id' => $user->id,
            'token_name' => $name,
            'abilities' => $abilities
        ]);
        
        return $token->plainTextToken;
    }

    /**
     * Store additional tenant metadata for token
     */
    protected function storeTenantTokenMetadata($accessToken, Tenant $tenant, array $metadata): void
    {
        // Store in tenant's data if needed
        $tenantData = $tenant->data ?? [];
        $tenantData['active_tokens'] = $tenantData['active_tokens'] ?? [];
        
        $tenantData['active_tokens'][$accessToken->id] = [
            'created_at' => $metadata['created_at']->toISOString(),
            'user_id' => $accessToken->tokenable_id,
            'name' => $accessToken->name,
        ];
        
        $tenant->update(['data' => $tenantData]);
    }

    /**
     * Revoke all tokens for tenant
     */
    public function revokeAllTenantTokens(?Tenant $tenant = null): int
    {
        if (!$tenant) {
            $tenant = tenant();
        }

        if (!$tenant) {
            throw new \Exception('No tenant context found for token revocation');
        }

        $this->configureSanctumForTenant($tenant);
        
        // Count tokens before deletion
        $tokenCount = PersonalAccessToken::count();
        
        // Delete all tokens for this tenant
        PersonalAccessToken::truncate();
        
        // Clear tenant token metadata
        $tenantData = $tenant->data ?? [];
        unset($tenantData['active_tokens']);
        $tenant->update(['data' => $tenantData]);
        
        Log::info("All API tokens revoked for tenant {$tenant->id}", [
            'revoked_count' => $tokenCount
        ]);
        
        return $tokenCount;
    }

    /**
     * Get token statistics for tenant
     */
    public function getTenantTokenStats(?Tenant $tenant = null): array
    {
        if (!$tenant) {
            $tenant = tenant();
        }

        if (!$tenant) {
            throw new \Exception('No tenant context found for token statistics');
        }

        $this->configureSanctumForTenant($tenant);
        
        $stats = [
            'total_tokens' => PersonalAccessToken::count(),
            'active_tokens' => PersonalAccessToken::whereNull('last_used_at')
                ->orWhere('last_used_at', '>', now()->subDays(30))
                ->count(),
            'expired_tokens' => 0,
            'recent_tokens' => PersonalAccessToken::where('created_at', '>', now()->subDays(7))->count(),
        ];
        
        // Calculate expired tokens if expiration is configured
        $expiration = config('sanctum.expiration');
        if ($expiration) {
            $expirationDate = now()->subMinutes($expiration);
            $stats['expired_tokens'] = PersonalAccessToken::where('created_at', '<', $expirationDate)->count();
        }
        
        return $stats;
    }

    /**
     * Clean up expired tokens for tenant
     */
    public function cleanupExpiredTokens(?Tenant $tenant = null): int
    {
        if (!$tenant) {
            $tenant = tenant();
        }

        if (!$tenant) {
            throw new \Exception('No tenant context found for token cleanup');
        }

        $this->configureSanctumForTenant($tenant);
        
        $expiration = config('sanctum.expiration');
        if (!$expiration) {
            return 0; // No expiration configured
        }
        
        $expirationDate = now()->subMinutes($expiration);
        $expiredTokens = PersonalAccessToken::where('created_at', '<', $expirationDate);
        
        $deletedCount = $expiredTokens->count();
        $expiredTokens->delete();
        
        // Update tenant metadata
        $tenantData = $tenant->data ?? [];
        if (isset($tenantData['active_tokens'])) {
            $activeTokens = $tenantData['active_tokens'];
            $remainingTokens = [];
            
            foreach ($activeTokens as $tokenId => $tokenInfo) {
                $tokenCreatedAt = \Carbon\Carbon::parse($tokenInfo['created_at']);
                if ($tokenCreatedAt->gt($expirationDate)) {
                    $remainingTokens[$tokenId] = $tokenInfo;
                }
            }
            
            $tenantData['active_tokens'] = $remainingTokens;
            $tenant->update(['data' => $tenantData]);
        }
        
        Log::info("Expired tokens cleaned up for tenant {$tenant->id}", [
            'deleted_count' => $deletedCount
        ]);
        
        return $deletedCount;
    }

    /**
     * Validate token belongs to current tenant
     */
    public function validateTenantToken(string $token): bool
    {
        $tenant = tenant();
        
        if (!$tenant) {
            return false;
        }

        $this->configureSanctumForTenant($tenant);
        
        // Find the token
        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            return false;
        }
        
        // Additional validation can be added here
        // For example, checking if token is in tenant's allowed tokens list
        
        return true;
    }

    /**
     * Get Sanctum middleware configuration for tenant
     */
    public function getTenantSanctumMiddleware(?Tenant $tenant = null): array
    {
        if (!$tenant) {
            $tenant = tenant();
        }

        if (!$tenant) {
            return ['auth:sanctum'];
        }

        $tenantData = $tenant->data ?? [];
        
        // Default Sanctum middleware
        $middleware = ['auth:sanctum'];
        
        // Add tenant-specific middleware if configured
        if (isset($tenantData['sanctum_middleware'])) {
            $middleware = array_merge($middleware, $tenantData['sanctum_middleware']);
        }
        
        return $middleware;
    }
}
