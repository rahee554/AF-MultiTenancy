<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class Dashboard extends Component
{
    
    public array $stats = [];
    public array $recentActivities = [];
    public array $systemHealth = [];
    public array $queueStats = [];
    public bool $autoRefresh = true;
    public int $refreshInterval = 30; // seconds
    public array $required_permissions = [];

    protected $listeners = ['refreshDashboard'];
        protected $layout = 'artflow-tenancy::layout.app';

    public function mount(TenantService $tenantService)
    {
        $this->refreshDashboard($tenantService);
    }

    public function refreshDashboard(?TenantService $tenantService = null)
    {
        $tenantService = $tenantService ?? app(TenantService::class);
        
        try {
            $this->stats = $tenantService->getSystemStats();
        } catch (\Exception $e) {
            $this->stats = [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'error' => 'Failed to load system stats: ' . $e->getMessage(),
            ];
        }

        $this->recentActivities = $this->getRecentActivities();
        $this->systemHealth = $this->getSystemHealth();
        $this->queueStats = $this->getQueueStats();
    $this->required_permissions = $this->getRequiredPermissions();
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function clearCache()
    {
        try {
            Cache::flush();
            session()->flash('message', 'Cache cleared successfully');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    public function runHealthCheck()
    {
        try {
            $this->systemHealth = app(TenantService::class)->checkSystemHealth();
            session()->flash('message', 'Health check completed');
        } catch (\Exception $e) {
            session()->flash('error', 'Health check failed: ' . $e->getMessage());
        }
    }

    private function getRecentActivities(): array
    {
        // This would ideally come from an activity log system
        // For now, we'll create some sample data based on recent tenant activities
        $activities = [];

        try {
            // Recent tenant creations
            $recentTenants = Tenant::orderBy('created_at', 'desc')->limit(5)->get();
            foreach ($recentTenants as $tenant) {
                $activities[] = [
                    'type' => 'tenant_created',
                    'message' => "Tenant '{$tenant->name}' was created",
                    'time' => $tenant->created_at->diffForHumans(),
                    'icon' => 'plus-circle',
                    'color' => 'green',
                ];
            }

            // Recent tenant updates
            $updatedTenants = Tenant::orderBy('updated_at', 'desc')->limit(3)->get();
            foreach ($updatedTenants as $tenant) {
                if ($tenant->updated_at->gt($tenant->created_at)) {
                    $activities[] = [
                        'type' => 'tenant_updated',
                        'message' => "Tenant '{$tenant->name}' was updated",
                        'time' => $tenant->updated_at->diffForHumans(),
                        'icon' => 'edit',
                        'color' => 'blue',
                    ];
                }
            }

            // Sort by time
            usort($activities, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return array_slice($activities, 0, 10);
        } catch (\Exception $e) {
            return [
                [
                    'type' => 'error',
                    'message' => 'Failed to load activities: ' . $e->getMessage(),
                    'time' => 'now',
                    'icon' => 'exclamation-triangle',
                    'color' => 'red',
                ]
            ];
        }
    }

    private function getSystemHealth(): array
    {
        try {
            return Cache::remember('system_health', 60, function () {
                $health = [
                    'overall_status' => 'healthy',
                    'checks' => [],
                ];

                // Database check
                try {
                    DB::select('SELECT 1');
                    $health['checks']['database'] = [
                        'status' => 'healthy',
                        'message' => 'Database connection successful',
                    ];
                } catch (\Exception $e) {
                    $health['checks']['database'] = [
                        'status' => 'error',
                        'message' => 'Database connection failed',
                    ];
                    $health['overall_status'] = 'unhealthy';
                }

                // Cache check
                try {
                    $testKey = 'health_check_' . time();
                    Cache::put($testKey, 'test', 10);
                    $value = Cache::get($testKey);
                    Cache::forget($testKey);
                    
                    if ($value === 'test') {
                        $health['checks']['cache'] = [
                            'status' => 'healthy',
                            'message' => 'Cache system operational',
                        ];
                    } else {
                        throw new \Exception('Cache test failed');
                    }
                } catch (\Exception $e) {
                    $health['checks']['cache'] = [
                        'status' => 'warning',
                        'message' => 'Cache system issues detected',
                    ];
                    if ($health['overall_status'] === 'healthy') {
                        $health['overall_status'] = 'warning';
                    }
                }

                // Memory check
                $memoryUsage = memory_get_usage(true);
                $memoryLimit = $this->getMemoryLimit();
                $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

                if ($memoryPercent < 80) {
                    $health['checks']['memory'] = [
                        'status' => 'healthy',
                        'message' => sprintf('Memory usage: %.1f%%', $memoryPercent),
                    ];
                } elseif ($memoryPercent < 95) {
                    $health['checks']['memory'] = [
                        'status' => 'warning',
                        'message' => sprintf('High memory usage: %.1f%%', $memoryPercent),
                    ];
                    if ($health['overall_status'] === 'healthy') {
                        $health['overall_status'] = 'warning';
                    }
                } else {
                    $health['checks']['memory'] = [
                        'status' => 'error',
                        'message' => sprintf('Critical memory usage: %.1f%%', $memoryPercent),
                    ];
                    $health['overall_status'] = 'unhealthy';
                }

                return $health;
            });
        } catch (\Exception $e) {
            return [
                'overall_status' => 'error',
                'error' => $e->getMessage(),
                'checks' => [],
            ];
        }
    }

    private function getQueueStats(): array
    {
        try {
            $stats = [
                'driver' => config('queue.default'),
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'workers' => 0,
            ];

            // Get pending jobs count
            if (config('queue.default') === 'database') {
                $stats['pending_jobs'] = DB::table('jobs')->count();
                $stats['failed_jobs'] = DB::table('failed_jobs')->count();
            }

            // Estimate workers (this is a rough estimate)
            if (function_exists('shell_exec')) {
                $workers = shell_exec('ps aux | grep "queue:work" | grep -v grep | wc -l');
                $stats['workers'] = (int) trim($workers ?? '0');
            }

            return $stats;
        } catch (\Exception $e) {
            return [
                'driver' => 'unknown',
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'workers' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX; // No limit
        }
        
        $value = (int) $memoryLimit;
        $unit = strtolower(substr($memoryLimit, -1));
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.dashboard', [
            'required_permissions' => $this->required_permissions,
        ])->extends($this->layout);
    }

    /**
     * Get required permissions and environment info.
     *
     * @return array{
     *   storage: array{path: string, writable: bool, required_perms: string},
     *   views: array{path: string, exists: bool, writable: bool, can_create_tenant_folder: bool, required_perms: string},
     *   database: array{connection: string|null, accessible: bool},
     *   php: array{version: string, required: string}
     * }
     */
    private function getRequiredPermissions(): array
    {
        $storagePath = storage_path('app/tenants');
        $viewsBase = resource_path('views');
        $tenantViewsBase = resource_path('views/tenants');

        $viewsWritable = is_dir($tenantViewsBase)
            ? is_writable($tenantViewsBase)
            : (is_dir($viewsBase) && is_writable($viewsBase));

        return [
            'storage' => [
                'path' => $storagePath,
                'writable' => is_writable($storagePath),
                'required_perms' => 'drwxrwxr-x (775) or higher',
            ],
            'views' => [
                'path' => $tenantViewsBase,
                'exists' => is_dir($tenantViewsBase),
                'writable' => $viewsWritable,
                'can_create_tenant_folder' => $viewsWritable,
                'required_perms' => 'resources/views should be writable (e.g. 755 or owner-writable) to allow creating views/tenants/{tenantName}',
            ],
            'database' => [
                'connection' => config('database.default'),
                'accessible' => $this->checkDatabaseAccessible(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'required' => '>= 8.2',
            ],
        ];
    }

    private function checkDatabaseAccessible(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
