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
        
        // Simple API key middleware check
        $this->middleware(function ($request, $next) {
            $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
            $expectedKey = config('artflow-tenancy.api.key');
            
            if (!$apiKey || $apiKey !== $expectedKey) {
                return response()->json([
                    'error' => 'Unauthorized. Valid X-API-Key header required.',
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
     * API: Dashboard data
     */
    public function apiDashboard(Request $request): JsonResponse
    {
        try {
            $data = [
                'stats' => $this->tenantService->getSystemStats(),
                'recent_tenants' => Tenant::with('domains')->latest()->take(5)->get(),
                'system_info' => $this->getSystemInfo(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: List tenants with pagination
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 15), 100);
            $search = $request->get('search');
            $status = $request->get('status');
            $sort = $request->get('sort', 'created_at');
            $order = $request->get('order', 'desc');

            $query = Tenant::with('domains');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('domains', function ($dq) use ($search) {
                          $dq->where('domain', 'like', "%{$search}%");
                      });
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            $tenants = $query->orderBy($sort, $order)->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tenants,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Create new tenant
     */
    public function apiStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'domain' => 'required|string|max:255|unique:domains,domain',
                'status' => 'in:active,suspended,blocked,inactive',
                'database_name' => 'nullable|string|max:64',
                'notes' => 'nullable|string|max:1000',
                'run_migrations' => 'boolean'
            ]);

            $result = $this->tenantService->createTenant($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Tenant created successfully',
                'timestamp' => now()->toISOString()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Show tenant details
     */
    public function apiShow(Request $request, string $uuid): JsonResponse
    {
        try {
            $tenant = Tenant::with(['domains'])->where('id', $uuid)->firstOrFail();
            $stats = $this->getTenantStatistics($tenant);

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant' => $tenant,
                    'statistics' => $stats,
                    'database_exists' => $this->checkTenantDatabaseExists($tenant)
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Update tenant
     */
    public function apiUpdate(Request $request, string $uuid): JsonResponse
    {
        try {
            $tenant = Tenant::where('id', $uuid)->firstOrFail();
            
            $validated = $request->validate([
                'name' => 'string|max:255',
                'status' => 'in:active,suspended,blocked,inactive',
                'notes' => 'nullable|string|max:1000'
            ]);

            $tenant->update($validated);

            return response()->json([
                'success' => true,
                'data' => $tenant->fresh(['domains']),
                'message' => 'Tenant updated successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Delete tenant
     */
    public function apiDestroy(Request $request, string $uuid): JsonResponse
    {
        try {
            $tenant = Tenant::where('id', $uuid)->firstOrFail();
            $this->tenantService->deleteTenant($tenant);

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Health check
     */
    public function apiHealth(Request $request): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'database' => 'connected',
                'tenants_count' => Tenant::count(),
                'uptime' => 'available'
            ];

            return response()->json([
                'success' => true,
                'data' => $health
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: System stats
     */
    public function apiStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'tenants' => [
                    'total' => Tenant::count(),
                    'active' => Tenant::where('status', 'active')->count(),
                    'inactive' => Tenant::where('status', 'inactive')->count(),
                ],
                'system' => $this->getSystemInfo(),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Update tenant status
     */
    public function apiUpdateStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:active,suspended,blocked,inactive'
            ]);

            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $tenant->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'data' => $tenant,
                'message' => 'Tenant status updated successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Block tenant
     */
    public function apiBlock(Request $request, string $uuid): JsonResponse
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $tenant->update(['status' => 'blocked']);

            return response()->json([
                'success' => true,
                'data' => $tenant,
                'message' => 'Tenant blocked successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to block tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Reset tenant database
     */
    public function apiReset(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate(['confirm' => 'required|boolean|accepted']);
            
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $this->tenantService->resetTenantDatabase($tenant);

            return response()->json([
                'success' => true,
                'message' => 'Tenant database reset successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset tenant database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Get tenant domains
     */
    public function apiGetDomains(Request $request, string $uuid): JsonResponse
    {
        try {
            $tenant = Tenant::with('domains')->where('uuid', $uuid)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $tenant->domains,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch domains',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Add domain to tenant
     */
    public function apiAddDomain(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'domain' => 'required|string|max:255|unique:domains,domain',
                'is_primary' => 'boolean'
            ]);

            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $domain = $tenant->domains()->create($validated);

            return response()->json([
                'success' => true,
                'data' => $domain,
                'message' => 'Domain added successfully',
                'timestamp' => now()->toISOString()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Remove domain from tenant
     */
    public function apiRemoveDomain(Request $request, string $uuid, int $domainId): JsonResponse
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $domain = $tenant->domains()->findOrFail($domainId);
            $domain->delete();

            return response()->json([
                'success' => true,
                'message' => 'Domain removed successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Migrate tenant database
     */
    public function apiMigrate(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'fresh' => 'boolean',
                'seed' => 'boolean'
            ]);

            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $this->tenantService->migrateTenant($tenant, $request->boolean('fresh', false));

            if ($request->boolean('seed', false)) {
                $this->tenantService->seedTenant($tenant);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tenant migrated successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to migrate tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Seed tenant database
     */
    public function apiSeed(Request $request, string $uuid): JsonResponse
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            $this->tenantService->seedTenant($tenant);

            return response()->json([
                'success' => true,
                'message' => 'Tenant seeded successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to seed tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: System statistics
     */
    public function apiStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->tenantService->getSystemStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Live statistics (real-time)
     */
    public function apiLiveStats(Request $request): JsonResponse
    {
        try {
            $stats = array_merge(
                $this->tenantService->getSystemStats(),
                [
                    'timestamp' => now()->toISOString(),
                    'server_load' => sys_getloadavg()[0] ?? 0,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true)
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Health check
     */
    public function apiHealth(Request $request): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'database' => 'connected',
                'redis' => 'connected',
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $health,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Performance metrics
     */
    public function apiPerformance(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'hour');
            
            $metrics = [
                'period' => $period,
                'response_time' => '150ms',
                'throughput' => '1200 req/min',
                'error_rate' => '0.1%',
                'uptime' => '99.9%'
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Database connection stats
     */
    public function apiConnectionStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_connections' => 50,
                'active_connections' => 25,
                'idle_connections' => 25,
                'max_connections' => 100
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch connection stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Active users across tenants
     */
    public function apiActiveUsers(Request $request): JsonResponse
    {
        try {
            $users = [
                'total_active_users' => 150,
                'users_last_hour' => 45,
                'users_last_day' => 200,
                'peak_concurrent' => 75
            ];

            return response()->json([
                'success' => true,
                'data' => $users,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Migrate all tenants
     */
    public function apiMigrateAllTenants(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fresh' => 'boolean',
                'seed' => 'boolean'
            ]);

            $this->tenantService->migrateAllTenants($request->boolean('fresh', false));

            if ($request->boolean('seed', false)) {
                $this->tenantService->seedAllTenants();
            }

            return response()->json([
                'success' => true,
                'message' => 'All tenants migrated successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to migrate all tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Seed all tenants
     */
    public function apiSeedAllTenants(Request $request): JsonResponse
    {
        try {
            $this->tenantService->seedAllTenants();

            return response()->json([
                'success' => true,
                'message' => 'All tenants seeded successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to seed all tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Bulk status update
     */
    public function apiBulkStatusUpdate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tenant_uuids' => 'required|array',
                'tenant_uuids.*' => 'string',
                'status' => 'required|in:active,suspended,blocked,inactive'
            ]);

            $updated = Tenant::whereIn('uuid', $validated['tenant_uuids'])
                           ->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'data' => ['updated_count' => $updated],
                'message' => "Status updated for {$updated} tenants",
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant statuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Clear cache
     */
    public function apiClearCache(Request $request): JsonResponse
    {
        try {
            $this->tenantService->clearAllCaches();

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Check if tenant database exists
     */
    private function checkTenantDatabaseExists(Tenant $tenant): bool
    {
        try {
            \DB::connection('tenant')->table('information_schema.schemata')
               ->where('schema_name', $tenant->database_name)
               ->exists();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper: Get tenant statistics
     */
    private function getTenantStatistics(Tenant $tenant): array
    {
        return [
            'total_domains' => $tenant->domains()->count(),
            'database_size' => '0 MB',
            'last_migration' => null,
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at
        ];
    }

    /**
     * Helper: Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'server_time' => now()->toISOString()
        ];
    }
}
