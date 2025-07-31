<?php

namespace ArtflowStudio\Tenancy\Livewire;

use Closure;

/**
 * TenancyBootstrapperHook
 *
 * Automatically initializes tenancy for Livewire component method requests.
 * This hook runs before Livewire component methods are called,
 * ensuring tenancy context is available throughout the request.
 *
 * This solves the issue where Livewire's _livewire/update endpoint
 * doesn't apply route middleware before calling component methods.
 */
class TenancyBootstrapperHook
{
    /**
     * Handle Livewire request initialization.
     * This method is called by the Livewire lifecycle hook before component methods execute.
     */
    public static function bootstrap(): void
    {
        // Skip if tenancy is already initialized
        if (tenancy()->initialized) {
            return;
        }

        // Try to initialize tenancy from the request domain/subdomain
        $domain = request()->getHost();

        try {
            $tenant = \ArtflowStudio\Tenancy\Models\Tenant::whereHas(
                'domains',
                fn ($query) => $query->where('domain', $domain)
            )->first();

            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        } catch (\Exception $e) {
            // Silently fail if tenant lookup fails
            // This allows central domain requests to proceed without error
        }
    }
}
