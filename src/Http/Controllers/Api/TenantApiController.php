<?php

namespace ArtflowStudio\Tenancy\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;
use ArtflowStudio\Tenancy\Services\TenantResourceQuotaService;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;

class TenantApiController extends Controller
{
    protected $quotaService;
    protected $analyticsService;

    public function __construct(
        TenantResourceQuotaService $quotaService,
        TenantAnalyticsService $analyticsService
    ) {
        $this->quotaService = $quotaService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get all tenants
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $status = $request->get('status');

            $query = Tenant::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            $tenants = $query->paginate($perPage);

            // Add quota summaries and domains manually
            $tenants->getCollection()->transform(function($tenant) {
                $tenant->quota_summary = $this->quotaService->getQuotaSummary($tenant->id);
                
                // Get domains manually since Tenant model doesn't have domains() relationship
                $tenant->domains = Domain::where('tenant_id', $tenant->id)->get();
                
                return $tenant;
            });

            return response()->json([
                'success' => true,
                'data' => $tenants,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tenants: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific tenant
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found',
                ], 404);
            }

            // Get domains manually since Tenant model doesn't have domains() relationship
            $tenant->domains = Domain::where('tenant_id', $tenant->id)->get();
            $tenant->quota_summary = $this->quotaService->getQuotaSummary($tenant->id);
            $tenant->analytics = $this->analyticsService->getTenantMetrics($tenant->id);
            $tenant->settings = $this->quotaService->getTenantSettings($tenant->id);

            return response()->json([
                'success' => true,
                'data' => $tenant,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new tenant
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'domain' => 'required|string|max:255|unique:domains,domain',
                'status' => 'nullable|in:active,inactive',
                'has_homepage' => 'nullable|boolean',
                'quotas' => 'nullable|array',
                'settings' => 'nullable|array',
            ]);

            $tenant = Tenant::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $request->name,
                'status' => $request->get('status', 'active'),
                'has_homepage' => $request->get('has_homepage', false),
                'settings' => json_encode(array_merge([
                    'quotas' => $request->get('quotas', []),
                    'created_via' => 'api',
                    'created_at' => now()->toISOString(),
                ], $request->get('settings', []))),
            ]);

            // Create domain
            Domain::create([
                'domain' => $request->domain,
                'tenant_id' => $tenant->id,
            ]);

            // Get domains manually since Tenant model doesn't have domains() relationship
            $tenant->domains = Domain::where('tenant_id', $tenant->id)->get();
            $tenant->quota_summary = $this->quotaService->getQuotaSummary($tenant->id);

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $tenant,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found',
                ], 404);
            }

            $request->validate([
                'name' => 'nullable|string|max:255',
                'status' => 'nullable|in:active,inactive',
                'has_homepage' => 'nullable|boolean',
                'quotas' => 'nullable|array',
                'settings' => 'nullable|array',
            ]);

            $updateData = $request->only(['name', 'status', 'has_homepage']);
            
            if ($request->has('quotas') || $request->has('settings')) {
                $currentSettings = json_decode($tenant->settings ?? '{}', true);
                
                if ($request->has('quotas')) {
                    $currentSettings['quotas'] = $request->quotas;
                }
                
                if ($request->has('settings')) {
                    $currentSettings = array_merge($currentSettings, $request->settings);
                }
                
                $updateData['settings'] = json_encode($currentSettings);
            }

            $tenant->update($updateData);
            
            // Get domains manually since Tenant model doesn't have domains() relationship
            $tenant->domains = Domain::where('tenant_id', $tenant->id)->get();
            $tenant->quota_summary = $this->quotaService->getQuotaSummary($tenant->id);

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete tenant
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found',
                ], 404);
            }

            // Delete domains first
            Domain::where('tenant_id', $tenant->id)->delete();
            // Delete tenant
            $tenant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tenant quotas
     */
    public function getQuotas(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found',
                ], 404);
            }

            $quotas = $this->quotaService->checkQuotas($id);
            $summary = $this->quotaService->getQuotaSummary($id);
            $recommendations = $this->quotaService->getQuotaRecommendations($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'quotas' => $quotas,
                    'summary' => $summary,
                    'recommendations' => $recommendations,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching quotas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant quotas
     */
    public function updateQuotas(Request $request, string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found',
                ], 404);
            }

            $request->validate([
                'quotas' => 'required|array',
                'quotas.*' => 'integer|min:0',
            ]);

            $this->quotaService->setTenantQuotas($id, $request->quotas);

            return response()->json([
                'success' => true,
                'message' => 'Quotas updated successfully',
                'data' => $this->quotaService->checkQuotas($id),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating quotas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tenant analytics
     */
    public function getAnalytics(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found',
                ], 404);
            }

            $analytics = $this->analyticsService->getTenantMetrics($id);

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching analytics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tenant settings
     */
    public function getSettings(string $id): JsonResponse
    {
        try {
            $settings = $this->quotaService->getTenantSettings($id);
            
            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant settings
     */
    public function updateSettings(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'settings' => 'required|array',
            ]);

            $this->quotaService->updateTenantSettings($id, $request->settings);
            $settings = $this->quotaService->getTenantSettings($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $settings,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset tenant quotas to defaults
     */
    public function resetQuotas(string $id): JsonResponse
    {
        try {
            $this->quotaService->resetMonthlyUsage($id);
            $quotas = $this->quotaService->getTenantQuotas($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Quotas reset successfully',
                'data' => $quotas,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resetting quotas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system overview statistics
     */
    public function getSystemOverview(): JsonResponse
    {
        try {
            $totalTenants = Tenant::count();
            $activeTenants = Tenant::where('status', 'active')->count();
            $inactiveTenants = Tenant::where('status', 'inactive')->count();
            $totalDomains = Domain::count();
            
            $overview = [
                'tenants' => [
                    'total' => $totalTenants,
                    'active' => $activeTenants,
                    'inactive' => $inactiveTenants,
                    'percentage_active' => $totalTenants > 0 ? round(($activeTenants / $totalTenants) * 100, 2) : 0,
                ],
                'domains' => [
                    'total' => $totalDomains,
                    'average_per_tenant' => $totalTenants > 0 ? round($totalDomains / $totalTenants, 2) : 0,
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'last_updated' => now()->toISOString(),
                ],
            ];
            
            return response()->json([
                'success' => true,
                'data' => $overview,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching system overview: ' . $e->getMessage(),
            ], 500);
        }
    }
}
