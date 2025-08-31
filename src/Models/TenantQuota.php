<?php

namespace ArtflowStudio\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'resource_type',
        'quota_limit',
        'current_usage',
        'warning_threshold',
        'enforcement_enabled',
        'last_checked_at',
        'last_warning_at',
        'metadata',
    ];

    protected $casts = [
        'quota_limit' => 'integer',
        'current_usage' => 'integer',
        'warning_threshold' => 'decimal:2',
        'enforcement_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_warning_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns this quota
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Get usage logs for this quota
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(TenantUsageLog::class, 'tenant_id', 'tenant_id')
                    ->where('resource_type', $this->resource_type);
    }

    /**
     * Calculate usage percentage
     */
    public function getUsagePercentageAttribute(): float
    {
        if ($this->quota_limit <= 0) {
            return 0.0;
        }

        return round(($this->current_usage / $this->quota_limit) * 100, 2);
    }

    /**
     * Check if usage is above warning threshold
     */
    public function isAboveWarningThreshold(): bool
    {
        return $this->usage_percentage >= $this->warning_threshold;
    }

    /**
     * Check if quota is exceeded
     */
    public function isExceeded(): bool
    {
        return $this->current_usage >= $this->quota_limit;
    }

    /**
     * Get remaining quota
     */
    public function getRemainingQuotaAttribute(): int
    {
        return max(0, $this->quota_limit - $this->current_usage);
    }

    /**
     * Get quota status
     */
    public function getStatusAttribute(): string
    {
        if ($this->isExceeded()) {
            return 'exceeded';
        }

        if ($this->isAboveWarningThreshold()) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Increment usage
     */
    public function incrementUsage(int $amount, string $source = 'system', array $context = []): void
    {
        $this->increment('current_usage', $amount);
        $this->touch('last_checked_at');

        // Log the usage
        TenantUsageLog::create([
            'tenant_id' => $this->tenant_id,
            'resource_type' => $this->resource_type,
            'usage_amount' => $amount,
            'action' => 'increment',
            'source' => $source,
            'context' => $context,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Decrement usage
     */
    public function decrementUsage(int $amount, string $source = 'system', array $context = []): void
    {
        $this->decrement('current_usage', $amount);
        $this->current_usage = max(0, $this->current_usage); // Prevent negative values
        $this->save();
        $this->touch('last_checked_at');

        // Log the usage
        TenantUsageLog::create([
            'tenant_id' => $this->tenant_id,
            'resource_type' => $this->resource_type,
            'usage_amount' => $amount,
            'action' => 'decrement',
            'source' => $source,
            'context' => $context,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Set usage to specific amount
     */
    public function setUsage(int $amount, string $source = 'system', array $context = []): void
    {
        $oldUsage = $this->current_usage;
        $this->current_usage = max(0, $amount);
        $this->save();
        $this->touch('last_checked_at');

        // Log the usage
        TenantUsageLog::create([
            'tenant_id' => $this->tenant_id,
            'resource_type' => $this->resource_type,
            'usage_amount' => $amount,
            'action' => 'set',
            'source' => $source,
            'context' => array_merge($context, ['old_usage' => $oldUsage]),
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get quota by tenant and resource type
     */
    public static function getForTenantResource(string $tenantId, string $resourceType): ?self
    {
        return static::where('tenant_id', $tenantId)
                     ->where('resource_type', $resourceType)
                     ->first();
    }

    /**
     * Create or update quota for tenant resource
     */
    public static function createOrUpdateQuota(
        string $tenantId,
        string $resourceType,
        int $quotaLimit,
        array $options = []
    ): self {
        return static::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'resource_type' => $resourceType,
            ],
            array_merge([
                'quota_limit' => $quotaLimit,
                'warning_threshold' => $options['warning_threshold'] ?? 80.0,
                'enforcement_enabled' => $options['enforcement_enabled'] ?? true,
                'metadata' => $options['metadata'] ?? [],
            ], $options)
        );
    }
}
