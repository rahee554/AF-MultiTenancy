<?php

namespace ArtflowStudio\Tenancy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;

class RealTimeMonitoringController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Get comprehensive real-time system statistics
     */
    public function getSystemStats(Request $request)
    {
        $cacheKey = 'system_realtime_stats';
        $ttl = $request->get('cache_ttl', 30); // 30 seconds default

        $stats = Cache::remember($cacheKey, $ttl, function () {
            return [
                'timestamp' => now()->toISOString(),
                'system' => $this->getSystemInfo(),
                'database' => $this->getDatabaseInfo(),
                'tenants' => $this->getTenantsInfo(),
                'performance' => $this->getPerformanceMetrics(),
                'connections' => $this->getConnectionStats(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'cache_ttl' => $ttl,
                'generated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get real-time tenant-specific statistics
     */
    public function getTenantStats(Request $request, $tenantId = null)
    {
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json(['error' => 'Tenant not found'], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $tenant->getRealTimeStats()
            ]);
        }

        // Get stats for all tenants
        $tenants = Tenant::all();
        $stats = [];

        foreach ($tenants as $tenant) {
            $stats[] = $tenant->getRealTimeStats();
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'total_tenants' => count($stats),
                'generated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get live database connection statistics
     */
    public function getConnectionStats()
    {
        try {
            // MySQL specific queries
            $processlist = DB::select('SHOW PROCESSLIST');
            $status = collect(DB::select('SHOW STATUS'))->pluck('Value', 'Variable_name');
            
            $connections = [
                'total_connections' => count($processlist),
                'active_connections' => collect($processlist)->where('Command', '!=', 'Sleep')->count(),
                'sleeping_connections' => collect($processlist)->where('Command', 'Sleep')->count(),
                'max_connections' => $status['max_connections'] ?? 'N/A',
                'threads_connected' => $status['Threads_connected'] ?? 'N/A',
                'threads_running' => $status['Threads_running'] ?? 'N/A',
                'connection_details' => collect($processlist)->map(function ($process) {
                    return [
                        'id' => $process->Id,
                        'user' => $process->User,
                        'host' => $process->Host,
                        'db' => $process->db,
                        'command' => $process->Command,
                        'time' => $process->Time,
                        'state' => $process->State,
                    ];
                })->toArray()
            ];

            return $connections;
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch connection stats',
                'message' => $e->getMessage(),
                'total_connections' => 'N/A',
            ];
        }
    }

    /**
     * Get system information
     */
    protected function getSystemInfo()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage' => [
                'current_bytes' => $memoryUsage,
                'current_mb' => round($memoryUsage / (1024 * 1024), 2),
                'limit' => $memoryLimit,
                'peak_bytes' => memory_get_peak_usage(true),
                'peak_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
            ],
            'server_time' => now()->toISOString(),
            'uptime' => $this->getSystemUptime(),
        ];
    }

    /**
     * Get database information
     */
    protected function getDatabaseInfo()
    {
        try {
            $variables = collect(DB::select('SHOW VARIABLES'))->pluck('Value', 'Variable_name');
            $status = collect(DB::select('SHOW STATUS'))->pluck('Value', 'Variable_name');
            
            return [
                'version' => $variables['version'] ?? 'Unknown',
                'charset' => $variables['character_set_server'] ?? 'Unknown',
                'uptime' => $status['Uptime'] ?? 'Unknown',
                'queries' => $status['Queries'] ?? 'Unknown',
                'slow_queries' => $status['Slow_queries'] ?? 'Unknown',
                'innodb_buffer_pool_size' => $variables['innodb_buffer_pool_size'] ?? 'Unknown',
                'max_connections' => $variables['max_connections'] ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch database info',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tenants information
     */
    protected function getTenantsInfo()
    {
        $tenants = Tenant::all();
        
        $statusCounts = $tenants->countBy('status');
        $recentlyAccessed = $tenants->where('last_accessed_at', '>', now()->subHour())->count();
        
        return [
            'total_tenants' => $tenants->count(),
            'active_tenants' => $statusCounts['active'] ?? 0,
            'inactive_tenants' => $statusCounts['inactive'] ?? 0,
            'blocked_tenants' => $statusCounts['blocked'] ?? 0,
            'recently_accessed' => $recentlyAccessed,
            'tenant_databases' => $this->getTenantDatabaseSizes(),
        ];
    }

    /**
     * Get tenant database sizes
     */
    protected function getTenantDatabaseSizes()
    {
        try {
            $databases = collect(DB::select('SHOW DATABASES'))->pluck('Database');
            $tenantDatabases = $databases->filter(function ($db) {
                return str_starts_with($db, 'tenant_') || str_starts_with($db, config('tenancy.database.prefix', 'tenant_'));
            });

            $sizes = [];
            foreach ($tenantDatabases as $database) {
                try {
                    $result = DB::select("
                        SELECT 
                            table_schema as 'database_name',
                            SUM(data_length + index_length) as 'size_bytes'
                        FROM information_schema.tables 
                        WHERE table_schema = ?
                        GROUP BY table_schema
                    ", [$database]);
                    
                    if (!empty($result)) {
                        $sizes[] = [
                            'database' => $database,
                            'size_bytes' => $result[0]->size_bytes ?? 0,
                            'size_mb' => round(($result[0]->size_bytes ?? 0) / (1024 * 1024), 2),
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip databases we can't access
                    continue;
                }
            }

            return $sizes;
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch database sizes',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics()
    {
        try {
            $status = collect(DB::select('SHOW STATUS'))->pluck('Value', 'Variable_name');
            
            return [
                'queries_per_second' => round(($status['Queries'] ?? 0) / ($status['Uptime'] ?? 1), 2),
                'slow_queries' => $status['Slow_queries'] ?? 0,
                'connections_per_second' => round(($status['Connections'] ?? 0) / ($status['Uptime'] ?? 1), 2),
                'innodb_buffer_pool_read_requests' => $status['Innodb_buffer_pool_read_requests'] ?? 0,
                'innodb_buffer_pool_reads' => $status['Innodb_buffer_pool_reads'] ?? 0,
                'cache_hit_ratio' => $this->calculateCacheHitRatio($status),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch performance metrics',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate cache hit ratio
     */
    protected function calculateCacheHitRatio($status)
    {
        $reads = $status['Innodb_buffer_pool_reads'] ?? 0;
        $readRequests = $status['Innodb_buffer_pool_read_requests'] ?? 0;
        
        if ($readRequests > 0) {
            return round((($readRequests - $reads) / $readRequests) * 100, 2);
        }
        
        return 0;
    }

    /**
     * Get system uptime (simplified)
     */
    protected function getSystemUptime()
    {
        try {
            if (PHP_OS_FAMILY === 'Linux') {
                $uptime = file_get_contents('/proc/uptime');
                $uptimeSeconds = (int) explode(' ', $uptime)[0];
                return [
                    'seconds' => $uptimeSeconds,
                    'formatted' => gmdate('H:i:s', $uptimeSeconds),
                ];
            }
            
            return ['message' => 'Uptime not available on this system'];
        } catch (\Exception $e) {
            return ['error' => 'Unable to fetch uptime'];
        }
    }

    /**
     * Clear all monitoring caches
     */
    public function clearCaches(Request $request)
    {
        try {
            Cache::forget('system_realtime_stats');
            
            // Clear tenant stats caches
            Tenant::all()->each(function ($tenant) {
                $tenant->clearStatsCache();
            });
            
            return response()->json([
                'success' => true,
                'message' => 'All monitoring caches cleared successfully',
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear caches',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get live monitoring dashboard data
     */
    public function getDashboardData(Request $request)
    {
        $refresh = $request->get('refresh', false);
        $cacheKey = 'monitoring_dashboard_data';
        
        if ($refresh) {
            Cache::forget($cacheKey);
        }
        
        $data = Cache::remember($cacheKey, 30, function () {
            return [
                'summary' => [
                    'total_tenants' => Tenant::count(),
                    'active_tenants' => Tenant::where('status', 'active')->count(),
                    'blocked_tenants' => Tenant::where('status', 'blocked')->count(),
                    'total_databases' => count($this->getTenantDatabaseSizes()),
                    'total_connections' => $this->getConnectionStats()['total_connections'],
                ],
                'recent_activity' => [
                    'recent_tenants' => Tenant::latest()->take(5)->get(['id', 'name', 'status', 'created_at']),
                    'recently_accessed' => Tenant::whereNotNull('last_accessed_at')
                        ->orderBy('last_accessed_at', 'desc')
                        ->take(10)
                        ->get(['id', 'name', 'last_accessed_at']),
                ],
                'performance' => $this->getPerformanceMetrics(),
                'system' => $this->getSystemInfo(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'generated_at' => now()->toISOString(),
                'cache_used' => !$refresh,
            ]
        ]);
    }
}
