<?php

namespace ArtflowStudio\Tenancy\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Models\TenantQuota;
use ArtflowStudio\Tenancy\Services\TenantResourceQuotaService;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;

class TenantController extends Controller
{
    protected $quotaService;
    protected $analyticsService;

    public function __construct(TenantResourceQuotaService $quotaService, TenantAnalyticsService $analyticsService)
    {
        $this->quotaService = $quotaService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Display a listing of tenants
     */
    public function index(Request $request)
    {
        $query = Tenant::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or domain
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('domains', function ($domainQuery) use ($search) {
                      $domainQuery->where('domain', 'like', "%{$search}%");
                  });
            });
        }

        $tenants = $query->with('domains')
                        ->orderBy('created_at', 'desc')
                        ->paginate(20);

        // Get quota summaries for each tenant
        foreach ($tenants as $tenant) {
            $tenant->quota_summary = $this->quotaService->getQuotaSummary($tenant->id);
        }

        return view('artflow-tenancy::admin.tenants.index', compact('tenants'));
    }

    /**
     * Show tenant details
     */
    public function show(Tenant $tenant)
    {
        $tenant->load('domains');
        
        // Get quota information
        $quotaSummary = $this->quotaService->getQuotaSummary($tenant->id);
        $quotaRecommendations = $this->quotaService->getQuotaRecommendations($tenant->id);
        
        // Get analytics
        $analytics = $this->analyticsService->getTenantMetrics($tenant->id);
        $healthScore = $this->analyticsService->getHealthScore($tenant->id);

        return view('artflow-tenancy::admin.tenants.show', compact(
            'tenant', 
            'quotaSummary', 
            'quotaRecommendations', 
            'analytics', 
            'healthScore'
        ));
    }

    /**
     * Show tenant quota management
     */
    public function quotas(Tenant $tenant)
    {
        $tenant->load('domains');
        
        $quotas = TenantQuota::where('tenant_id', $tenant->id)->get();
        $quotaSummary = $this->quotaService->getQuotaSummary($tenant->id);
        $recommendations = $this->quotaService->getQuotaRecommendations($tenant->id);
        
        // Get default quotas for comparison
        $defaultQuotas = config('artflow-tenancy.quotas.default', []);

        return view('artflow-tenancy::admin.tenants.quotas', compact(
            'tenant', 
            'quotas', 
            'quotaSummary', 
            'recommendations',
            'defaultQuotas'
        ));
    }

    /**
     * Update tenant quotas
     */
    public function updateQuotas(Request $request, Tenant $tenant)
    {
        $request->validate([
            'quotas' => 'required|array',
            'quotas.*.limit' => 'required|integer|min:0',
            'quotas.*.warning_threshold' => 'required|numeric|min:0|max:100',
            'quotas.*.enforcement_enabled' => 'boolean',
        ]);

        $quotaData = [];
        foreach ($request->quotas as $resourceType => $data) {
            $quotaData[$resourceType] = [
                'limit' => $data['limit'],
                'warning_threshold' => $data['warning_threshold'],
                'enforcement_enabled' => $data['enforcement_enabled'] ?? false,
            ];
        }

        $this->quotaService->setTenantQuotas($tenant->id, $quotaData);

        return redirect()
            ->route('admin.tenants.quotas', $tenant)
            ->with('success', 'Tenant quotas updated successfully.');
    }

    /**
     * Reset quota usage for a specific resource
     */
    public function resetQuotaUsage(Request $request, Tenant $tenant)
    {
        $request->validate([
            'resource_type' => 'required|string',
        ]);

        $success = $this->quotaService->resetQuotaUsage(
            $tenant->id, 
            $request->resource_type,
            'admin_reset'
        );

        if ($success) {
            return redirect()
                ->route('admin.tenants.quotas', $tenant)
                ->with('success', "Usage reset for {$request->resource_type}.");
        }

        return redirect()
            ->route('admin.tenants.quotas', $tenant)
            ->with('error', "Failed to reset usage for {$request->resource_type}.");
    }

    /**
     * Get tenant analytics data for AJAX
     */
    public function analytics(Tenant $tenant)
    {
        $analytics = $this->analyticsService->getTenantMetrics($tenant->id);
        $healthScore = $this->analyticsService->getHealthScore($tenant->id);
        $quotaSummary = $this->quotaService->getQuotaSummary($tenant->id);

        return response()->json([
            'analytics' => $analytics,
            'health_score' => $healthScore,
            'quota_summary' => $quotaSummary,
            'updated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get quota usage history for a resource
     */
    public function quotaHistory(Tenant $tenant, $resourceType)
    {
        $history = $this->quotaService->getUsageHistory($tenant->id, $resourceType, 30);

        return response()->json($history);
    }
}
