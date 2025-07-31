<?php

namespace ArtflowStudio\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Facades\Tenancy;

class TenantApiController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Validate API access using TENANT_API_KEY
     */
    private function validateApiAccess(Request $request): bool
    {
        // Check for X-API-Key header
        $apiKey = $request->header('X-API-Key');
        $expectedKey = env('TENANT_API_KEY');
        
        if ($expectedKey) {
            // If API key is configured, it must match exactly
            return $apiKey === $expectedKey;
        }
        
        // If no API key is configured, allow localhost for development
        return in_array($request->ip(), ['127.0.0.1', '::1', 'localhost']);
    }

    /**
     * API: Dashboard data
     */
    public function apiDashboard(Request $request)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized API access'
            ], 401);
        }

        try {
            $stats = $this->tenantService->getSystemStats();
            $systemInfo = $this->getSystemInfo();
            $tenants = Tenant::with('domains')->get();
            $recentTenants = Tenant::latest()->limit(5)->get();
            
            $enhancedStats = [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'inactive_tenants' => Tenant::where('status', 'inactive')->count(),
                'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
                'blocked_tenants' => Tenant::where('status', 'blocked')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => array_merge($stats, $enhancedStats),
                    'system_info' => $systemInfo,
                    'tenants' => $tenants,
                    'recent_tenants' => $recentTenants,
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: List all tenants
     */
    public function apiIndex(Request $request)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $tenants = Tenant::with('domains')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $tenants,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tenants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Create tenant
     */
    public function apiStore(Request $request)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'domain' => 'required|string|unique:domains,domain|max:255|regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/',
            'database_suffix' => 'nullable|string|max:100',
            'status' => 'in:active,inactive,suspended,blocked',
            'notes' => 'nullable|string',
            'run_migrations' => 'nullable|boolean',
            'run_seeders' => 'nullable|boolean',
        ]);

        try {
            // Generate database name from suffix or auto-generate
            $databaseSuffix = $request->input('database_suffix');
            if (!$databaseSuffix) {
                $slug = \Illuminate\Support\Str::slug($request->input('name'), '_');
                $randomSuffix = substr(md5(microtime()), 0, 8);
                $databaseSuffix = $slug . '_' . $randomSuffix;
            }
            $databaseName = 'tenant_' . $databaseSuffix;
            
            $tenant = $this->tenantService->createTenant(
                $request->input('name'),
                $request->input('domain'),
                $request->input('status', 'active'),
                $databaseName,
                $request->input('notes')
            );
            
            $operations = [];
            
            // Run migrations if requested
            if ($request->boolean('run_migrations', false)) {
                $this->migrateTenantSafely($tenant);
                $operations[] = 'migrations';
            }
            
            // Run seeders if requested
            if ($request->boolean('run_seeders', false)) {
                $this->seedTenantSafely($tenant);
                $operations[] = 'seeders';
            }
            
            $message = "Tenant '{$tenant->name}' created successfully!";
            if (!empty($operations)) {
                $message .= ' (' . implode(' and ', $operations) . ' completed)';
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'domain' => $request->input('domain'),
                    'database_name' => $tenant->database_name,
                    'operations_completed' => $operations,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
                'errors' => []
            ], 500);
        }
    }

    /**
     * API: Show tenant details
     */
    public function apiShow(Request $request, $uuid)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $tenant = Tenant::with('domains')->where('uuid', $uuid)->firstOrFail();
            
            // Get tenant statistics
            $stats = $this->getTenantStatistics($tenant);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tenant' => $tenant,
                    'statistics' => $stats,
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found or error occurred: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Update tenant
     */
    public function apiUpdate(Request $request, $uuid)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'sometimes|required|string|min:2|max:255',
            'status' => 'sometimes|in:active,inactive,suspended,blocked',
            'notes' => 'nullable|string',
        ]);

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $tenant->update($request->only(['name', 'status', 'notes']));
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Delete tenant
     */
    public function apiDestroy(Request $request, $uuid)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Delete tenant using service
            $this->tenantService->deleteTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Reset tenant database safely
     */
    public function apiReset(Request $request, $uuid)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Ensure we're working with the correct tenant database
            $tenant->makeCurrent();
            
            // Run fresh migrations ONLY for tenant database
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            // End tenancy context
            Tenancy::end();
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant database reset successfully',
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'database_name' => $tenant->database_name,
                    'output' => $output,
                    'reset_at' => now()->format('c'),
                ]
            ]);
        } catch (\Exception $e) {
            Tenancy::end();
            return response()->json([
                'success' => false,
                'message' => 'Reset failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Migrate tenant database safely
     */
    public function apiMigrate(Request $request, $uuid)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $this->migrateTenantSafely($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant migrations completed successfully',
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'database_name' => $tenant->database_name,
                    'migrated_at' => now()->format('c'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: System statistics
     */
    public function apiStats(Request $request)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->tenantService->getSystemStats(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * API: System health check
     */
    public function apiHealth(Request $request)
    {
        if (!$this->validateApiAccess($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $health = $this->tenantService->checkSystemHealth();
        
        return response()->json([
            'success' => true,
            'status' => $health['status'],
            'timestamp' => now()->toISOString(),
            'checks' => $health['checks']
        ]);
    }

    /**
     * Safely migrate tenant database
     */
    private function migrateTenantSafely(Tenant $tenant)
    {
        try {
            // Make sure tenant database exists
            if (!$this->checkTenantDatabaseExists($tenant)) {
                DB::statement("CREATE DATABASE IF NOT EXISTS `{$tenant->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Switch to tenant context
            $tenant->makeCurrent();
            
            // Run migrations for tenant database only
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            
        } finally {
            // Always end tenancy context
            Tenancy::end();
        }
    }

    /**
     * Safely seed tenant database
     */
    private function seedTenantSafely(Tenant $tenant)
    {
        try {
            // Switch to tenant context
            $tenant->makeCurrent();
            
            // Run seeders for tenant database only
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            
        } finally {
            // Always end tenancy context
            Tenancy::end();
        }
    }

    /**
     * Check if tenant database exists
     */
    private function checkTenantDatabaseExists(Tenant $tenant): bool
    {
        try {
            $databases = DB::select('SHOW DATABASES');
            $databaseNames = array_column($databases, 'Database');
            return in_array($tenant->database_name, $databaseNames);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get tenant statistics
     */
    private function getTenantStatistics(Tenant $tenant): array
    {
        try {
            if (!$this->checkTenantDatabaseExists($tenant)) {
                return [
                    'database_exists' => false,
                    'migrations_run' => 0,
                    'pending_migrations' => 0,
                    'database_size' => '0 MB',
                    'table_count' => 0,
                    'last_migration' => null,
                ];
            }

            $tenant->makeCurrent();
            
            // Check migrations
            $migrationsRun = 0;
            $pendingMigrations = 0;
            
            if (Schema::hasTable('migrations')) {
                $migrationsRun = DB::table('migrations')->count();
            }
            
            // Get database size
            $dbSize = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS DB_SIZE_MB 
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$tenant->database_name]);
            
            $databaseSize = ($dbSize[0]->DB_SIZE_MB ?? 0) . ' MB';
            
            // Get table count
            $tableCount = count(DB::select("SHOW TABLES"));
            
            Tenancy::end();
            
            return [
                'database_exists' => true,
                'migrations_run' => $migrationsRun,
                'pending_migrations' => $pendingMigrations,
                'database_size' => $databaseSize,
                'table_count' => $tableCount,
                'last_migration' => $migrationsRun > 0 ? now()->format('c') : null,
            ];
            
        } catch (\Exception $e) {
            Tenancy::end();
            return [
                'database_exists' => false,
                'error' => $e->getMessage(),
                'migrations_run' => 0,
                'pending_migrations' => 0,
                'database_size' => '0 MB',
                'table_count' => 0,
            ];
        }
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_type' => config('database.default'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_limit' => ini_get('memory_limit'),
            'server_time' => now()->format('c'),
            'timezone' => config('app.timezone'),
        ];
    }
}
