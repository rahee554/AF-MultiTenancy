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

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'name',
        'status',
        'notes',
        'database_name',
        'last_accessed_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Disable the data column from stancl tenancy.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'uuid', 
            'name',
            'status',
            'notes',
            'database_name',
            'last_accessed_at',
            'settings',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Override to use individual columns instead of data JSON.
     */
    public function getAttribute($key)
    {
        // For stancl tenancy compatibility, map data array access to individual columns
        if ($key === 'data') {
            return [
                'uuid' => $this->uuid,
                'name' => $this->name,
                'status' => $this->status,
                'notes' => $this->notes,
                'database_name' => $this->database_name,
                'settings' => $this->settings,
            ];
        }

        return parent::getAttribute($key);
    }

    /**
     * Override to handle data array updates.
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'data' && is_array($value)) {
            // Map data array to individual columns
            foreach ($value as $dataKey => $dataValue) {
                if (in_array($dataKey, $this->fillable)) {
                    $this->{$dataKey} = $dataValue;
                }
            }
            return $this;
        }

        return parent::setAttribute($key, $value);
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
     * Get formatted status for display.
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucfirst($this->status ?? 'active');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });

        static::created(function ($tenant) {
            // Clear cache when tenant is created
            try {
                Cache::tags(['tenants'])->flush();
            } catch (\Exception $e) {
                // Fallback for cache drivers that don't support tagging
                Cache::flush();
            }
        });

        static::updated(function ($tenant) {
            // Clear cache when tenant is updated
            try {
                Cache::tags(['tenants'])->flush();
            } catch (\Exception $e) {
                // Fallback for cache drivers that don't support tagging
                Cache::flush();
            }
        });

        static::deleted(function ($tenant) {
            // Clear cache when tenant is deleted
            try {
                Cache::tags(['tenants'])->flush();
            } catch (\Exception $e) {
                // Fallback for cache drivers that don't support tagging
                Cache::flush();
            }
        });
    }

    /**
     * Get the tenant identifier for the tenancy package.
     */
    public function getTenantKey(): string
    {
        return (string) $this->uuid;
    }

    /**
     * Get the tenant identifier name for the tenancy package.
     */
    public function getTenantKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the database name for this tenant.
     * Uses stored database_name if available, otherwise generates one.
     */
    public function getDatabaseName(): string
    {
        if ($this->database_name) {
            return $this->database_name;
        }
        
        // Fallback to generate from name + uuid (for backwards compatibility)
        $prefix = config('tenancy.database.prefix', env('TENANT_DB_PREFIX', 'tenant_'));
        $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($this->name ?? 'tenant'));
        return $prefix . $cleanName . '_' . substr($this->uuid, 0, 8);
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
     * Get tenant statistics.
     */
    public function getStats(): array
    {
        if (!tenant() || tenant()->id !== $this->id) {
            return [];
        }

        try {
            return Cache::tags(['tenant_stats'])->remember(
                "tenant_stats_{$this->id}",
                now()->addMinutes(5),
                function () {
                    return [
                        'customers_count' => \App\Models\Customer::count(),
                        'invoices_count' => \App\Models\Invoice::count(),
                        'services_count' => \App\Models\Service::count(),
                        'orders_count' => \App\Models\Order::count(),
                        'total_revenue' => \App\Models\Invoice::sum('total') ?? 0,
                        'last_invoice_date' => \App\Models\Invoice::latest()->value('created_at'),
                    ];
                }
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clear tenant statistics cache.
     */
    public function clearStatsCache(): void
    {
        try {
            Cache::tags(['tenant_stats'])->forget("tenant_stats_{$this->id}");
        } catch (\Exception $e) {
            // Fallback for cache drivers that don't support tagging
            Cache::forget("tenant_stats_{$this->id}");
        }
    }
}
