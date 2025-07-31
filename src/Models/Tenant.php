<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
        'status',
        'last_accessed_at',
        'settings',
        'data',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'settings' => 'array',
        'data' => 'array',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // Generate ID if not provided (stancl/tenancy uses string IDs)
            if (empty($tenant->id)) {
                $tenant->id = Str::uuid()->toString();
            }
            
            // Store additional data in the data column
            $tenant->data = array_merge($tenant->data ?? [], [
                'name' => $tenant->name,
                'status' => $tenant->status ?? 'active',
                'settings' => $tenant->settings ?? [],
            ]);
        });

        static::updating(function ($tenant) {
            // Update data column when attributes change
            $tenant->data = array_merge($tenant->data ?? [], [
                'name' => $tenant->name,
                'status' => $tenant->status,
                'settings' => $tenant->settings ?? [],
            ]);
        });

        static::created(function ($tenant) {
            Cache::tags(['tenants'])->flush();
        });

        static::updated(function ($tenant) {
            Cache::tags(['tenants'])->flush();
            $tenant->updateLastAccessed();
        });

        static::deleted(function ($tenant) {
            Cache::tags(['tenants'])->flush();
        });
    }

    /**
     * Get the primary domain for this tenant.
     */
    public function getPrimaryDomain()
    {
        return $this->domains()->first();
    }

    /**
     * Get the primary domain name.
     */
    public function getPrimaryDomainName(): ?string
    {
        $domain = $this->getPrimaryDomain();
        return $domain ? $domain->domain : null;
    }

    /**
     * Check if tenant has a specific domain.
     */
    public function hasDomain(string $domain): bool
    {
        return $this->domains()->where('domain', $domain)->exists();
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Activate the tenant.
     */
    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the tenant.
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }

    /**
     * Block the tenant.
     */
    public function block(): bool
    {
        return $this->update(['status' => 'blocked']);
    }

    /**
     * Scope for active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive tenants.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope for blocked tenants.
     */
    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    /**
     * Update last accessed timestamp
     */
    public function updateLastAccessed(): void
    {
        $this->updateQuietly(['last_accessed_at' => now()]);
    }

    /**
     * Get real-time statistics for this tenant
     */
    public function getRealTimeStats(): array
    {
        $cacheKey = "tenant_realtime_stats_{$this->id}";
        
        return Cache::remember($cacheKey, 60, function () {
            // Initialize tenancy context for this tenant
            tenancy()->initialize($this);
            
            try {
                $stats = [
                    'tenant_id' => $this->id,
                    'tenant_name' => $this->name,
                    'status' => $this->status,
                    'primary_domain' => $this->getPrimaryDomainName(),
                    'database_name' => $this->database()->name,
                    'last_accessed_at' => $this->last_accessed_at?->toISOString(),
                    'created_at' => $this->created_at->toISOString(),
                    'updated_at' => $this->updated_at->toISOString(),
                ];

                // Get database statistics
                try {
                    $dbStats = DB::select('SHOW TABLE STATUS');
                    $totalSize = collect($dbStats)->sum(function ($table) {
                        return $table->Data_length + $table->Index_length;
                    });
                    
                    $stats['database_stats'] = [
                        'tables_count' => count($dbStats),
                        'total_size_bytes' => $totalSize,
                        'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                    ];
                } catch (\Exception $e) {
                    $stats['database_stats'] = [
                        'error' => 'Unable to fetch database stats',
                        'message' => $e->getMessage(),
                    ];
                }

                return $stats;
            } catch (\Exception $e) {
                return [
                    'tenant_id' => $this->id,
                    'error' => 'Unable to fetch tenant stats',
                    'message' => $e->getMessage(),
                ];
            } finally {
                // End tenancy context
                tenancy()->end();
            }
        });
    }

    /**
     * Clear tenant statistics cache.
     */
    public function clearStatsCache(): void
    {
        Cache::forget("tenant_realtime_stats_{$this->id}");
    }
}
