<?php

namespace ArtflowStudio\Tenancy\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Facades\Tenancy;

class TenantController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Display tenant dashboard with comprehensive statistics.
     */
    public function dashboard()
    {
        $stats = $this->tenantService->getSystemStats();
        $systemInfo = $this->getSystemInfo();
        $tenants = Tenant::with('domains')->get();
        $recentTenants = Tenant::latest()->limit(5)->get();
        
        // Enhanced statistics
        $enhancedStats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'inactive_tenants' => Tenant::where('status', 'inactive')->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'blocked_tenants' => Tenant::where('status', 'blocked')->count(),
            'total_domains' => \Stancl\Tenancy\Database\Models\Domain::count(),
            'total_connections' => $this->getTotalConnections(),
            'persistent_connections' => $this->getPersistentConnections(),
            'cache_size' => $this->getCacheSize(),
            'active_users_per_tenant' => $this->getActiveUsersPerTenant(),
            'database_sizes' => $this->getDatabaseSizes(),
            'migration_status' => $this->getMigrationStatusForAllTenants(),
            'last_activity' => $this->getLastActivity(),
        ];
        
        // Return JSON for API requests, view for web requests
        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => array_merge($stats, $enhancedStats),
                    'system_info' => $systemInfo,
                    'tenants' => $tenants,
                    'recent_tenants' => $recentTenants,
                ]
            ]);
        }
        
        // Return web view with all data
        return view('admin.tenants.dashboard', compact(
            'stats', 
            'systemInfo', 
            'tenants', 
            'recentTenants', 
            'enhancedStats'
        ));
    }

    /**
     * Get comprehensive tenant statistics.
     */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => $this->tenantService->getSystemStats()
        ]);
    }

    /**
     * Get database connection monitoring data.
     */
    public function connectionStats()
    {
        try {
            $connections = $this->getConnectionStats();
            $tenantConnections = $this->getTenantConnectionStats();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'connections' => $connections,
                    'tenant_connections' => $tenantConnections,
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active users per tenant.
     */
    public function activeUsers()
    {
        try {
            $activeUsers = [];
            $tenants = Tenant::where('status', 'active')->get();
            
            foreach ($tenants as $tenant) {
                try {
                    $tenant->makeCurrent();
                    
                    // Count active sessions in the last 30 minutes
                    $activeCount = DB::table('sessions')
                        ->where('last_activity', '>', now()->subMinutes(30)->timestamp)
                        ->count();
                    
                    // Count total users
                    $totalUsers = DB::table('users')->count();
                    
                    $activeUsers[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'domain' => $tenant->getPrimaryDomain(),
                        'active_users' => $activeCount,
                        'total_users' => $totalUsers,
                        'database_name' => $tenant->database_name
                    ];
                } catch (\Exception $e) {
                    $activeUsers[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'domain' => $tenant->getPrimaryDomain(),
                        'active_users' => 0,
                        'total_users' => 0,
                        'database_name' => $tenant->database_name,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Reset to central connection
            Tenancy::end();
            
            return response()->json([
                'success' => true,
                'data' => $activeUsers,
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
     * List all tenants with pagination.
     */
    public function index(Request $request)
    {
        $query = Tenant::with('domains'); // Load domains relationship
        
        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('database_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('domains', function($domainQuery) use ($search) {
                      $domainQuery->where('domain', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        $perPage = $request->input('per_page', 15);
        $tenants = $query->paginate($perPage);
        
        // Return JSON for API requests, view for web requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'data' => $tenants
            ]);
        }
        
        // Return web view
        return view('admin.tenants.index', compact('tenants'));
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create()
    {
        return view('admin.tenants.create');
    }

    /**
     * Show specific tenant details with comprehensive information.
     */
    public function show($uuid)
    {
        $tenant = Tenant::with('domains')->where('uuid', $uuid)->first();
        
        if (!$tenant) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }
            abort(404);
        }

        // Get migration status for this tenant
        $migrationStatus = $this->getTenantMigrationStatus($tenant);
        
        // Get tenant-specific statistics
        $stats = $this->getTenantStatistics($tenant);
        
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'tenant' => $tenant->toArray(),
                    'migration_status' => $migrationStatus,
                    'statistics' => $stats,
                ]
            ]);
        }

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'migrationStatus' => $migrationStatus,
            'statistics' => $stats,
        ]);
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'domain' => 'required|string|unique:domains,domain|max:255|regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/',
            'database_suffix' => 'nullable|string|max:100',
            'status' => 'in:active,inactive,suspended,blocked',
            'notes' => 'nullable|string',
            'run_migrations' => 'boolean',
            'run_seeders' => 'boolean',
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
            if ($request->input('run_migrations', false)) {
                $this->tenantService->migrateTenant($tenant);
                $operations[] = 'migrations';
            }
            
            // Run seeders if requested
            if ($request->input('run_seeders', false)) {
                $this->tenantService->seedTenant($tenant);
                $operations[] = 'seeders';
            }
            
            $message = "Tenant '{$tenant->name}' created successfully!";
            if (!empty($operations)) {
                $message .= ' (' . implode(' and ', $operations) . ' completed)';
            }
            
            // Return JSON for API requests, redirect for web requests
            if ($request->expectsJson() || $request->is('api/*')) {
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
            }
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', $message);
            
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create tenant: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withInput()
                        ->with('error', 'Failed to create tenant: ' . $e->getMessage());
        }
    }

    /**
     * Update tenant details.
     */
    public function update(Request $request, $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }
            return redirect()->route('admin.tenants.index')->with('error', 'Tenant not found');
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain,' . $tenant->id,
            'status' => 'in:active,inactive',
            'notes' => 'nullable|string'
        ]);

        try {
            $tenant->update($request->only(['name', 'domain', 'status', 'notes']));
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tenant updated successfully',
                    'data' => $tenant
                ]);
            }
            
            return redirect()->route('admin.tenants.index')
                           ->with('success', "Tenant '{$tenant->name}' updated successfully!");
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update tenant: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to update tenant: ' . $e->getMessage());
        }
    }

    /**
     * Delete tenant.
     */
    public function destroy($uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }
            return redirect()->route('admin.tenants.index')->with('error', 'Tenant not found');
        }

        try {
            $this->tenantService->deleteTenant($tenant);
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tenant deleted successfully'
                ]);
            }
            
            return redirect()->route('admin.tenants.index')
                           ->with('success', "Tenant '{$tenant->name}' deleted successfully!");
                           
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete tenant: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to delete tenant: ' . $e->getMessage());
        }
    }

    /**
     * Activate tenant.
     */
    public function activate(Request $request, $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->activateTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant activated successfully',
                'data' => $tenant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend tenant.
     */
    public function suspend(Request $request, $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->deactivateTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant suspended successfully',
                'data' => $tenant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate tenant database.
     */
    public function migrate(Request $request, $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->migrateTenant($tenant, $request->input('fresh', false));
            
            return response()->json([
                'success' => true,
                'message' => 'Migration completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Seed tenant database.
     */
    public function seed(Request $request, $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->seedTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Seeding completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Seeding failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status.
     */
    public function health()
    {
        $health = $this->tenantService->checkSystemHealth();
        
        return response()->json([
            'success' => true,
            'status' => $health['status'],
            'timestamp' => now()->toISOString(),
            'checks' => $health['checks']
        ]);
    }

    /**
     * Get live statistics.
     */
    public function liveStats()
    {
        $stats = $this->tenantService->getSystemStats();
        
        return response()->json([
            'success' => true,
            'data' => array_merge($stats, [
                'timestamp' => now()->toISOString(),
            ])
        ]);
    }

    /**
     * Get performance metrics.
     */
    public function performance()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round((microtime(true) - LARAVEL_START) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]
        ]);
    }

    /**
     * Reset application caches.
     */
    public function resetCache()
    {
        try {
            Cache::flush();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system information.
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_connection' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_connection' => config('queue.default'),
            'tenant_db_prefix' => config('tenancy.database.prefix', env('TENANT_DB_PREFIX', 'tenant_')),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
            'timezone' => config('app.timezone'),
            'debug_mode' => config('app.debug'),
        ];
    }

    // ===========================================
    // API METHODS
    // ===========================================

    /**
     * API: Get all tenants.
     */
    public function apiIndex(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $tenants = Tenant::latest()->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $tenants,
            'message' => 'Tenants retrieved successfully'
        ]);
    }

    /**
     * API: Create a new tenant.
     */
    public function apiStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants,domain|max:255',
            'status' => 'sometimes|in:active,inactive',
            'notes' => 'nullable|string',
            'database_name' => 'nullable|string|max:255',
            'custom_prefix' => 'nullable|string|max:50',
        ]);

        try {
            $tenant = $this->tenantService->createTenant(
                $request->name,
                $request->domain,
                $request->status ?? 'active',
                $request->database_name,
                $request->notes,
                $request->custom_prefix
            );

            return response()->json([
                'success' => true,
                'data' => $tenant,
                'message' => 'Tenant created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Get specific tenant.
     */
    public function apiShow(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => 'Tenant retrieved successfully'
        ]);
    }

    /**
     * API: Update tenant.
     */
    public function apiUpdate(Request $request, string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255|unique:tenants,domain,' . $tenant->id,
            'status' => 'sometimes|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        try {
            $tenant->update($request->only(['name', 'domain', 'status', 'notes']));
            
            return response()->json([
                'success' => true,
                'data' => $tenant->fresh(),
                'message' => 'Tenant updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Delete tenant.
     */
    public function apiDestroy(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->deleteTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Activate tenant.
     */
    public function apiActivate(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->activateTenant($tenant);
            
            return response()->json([
                'success' => true,
                'data' => $tenant->fresh(),
                'message' => 'Tenant activated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate tenant: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Suspend tenant.
     */
    public function apiSuspend(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->deactivateTenant($tenant);
            
            return response()->json([
                'success' => true,
                'data' => $tenant->fresh(),
                'message' => 'Tenant suspended successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend tenant: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Migrate tenant database.
     */
    public function apiMigrate(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->migrateTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant database migrated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to migrate tenant database: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Seed tenant database.
     */
    public function apiSeed(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        try {
            $this->tenantService->seedTenant($tenant);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant database seeded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to seed tenant database: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * API: Get system statistics.
     */
    public function apiStats()
    {
        $stats = $this->tenantService->getSystemStats();
        
        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'System statistics retrieved successfully'
        ]);
    }

    /**
     * API: Get system health.
     */
    public function apiHealth()
    {
        $health = $this->tenantService->checkSystemHealth();
        
        return response()->json([
            'success' => true,
            'data' => $health,
            'message' => 'System health retrieved successfully'
        ]);
    }

    /**
     * API: Reset cache.
     */
    public function apiResetCache()
    {
        try {
            Cache::tags(['tenants'])->flush();
        } catch (\Exception $e) {
            Cache::flush();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Cache reset successfully'
        ]);
    }

    /**
     * Get database connection statistics.
     */
    private function getConnectionStats()
    {
        try {
            // Get MySQL connection status
            $centralStats = DB::select("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Max_used_connections', 'Connections')");
            
            $stats = [
                'central_db' => [
                    'connected_threads' => 0,
                    'running_threads' => 0,
                    'max_used_connections' => 0,
                    'total_connections' => 0
                ]
            ];
            
            foreach ($centralStats as $stat) {
                switch ($stat->Variable_name) {
                    case 'Threads_connected':
                        $stats['central_db']['connected_threads'] = (int) $stat->Value;
                        break;
                    case 'Threads_running':
                        $stats['central_db']['running_threads'] = (int) $stat->Value;
                        break;
                    case 'Max_used_connections':
                        $stats['central_db']['max_used_connections'] = (int) $stat->Value;
                        break;
                    case 'Connections':
                        $stats['central_db']['total_connections'] = (int) $stat->Value;
                        break;
                }
            }
            
            return $stats;
        } catch (\Exception $e) {
            return [
                'central_db' => [
                    'connected_threads' => 0,
                    'running_threads' => 0,
                    'max_used_connections' => 0,
                    'total_connections' => 0,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get tenant-specific connection statistics.
     */
    private function getTenantConnectionStats()
    {
        $tenantStats = [];
        $tenants = Tenant::where('status', 'active')->get();
        
        foreach ($tenants as $tenant) {
            try {
                $dbName = $tenant->database_name;
                
                // Check if database exists
                $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                
                if (!empty($dbExists)) {
                    // Get table count
                    $tableCount = DB::select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?", [$dbName]);
                    $tables = $tableCount[0]->count ?? 0;
                    
                    $tenantStats[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'database_name' => $dbName,
                        'database_exists' => true,
                        'table_count' => (int) $tables,
                        'status' => 'connected'
                    ];
                } else {
                    $tenantStats[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'database_name' => $dbName,
                        'database_exists' => false,
                        'table_count' => 0,
                        'status' => 'missing_database'
                    ];
                }
            } catch (\Exception $e) {
                $tenantStats[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'database_name' => $tenant->database_name,
                    'database_exists' => false,
                    'table_count' => 0,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $tenantStats;
    }

    /**
     * Get total database connections count.
     */
    private function getTotalConnections(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get persistent connections count.
     */
    private function getPersistentConnections(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_cached'");
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache size information.
     */
    private function getCacheSize(): array
    {
        try {
            $cacheStore = config('cache.default');
            
            if ($cacheStore === 'redis') {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $info = $redis->info('memory');
                
                return [
                    'used_memory' => $info['used_memory_human'] ?? 'N/A',
                    'used_memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                    'total_keys' => $redis->dbsize(),
                ];
            } else {
                // For file-based cache, estimate size
                $cacheSize = $this->getFileCacheSize();
                return [
                    'used_memory' => $cacheSize['formatted'],
                    'used_memory_peak' => 'N/A',
                    'total_keys' => $cacheSize['files'],
                ];
            }
        } catch (\Exception $e) {
            return [
                'used_memory' => 'N/A',
                'used_memory_peak' => 'N/A',
                'total_keys' => 0,
            ];
        }
    }
    
    /**
     * Get file cache size information.
     */
    private function getFileCacheSize(): array
    {
        try {
            $cachePath = storage_path('framework/cache/data');
            $totalSize = 0;
            $fileCount = 0;
            
            if (is_dir($cachePath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cachePath),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $totalSize += $file->getSize();
                        $fileCount++;
                    }
                }
            }
            
            // Format size in human readable format
            $units = ['B', 'KB', 'MB', 'GB'];
            $bytes = $totalSize;
            $unitIndex = 0;
            
            while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
                $bytes /= 1024;
                $unitIndex++;
            }
            
            return [
                'size' => $totalSize,
                'formatted' => round($bytes, 2) . ' ' . $units[$unitIndex],
                'files' => $fileCount,
            ];
        } catch (\Exception $e) {
            return [
                'size' => 0,
                'formatted' => '0 B',
                'files' => 0,
            ];
        }
    }

    /**
     * Get active users per tenant.
     */
    private function getActiveUsersPerTenant(): array
    {
        $tenantsData = [];
        
        foreach (Tenant::with('domains')->get() as $tenant) {
            try {
                $activeUsers = 0;
                
                // Initialize tenancy to access tenant database
                tenancy()->initialize($tenant);
                
                // Count active users (logged in within last 30 minutes)
                if (tenancy()->initialized) {
                    $activeUsers = DB::connection('mysql')
                        ->table('users')
                        ->where('last_login_at', '>', now()->subMinutes(30))
                        ->count();
                }
                
                tenancy()->end();
                
                $tenantsData[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'active_users' => $activeUsers,
                    'domains' => $tenant->domains->pluck('domain')->toArray(),
                ];
            } catch (\Exception $e) {
                tenancy()->end();
                $tenantsData[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'active_users' => 0,
                    'domains' => $tenant->domains->pluck('domain')->toArray(),
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $tenantsData;
    }

    /**
     * Get database sizes for all tenants.
     */
    private function getDatabaseSizes(): array
    {
        $sizes = [];
        
        foreach (Tenant::all() as $tenant) {
            try {
                $dbName = $tenant->database_name;
                $sizeQuery = "SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                    FROM information_schema.tables 
                    WHERE table_schema = ?";
                
                $result = DB::select($sizeQuery, [$dbName]);
                $sizeMb = $result[0]->size_mb ?? 0;
                
                $sizes[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'database_name' => $dbName,
                    'size_mb' => $sizeMb,
                    'size_formatted' => $sizeMb . ' MB',
                ];
            } catch (\Exception $e) {
                $sizes[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'database_name' => $tenant->database_name,
                    'size_mb' => 0,
                    'size_formatted' => 'N/A',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $sizes;
    }

    /**
     * Get last activity information.
     */
    private function getLastActivity(): array
    {
        $lastTenant = Tenant::latest('created_at')->first();
        
        return [
            'last_tenant_created' => $lastTenant 
                ? \Carbon\Carbon::parse($lastTenant->created_at)->diffForHumans() 
                : 'Never',
            'last_login' => $this->getLastLoginAcrossAllTenants(),
            'system_uptime' => $this->getSystemUptime(),
        ];
    }

    /**
     * Get last login across all tenants.
     */
    private function getLastLoginAcrossAllTenants(): string
    {
        $lastLogin = 'Never';
        
        foreach (Tenant::all() as $tenant) {
            try {
                tenancy()->initialize($tenant);
                
                if (tenancy()->initialized) {
                    $userLastLogin = DB::connection('mysql')
                        ->table('users')
                        ->whereNotNull('last_login_at')
                        ->orderBy('last_login_at', 'desc')
                        ->first();
                    
                    if ($userLastLogin && $userLastLogin->last_login_at) {
                        $loginTime = \Carbon\Carbon::parse($userLastLogin->last_login_at);
                        if ($lastLogin === 'Never' || $loginTime->isAfter(\Carbon\Carbon::parse($lastLogin))) {
                            $lastLogin = $loginTime->diffForHumans();
                        }
                    }
                }
                
                tenancy()->end();
            } catch (\Exception $e) {
                tenancy()->end();
                continue;
            }
        }
        
        return $lastLogin;
    }

    /**
     * Get system uptime.
     */
    private function getSystemUptime(): string
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Uptime'");
            $seconds = $result[0]->Value ?? 0;
            
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            return "{$days}d {$hours}h {$minutes}m";
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Run migrations for all tenants.
     */
    public function migrateAllTenants()
    {
        $results = [];
        
        foreach (Tenant::all() as $tenant) {
            try {
                tenancy()->initialize($tenant);
                
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--force' => true,
                ]);
                
                tenancy()->end();
                
                $results[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'status' => 'success',
                    'message' => 'Migrations completed successfully',
                ];
            } catch (\Exception $e) {
                tenancy()->end();
                
                $results[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Migration process completed',
            'results' => $results,
        ]);
    }

    /**
     * Clear all caches.
     */
    public function clearAllCaches()
    {
        try {
            // Clear Laravel cache
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            
            // Clear cache based on driver
            $cacheStore = config('cache.default');
            if ($cacheStore === 'redis') {
                try {
                    \Illuminate\Support\Facades\Redis::flushall();
                } catch (\Exception $e) {
                    // Redis not available, ignore
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully',
                'cache_driver' => $cacheStore,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear caches: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration status for all tenants.
     */
    private function getMigrationStatusForAllTenants(): array
    {
        $migrationStatuses = [];
        
        foreach (Tenant::all() as $tenant) {
            $status = $this->getTenantMigrationStatus($tenant);
            $migrationStatuses[] = [
                'tenant_id' => $tenant->id,
                'tenant_uuid' => $tenant->uuid,
                'tenant_name' => $tenant->name,
                'database_name' => $tenant->database_name,
                'status' => $status['status'],
                'pending_migrations' => $status['pending_count'],
                'total_migrations' => $status['total_count'],
                'database_exists' => $status['database_exists'],
                'last_checked' => now()->toISOString(),
            ];
        }
        
        return $migrationStatuses;
    }

    /**
     * Get migration status for a specific tenant.
     */
    private function getTenantMigrationStatus($tenant): array
    {
        try {
            // Check if database exists
            $databaseExists = $this->checkTenantDatabaseExists($tenant);
            
            if (!$databaseExists) {
                return [
                    'status' => 'database_missing',
                    'pending_count' => 0,
                    'total_count' => 0,
                    'database_exists' => false,
                    'error' => 'Database does not exist',
                ];
            }

            // Switch to tenant context
            tenancy()->initialize($tenant);
            
            // Get migration files
            $migrationPath = database_path('migrations/tenant');
            $migrationFiles = [];
            
            if (is_dir($migrationPath)) {
                $migrationFiles = glob($migrationPath . '/*.php');
            }
            
            // Get ran migrations
            $ranMigrations = [];
            try {
                $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();
            } catch (\Exception $e) {
                // Migration table doesn't exist
            }
            
            $totalMigrations = count($migrationFiles);
            $pendingMigrations = $totalMigrations - count($ranMigrations);
            
            $status = 'up_to_date';
            if ($pendingMigrations > 0) {
                $status = 'pending_migrations';
            } elseif (empty($ranMigrations) && $totalMigrations > 0) {
                $status = 'not_migrated';
            }
            
            return [
                'status' => $status,
                'pending_count' => $pendingMigrations,
                'total_count' => $totalMigrations,
                'database_exists' => true,
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'pending_count' => 0,
                'total_count' => 0,
                'database_exists' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            // Always switch back to central
            tenancy()->end();
        }
    }

    /**
     * Check if tenant database exists.
     */
    private function checkTenantDatabaseExists($tenant): bool
    {
        try {
            $databaseName = $tenant->database_name ?: "tenant_{$tenant->id}";
            $databases = DB::select("SHOW DATABASES LIKE ?", [$databaseName]);
            return !empty($databases);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update tenant status.
     */
    public function updateStatus(Request $request, $uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $request->validate([
                'status' => 'required|in:active,inactive,suspended,blocked'
            ]);
            
            $oldStatus = $tenant->status;
            $tenant->status = $request->status;
            $tenant->save();
            
            return response()->json([
                'success' => true,
                'message' => "Tenant status updated from {$oldStatus} to {$request->status}",
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'old_status' => $oldStatus,
                    'new_status' => $request->status,
                    'updated_at' => $tenant->updated_at?->format('c'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Migrate specific tenant database.
     */
    public function migrateTenant($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Initialize tenant context
            tenancy()->initialize($tenant);
            
            // Run migrations
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant migrations completed successfully',
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'output' => $output,
                    'migrated_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Seed specific tenant database.
     */
    public function seedTenant($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Initialize tenant context
            tenancy()->initialize($tenant);
            
            // Run seeders
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--force' => true,
            ]);
            
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant database seeded successfully',
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'output' => $output,
                    'seeded_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Seeding failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Get tenant-specific statistics.
     */
    private function getTenantStatistics($tenant): array
    {
        try {
            // Initialize tenant context
            tenancy()->initialize($tenant);
            
            $stats = [
                'active_users' => 0,
                'database_size' => 'N/A',
                'total_tables' => 0,
                'last_activity' => 'Never',
            ];
            
            // Get database size
            $databaseSizes = $this->getDatabaseSizes();
            $tenantDbSize = collect($databaseSizes)->where('tenant_id', $tenant->id)->first();
            if ($tenantDbSize) {
                $stats['database_size'] = $tenantDbSize['size_formatted'];
            }
            
            // Get table count
            try {
                $tables = DB::select('SHOW TABLES');
                $stats['total_tables'] = count($tables);
            } catch (\Exception $e) {
                $stats['total_tables'] = 'N/A';
            }
            
            // Get active users (if users table exists)
            try {
                if (Schema::hasTable('users')) {
                    $stats['active_users'] = DB::table('users')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count();
                }
            } catch (\Exception $e) {
                // Users table might not exist
            }
            
            // Get last activity
            try {
                if (Schema::hasTable('users')) {
                    $lastUser = DB::table('users')
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    if ($lastUser && $lastUser->updated_at) {
                        $stats['last_activity'] = \Carbon\Carbon::parse($lastUser->updated_at)->diffForHumans();
                    }
                }
            } catch (\Exception $e) {
                // Handle gracefully
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            return [
                'active_users' => 'Error',
                'database_size' => 'Error',
                'total_tables' => 'Error',
                'last_activity' => 'Error',
            ];
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Reset tenant database (drop all tables and re-migrate).
     */
    public function resetTenant($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Initialize tenant context
            tenancy()->initialize($tenant);
            
            // Fresh migrate (drop all tables and re-create)
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant database reset successfully',
                'data' => [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'output' => $output,
                    'reset_at' => now()->format('c'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reset failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Add domain to tenant.
     */
    public function addDomain(Request $request, $uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $request->validate([
                'domain' => 'required|string|unique:domains,domain'
            ]);
            
            $domain = \Stancl\Tenancy\Database\Models\Domain::create([
                'domain' => $request->domain,
                'tenant_id' => $tenant->id,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Domain added successfully',
                'data' => [
                    'domain' => $domain,
                    'tenant_uuid' => $tenant->uuid,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add domain: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove domain.
     */
    public function removeDomain($domainId)
    {
        try {
            $domain = \Stancl\Tenancy\Database\Models\Domain::findOrFail($domainId);
            $domain->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Domain removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove domain: ' . $e->getMessage(),
            ], 500);
        }
    }
}
