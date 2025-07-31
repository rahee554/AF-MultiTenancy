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
            $expectedKey = config('artflow-tenancy.api.api_key', 'test123');

            // Skip API key validation in local/testing environment
            if (app()->environment(['local', 'testing'])) {
                return $next($request);
            }

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

    /**
     * System health check
     */
    public function health(): JsonResponse
    {
        return $this->apiHealth();
    }

    /**
     * System statistics
     */
    public function stats(): JsonResponse
    {
        return $this->apiStats();
    }

    /**
     * System information
     */
    public function systemInfo(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'service' => 'Artflow Studio Tenancy',
                    'version' => '0.6.0',
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                    'environment' => app()->environment(),
                    'tenants_count' => \Stancl\Tenancy\Database\Models\Tenant::count(),
                    'domains_count' => \Stancl\Tenancy\Database\Models\Domain::count(),
                    'central_domains' => config('artflow-tenancy.central_domains'),
                    'timezone' => config('app.timezone'),
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
     * Migrate all tenants
     */
    public function migrateAll(): JsonResponse
    {
        try {
            $tenants = \Stancl\Tenancy\Database\Models\Tenant::all();
            $results = [];

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                    tenancy()->end();

                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'status' => 'success',
                        'message' => 'Migrations run successfully'
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Migration completed for all tenants',
                'data' => [
                    'total_tenants' => count($results),
                    'successful' => collect($results)->where('status', 'success')->count(),
                    'failed' => collect($results)->where('status', 'error')->count(),
                    'results' => $results
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
     * Seed a specific tenant
     */
    public function seed(string $id): JsonResponse
    {
        try {
            $tenant = \Stancl\Tenancy\Database\Models\Tenant::findOrFail($id);
            
            tenancy()->initialize($tenant);
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            tenancy()->end();

            return response()->json([
                'success' => true,
                'message' => 'Tenant database seeded successfully',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name ?? 'Unknown'
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
     * Test connections for all tenants
     */
    public function testConnections(): JsonResponse
    {
        try {
            $tenants = \Stancl\Tenancy\Database\Models\Tenant::all();
            $results = [];

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    \Illuminate\Support\Facades\DB::connection('tenant')->getPdo();
                    tenancy()->end();

                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'status' => 'connected',
                        'response_time' => round(microtime(true) * 1000) . 'ms'
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Connection test completed',
                'data' => [
                    'total_tenants' => count($results),
                    'connected' => collect($results)->where('status', 'connected')->count(),
                    'failed' => collect($results)->where('status', 'failed')->count(),
                    'results' => $results
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
     * Run stress test
     */
    public function stressTest(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $results = [];

            // Test 1: Database connections
            $tenants = \Stancl\Tenancy\Database\Models\Tenant::take(5)->get();
            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    \Illuminate\Support\Facades\DB::connection('tenant')->select('SELECT 1');
                    tenancy()->end();
                    $results['connections'][] = 'success';
                } catch (\Exception $e) {
                    $results['connections'][] = 'failed';
                }
            }

            // Test 2: Memory usage
            $memoryStart = memory_get_usage();
            for ($i = 0; $i < 1000; $i++) {
                $dummy = str_repeat('test', 100);
            }
            $memoryEnd = memory_get_usage();
            $results['memory'] = [
                'start' => $memoryStart,
                'end' => $memoryEnd,
                'used' => $memoryEnd - $memoryStart
            ];

            // Test 3: Response time
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;

            return response()->json([
                'success' => true,
                'message' => 'Stress test completed',
                'data' => [
                    'response_time_ms' => round($responseTime, 2),
                    'memory_usage' => $results['memory'],
                    'connection_tests' => [
                        'total' => count($results['connections']),
                        'successful' => count(array_filter($results['connections'], fn($r) => $r === 'success')),
                        'failed' => count(array_filter($results['connections'], fn($r) => $r === 'failed'))
                    ]
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
