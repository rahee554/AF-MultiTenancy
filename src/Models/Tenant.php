<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    /**
     * The attributes that are mass assignable.
     * Note: Custom table structure includes name, status fields + stancl's data field
     */
    protected $fillable = [
        'id',
        'data',
        'name',
        'database',
        'status', 
        'has_homepage',
        'last_accessed_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'data' => 'array',
        'settings' => 'array',
        'has_homepage' => 'boolean',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Get custom columns that are not stored in the data JSON column
     */
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'database',
            'status',
            'has_homepage',
            'last_accessed_at',
            'settings',
        ]);
    }

    /**
     * Activate the tenant
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the tenant
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * Check if tenant is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant has homepage enabled
     */
    public function hasHomepage(): bool
    {
        return $this->has_homepage === true;
    }

    /**
     * Enable homepage for tenant
     */
    public function enableHomepage(): void
    {
        $this->update(['has_homepage' => true]);
        
        // Auto-create homepage view directory and file if enabled
        if (config('artflow-tenancy.homepage.auto_create_directory', true)) {
            $domain = $this->domains()->first()?->domain;
            if ($domain) {
                app(\ArtflowStudio\Tenancy\Services\TenantService::class)->createHomepageView($domain);
            }
        }
    }

    /**
     * Disable homepage for tenant
     */
    public function disableHomepage(): void
    {
        $this->update(['has_homepage' => false]);
        
        // Optionally remove homepage view directory when disabling
        // Note: We don't auto-remove to preserve custom content
        // User can manually delete if needed
    }

    /**
     * Get the database name for this tenant
     * First checks custom 'database' column, then falls back to prefix + tenant key (without hyphens)
     */
    public function getDatabaseName(): string
    {
        // If custom database name is set, use it
        if (!empty($this->database)) {
            return $this->database;
        }
        
        // Otherwise use standard stancl/tenancy naming (strip hyphens from UUID)
        $prefix = config('tenancy.database.prefix', 'tenant_');
        return $prefix . str_replace('-', '', $this->getTenantKey());
    }

    /**
     * Override database config to use custom database name
     */
    public function database(): \Stancl\Tenancy\DatabaseConfig
    {
        $databaseConfig = new \Stancl\Tenancy\DatabaseConfig($this);
        
        // Set the internal db_name to our custom database name
        $this->setInternal('db_name', $this->getDatabaseName());
        
        return $databaseConfig;
    }
}
