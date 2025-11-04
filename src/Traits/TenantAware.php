<?php

namespace ArtflowStudio\Tenancy\Traits;

/**
 * TenantAware Trait
 *
 * Automatically uses the tenant connection when tenancy is initialized.
 * This trait should be added to models that exist in both central and tenant databases.
 *
 * Usage: Add `use TenantAware` to your model class
 *
 * Example:
 *     class User extends Authenticatable
 *     {
 *         use TenantAware;
 *     }
 */
trait TenantAware
{
    /**
     * Get the database connection to use for this model.
     * Uses the tenant connection when tenancy is initialized,
     * otherwise uses the default connection (central database).
     */
    public function getConnectionName(): ?string
    {
        // If tenancy is initialized, use the tenant connection
        if (tenancy()->initialized) {
            return 'tenant';
        }

        // Otherwise use the default connection (central database)
        return parent::getConnectionName();
    }
}
