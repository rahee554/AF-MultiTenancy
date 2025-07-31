<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Features;

use Stancl\Tenancy\Contracts\Feature;
use Stancl\Tenancy\Tenancy;

/**
 * Enhanced Telescope Integration with Tenant Names
 * 
 * Extends the basic TelescopeTags to include tenant names and additional context
 */
class EnhancedTelescopeTags implements Feature
{
    public function bootstrap(Tenancy $tenancy): void
    {
        if (!class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        \Laravel\Telescope\Telescope::tag(function (\Laravel\Telescope\IncomingEntry $entry) {
            $tags = [];

            if (!request()->route()) {
                return $tags;
            }

            if (tenancy()->initialized) {
                $tenant = tenant();
                
                // Basic tenant ID tag
                $tags[] = 'tenant:' . $tenant->id;
                
                // Add tenant name if available
                if (!empty($tenant->name)) {
                    $tags[] = 'tenant_name:' . str_replace(' ', '_', $tenant->name);
                }
                
                // Add domain information
                $domain = $tenant->domains()->first();
                if ($domain) {
                    $tags[] = 'domain:' . $domain->domain;
                }
                
                // Add tenant status
                if (!empty($tenant->status)) {
                    $tags[] = 'tenant_status:' . $tenant->status;
                }
                
                // Add project identifier for multi-project setups
                $projectId = config('artflow-tenancy.project.id');
                if ($projectId) {
                    $tags[] = 'project:' . $projectId;
                }
                
                // Add environment tag
                $tags[] = 'environment:' . app()->environment();
                
            } else {
                $tags[] = 'central';
                $tags[] = 'environment:' . app()->environment();
            }

            return $tags;
        });

        // Configure Telescope to use central database
        $this->ensureCentralDatabase();
    }

    protected function ensureCentralDatabase(): void
    {
        // Ensure Telescope uses the central database connection
        config([
            'telescope.storage.database.connection' => config('tenancy.database.central_connection', 'mysql')
        ]);
    }
}
