<?php

namespace ArtflowStudio\Tenancy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ArtflowStudio\Tenancy\Services\TenantPulseService;
use ArtflowStudio\Tenancy\Models\Tenant;

class TenantPulseController extends Controller
{
    public function __construct(
        private TenantPulseService $pulseService
    ) {}

    /**
     * Show tenant-specific Pulse dashboard
     */
    public function dashboard(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $hours = min(168, max(1, (int) $request->get('hours', 24))); // Max 1 week, min 1 hour

        if ($tenantId) {
            $tenant = Tenant::findOrFail($tenantId);
            $metrics = $this->pulseService->getTenantMetrics($tenantId, null, $hours);
            
            return view('tenancy::pulse.tenant-dashboard', [
                'tenant' => $tenant,
                'metrics' => $metrics,
                'hours' => $hours
            ]);
        }

        // Show all tenants overview
        $allMetrics = $this->pulseService->getAllTenantsMetrics($hours);
        $tenants = Tenant::all();

        return view('tenancy::pulse.overview', [
            'tenants' => $tenants,
            'allMetrics' => $allMetrics,
            'hours' => $hours
        ]);
    }

    /**
     * Get tenant metrics API endpoint
     */
    public function metrics(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $type = $request->get('type');
        $hours = min(168, max(1, (int) $request->get('hours', 24)));

        if ($tenantId) {
            $metrics = $this->pulseService->getTenantMetrics($tenantId, $type, $hours);
        } else {
            $metrics = $this->pulseService->getAllTenantsMetrics($hours);
        }

        return response()->json([
            'metrics' => $metrics,
            'meta' => [
                'tenant_id' => $tenantId,
                'type' => $type,
                'hours' => $hours,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Clean old metrics
     */
    public function cleanMetrics(Request $request)
    {
        $days = min(30, max(1, (int) $request->get('days', 7)));
        
        $deletedCount = $this->pulseService->cleanOldMetrics($days);

        return response()->json([
            'deleted_count' => $deletedCount,
            'days' => $days,
            'message' => "Cleaned {$deletedCount} old metric entries older than {$days} days"
        ]);
    }
}
