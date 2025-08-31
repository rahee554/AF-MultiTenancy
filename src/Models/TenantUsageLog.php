<?php

namespace ArtflowStudio\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'resource_type',
        'usage_amount',
        'action',
        'source',
        'context',
        'recorded_at',
    ];

    protected $casts = [
        'usage_amount' => 'integer',
        'context' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this usage log
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Get the quota for this usage log
     */
    public function quota(): BelongsTo
    {
        return $this->belongsTo(TenantQuota::class, 'tenant_id', 'tenant_id')
                    ->where('resource_type', $this->resource_type);
    }

    /**
     * Scope for specific tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for specific resource type
     */
    public function scopeForResource($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope for date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Get usage summary for tenant and resource in date range
     */
    public static function getUsageSummary(string $tenantId, string $resourceType, $startDate, $endDate): array
    {
        $logs = static::forTenant($tenantId)
                     ->forResource($resourceType)
                     ->inDateRange($startDate, $endDate)
                     ->orderBy('recorded_at')
                     ->get();

        $summary = [
            'total_increments' => 0,
            'total_decrements' => 0,
            'net_change' => 0,
            'action_counts' => [
                'increment' => 0,
                'decrement' => 0,
                'set' => 0,
            ],
            'source_breakdown' => [],
            'daily_usage' => [],
        ];

        foreach ($logs as $log) {
            if ($log->action === 'increment') {
                $summary['total_increments'] += $log->usage_amount;
                $summary['net_change'] += $log->usage_amount;
            } elseif ($log->action === 'decrement') {
                $summary['total_decrements'] += $log->usage_amount;
                $summary['net_change'] -= $log->usage_amount;
            }

            $summary['action_counts'][$log->action]++;

            // Source breakdown
            $source = $log->source ?? 'unknown';
            if (!isset($summary['source_breakdown'][$source])) {
                $summary['source_breakdown'][$source] = 0;
            }
            $summary['source_breakdown'][$source] += $log->usage_amount;

            // Daily usage
            $date = $log->recorded_at->format('Y-m-d');
            if (!isset($summary['daily_usage'][$date])) {
                $summary['daily_usage'][$date] = 0;
            }
            $summary['daily_usage'][$date] += $log->usage_amount;
        }

        return $summary;
    }
}
