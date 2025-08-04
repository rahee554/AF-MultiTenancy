<?php

namespace ArtflowStudio\Tenancy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Models\Tenant;

class TenantApiController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;

        // API key middleware check (only via api_key param in query/body)
        $this->middleware(function ($request, $next) {
            $apiKey = $request->input('api_key');
            $expectedKey = config('artflow-tenancy.api.api_key');

            if (!$apiKey || $apiKey !== $expectedKey) {
                return response()->json([
                    'error' => 'Unauthorized. Valid api_key parameter required.',
                    'code' => 401
                ], 401);
            }

            return $next($request);
        });
    }

    /**
     * API Health Check
     */
    public function apiHealth(): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'healthy',
                'service' => 'Artflow Studio Tenancy',
                'version' => '0.6.0',
                'timestamp' => now()->toISOString(),
                'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Statistics
     */
    public function apiStats(): JsonResponse
    {
        try {
            $stats = [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('data->status', 'active')->count(),
                'inactive_tenants' => Tenant::where('data->status', 'inactive')->count(),
                'total_domains' => DB::table('domains')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all tenants
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $tenants = Tenant::with('domains')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tenants,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new tenant
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'domain' => 'required|string|max:255|unique:domains,domain',
                'status' => 'sometimes|in:active,inactive',
                'database' => 'sometimes|string|max:255',
                'notes' => 'sometimes|string'
            ]);

            $tenant = $this->tenantService->createTenant(
                $validated['name'],
                $validated['domain'],
                $validated['status'] ?? 'active',
                $validated['database'] ?? null,
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $tenant->load('domains'),
                'timestamp' => now()->toISOString()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Show a specific tenant
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::with('domains')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tenant,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant not found'
            ], 404);
        }
    }

    /**
     * Update a tenant
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:active,inactive',
                'notes' => 'sometimes|string'
            ]);

            $data = $tenant->data ?? [];
            foreach ($validated as $key => $value) {
                $data[$key] = $value;
            }

            $tenant->update(['data' => $data]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant->load('domains'),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete a tenant
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $this->tenantService->deleteTenant($tenant);

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a tenant
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $data = $tenant->data ?? [];
            $data['status'] = 'active';
            $tenant->update(['data' => $data]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant activated successfully',
                'data' => $tenant,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate a tenant
     */
    public function deactivate(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $data = $tenant->data ?? [];
            $data['status'] = 'inactive';
            $tenant->update(['data' => $data]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant deactivated successfully',
                'data' => $tenant,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate a tenant's database
     */
    public function migrate(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $this->tenantService->migrateTenant($tenant);

            return response()->json([
                'success' => true,
                'message' => 'Tenant database migrated successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable homepage for a tenant
     */
    public function enableHomepage(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $tenant->enableHomepage();

            return response()->json([
                'success' => true,
                'message' => 'Homepage enabled successfully for tenant',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'has_homepage' => $tenant->has_homepage,
                    'tenant_name' => $tenant->data['name'] ?? 'Unknown'
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable homepage for a tenant
     */
    public function disableHomepage(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $tenant->disableHomepage();

            return response()->json([
                'success' => true,
                'message' => 'Homepage disabled successfully for tenant',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'has_homepage' => $tenant->has_homepage,
                    'tenant_name' => $tenant->data['name'] ?? 'Unknown'
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
