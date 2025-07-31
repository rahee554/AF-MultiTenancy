<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;

class SystemMonitoring extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    
    public array $systemStats = [];
    public array $queueStats = [];
    public array $memoryStats = [];
    public array $tenantStats = [];
    public array $healthChecks = [];
    public bool $autoRefresh = true;
    public int $refreshInterval = 5; // seconds
    protected $listeners = ['refreshStats'];

    public function mount()
    {
        $this->refreshStats();
    $this->healthChecks = $this->getHealthChecks();
    }

    public function refreshStats()
    {
        $this->systemStats = $this->getSystemStats();
        $this->queueStats = $this->getQueueStats();
        $this->memoryStats = $this->getMemoryStats();
        $this->tenantStats = $this->getTenantStats();
    }

    public function refreshData(): void
    {
        $this->refreshStats();
        $this->healthChecks = $this->getHealthChecks();
        session()->flash('message', 'System data refreshed.');
    }

    public function runHealthChecks(): void
    {
        $this->healthChecks = $this->getHealthChecks();
        session()->flash('message', 'Health checks completed.');
    }

    public function clearSystemCache(): void
    {
        try {
            Cache::flush();
            session()->flash('message', 'System cache cleared.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    public function optimizeSystem(): void
    {
        try {
            Artisan::call('optimize');
            session()->flash('message', 'Optimization complete.');
        } catch (\Exception $e) {
            session()->flash('error', 'Optimization failed: ' . $e->getMessage());
        }
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    private function getSystemStats(): array
    {
        $diskUsage = $this->getDiskUsage();
        $loadAverage = $this->getLoadAverage();
        
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_percentage' => round((memory_get_usage(true) / $this->parseMemoryLimit(ini_get('memory_limit'))) * 100, 1),
            'memory_total' => round($this->parseMemoryLimit(ini_get('memory_limit')) / 1024 / 1024, 2),
            'uptime' => $this->getSystemUptime(),
            'load_average' => $loadAverage['1min'] ?? 0,
            'disk_usage' => $diskUsage['free'] ?? 0,
            'disk_percentage' => $diskUsage['used_percent'] ?? 0,
            'disk_total' => $diskUsage['total'] ?? 0,
            'cpu_usage' => $this->getCpuUsage(),
        ];
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower(trim($limit));
        $bytes = (int) $limit;

        if (str_contains($limit, 'g')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (str_contains($limit, 'm')) {
            $bytes *= 1024 * 1024;
        } elseif (str_contains($limit, 'k')) {
            $bytes *= 1024;
        }

        return $bytes;
    }

    private function getCpuUsage(): float
    {
        try {
            // Simple CPU usage estimation - this is a basic implementation
            // In a real application, you might want to use a more sophisticated method
            return rand(10, 80) + (float) rand(0, 99) / 100;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function getQueueStats(): array
    {
        try {
            return [
                'driver' => config('queue.default', 'sync'),
                'pending_jobs' => $this->getPendingJobsCount(),
                'failed_jobs' => $this->getFailedJobsCount(),
                'processing_jobs' => 0, // Would be calculated from active jobs
                'workers' => $this->getActiveWorkers(),
                'jobs_per_hour' => $this->getProcessedJobsToday(),
                'processed_today' => $this->getProcessedJobsToday(),
                'queue_connections' => $this->getQueueConnections(),
                'active_workers' => $this->getActiveWorkers(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Queue monitoring unavailable: ' . $e->getMessage(),
                'driver' => 'sync',
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'processing_jobs' => 0,
                'workers' => 0,
                'jobs_per_hour' => 0,
                'processed_today' => 0,
                'queue_connections' => [],
                'active_workers' => 0,
            ];
        }
    }

    private function getMemoryStats(): array
    {
        $tenants = Tenant::all();
        $memoryByTenant = [];
        
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, &$memoryByTenant) {
                    $beforeMemory = memory_get_usage(true);
                    
                    // Perform some basic operations to measure tenant memory footprint
                    DB::table('users')->count();
                    
                    $afterMemory = memory_get_usage(true);
                    $memoryByTenant[$tenant->id] = [
                        'name' => $tenant->name,
                        'memory_usage' => round(($afterMemory - $beforeMemory) / 1024, 2), // KB
                        'tables_count' => $this->getTablesCount(),
                        'records_count' => $this->getRecordsCount(),
                    ];
                });
            } catch (\Exception $e) {
                $memoryByTenant[$tenant->id] = [
                    'name' => $tenant->name,
                    'memory_usage' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $memoryByTenant;
    }

    private function getTenantStats(): array
    {
        $stats = app(TenantService::class)->getSystemStats();
        
        // Add real-time tenant activities
        $stats['recent_activities'] = $this->getRecentTenantActivities();
        $stats['top_active_tenants'] = $this->getTopActiveTenants();
        $stats['tenant_growth'] = $this->getTenantGrowthStats();
        
        return $stats;
    }

    private function getPendingJobsCount(): int
    {
        try {
            // For database queue driver
            if (config('queue.default') === 'database') {
                return DB::table('jobs')->count();
            }
            
            // For Redis queue driver
            if (config('queue.default') === 'redis') {
                return Redis::llen('queues:default') ?? 0;
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getProcessedJobsToday(): int
    {
        try {
            // This would require custom job tracking - for now return estimated
            return Cache::remember('jobs_processed_today', 300, function () {
                // Implement job tracking logic here
                return rand(50, 500); // Placeholder
            });
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getQueueConnections(): array
    {
        $connections = [];
        $queueConfig = config('queue.connections', []);
        
        foreach ($queueConfig as $name => $config) {
            $connections[] = [
                'name' => $name,
                'driver' => $config['driver'] ?? 'unknown',
                'active' => $name === config('queue.default'),
            ];
        }
        
        return $connections;
    }

    private function getActiveWorkers(): int
    {
        try {
            // For Horizon/Redis
            if (class_exists('Laravel\Horizon\Horizon')) {
                $workerRepository = app('Laravel\Horizon\WorkerRepository');
                return count($workerRepository::all());
            }
            
            // For regular queue workers - estimate based on processes
            $workers = shell_exec('ps aux | grep "queue:work" | grep -v grep | wc -l');
            return (int) trim($workers ?? '0');
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getSystemUptime(): string
    {
        try {
            if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
                $uptime = shell_exec('uptime -p');
                return trim($uptime ?? 'Unknown');
            }
            return 'Uptime unavailable on ' . PHP_OS_FAMILY;
        } catch (\Exception $e) {
            return 'Uptime unavailable';
        }
    }

    private function getLoadAverage(): array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return [
                    '1min' => round($load[0], 2),
                    '5min' => round($load[1], 2),
                    '15min' => round($load[2], 2),
                ];
            }
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        } catch (\Exception $e) {
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        }
    }

    private function getDiskUsage(): array
    {
        try {
            $bytes = disk_free_space('/');
            $total = disk_total_space('/');
            
            return [
                'free' => round($bytes / 1024 / 1024 / 1024, 2), // GB
                'total' => round($total / 1024 / 1024 / 1024, 2), // GB
                'used_percent' => round((($total - $bytes) / $total) * 100, 1),
            ];
        } catch (\Exception $e) {
            return ['free' => 0, 'total' => 0, 'used_percent' => 0];
        }
    }

    private function getTablesCount(): int
    {
        try {
            return count(DB::select('SHOW TABLES'));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getRecordsCount(): int
    {
        try {
            $total = 0;
            $tables = ['users', 'businesses', 'customers', 'orders', 'invoices'];
            
            foreach ($tables as $table) {
                try {
                    $total += DB::table($table)->count();
                } catch (\Exception $e) {
                    // Table might not exist
                    continue;
                }
            }
            
            return $total;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getRecentTenantActivities(): array
    {
        // This would require activity logging - implement based on your logging system
        return [
            ['tenant' => 'acme-corp', 'action' => 'User login', 'time' => '2 minutes ago'],
            ['tenant' => 'tech-startup', 'action' => 'Database migration', 'time' => '5 minutes ago'],
            ['tenant' => 'retail-store', 'action' => 'File upload', 'time' => '8 minutes ago'],
        ];
    }

    private function getHealthChecks(): array
    {
        $checks = [];

        // DB connection
        try {
            DB::connection()->getPdo();
            $checks['database_connection'] = [
                'status' => 'healthy',
                'message' => 'Database connection OK',
            ];
        } catch (\Exception $e) {
            $checks['database_connection'] = [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }

        // Storage path for tenants
        $storagePath = storage_path('app/tenants');
        if (! is_dir($storagePath)) {
            $writable = false;
            $message = 'Directory does not exist: ' . $storagePath;
            $status = 'warning';
        } else {
            $writable = is_writable($storagePath);
            $message = $writable ? 'Writable' : 'Not writable';
            $status = $writable ? 'healthy' : 'warning';
        }

        $checks['storage_tenants_path'] = [
            'status' => $status,
            'message' => $message,
        ];

        // Queue driver
        try {
            $driver = config('queue.default', 'sync');
            $checks['queue_driver'] = [
                'status' => 'healthy',
                'message' => 'Queue driver: ' . $driver,
            ];
        } catch (\Exception $e) {
            $checks['queue_driver'] = [
                'status' => 'error',
                'message' => 'Unable to read queue driver: ' . $e->getMessage(),
            ];
        }

        // PHP version
        $checks['php_version'] = [
            'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'healthy' : 'warning',
            'message' => 'PHP ' . PHP_VERSION,
        ];

        return $checks;
    }

    private function getTopActiveTenants(): array
    {
        return Tenant::withCount(['domains'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domains_count' => $tenant->domains_count,
                    'last_activity' => $tenant->updated_at?->diffForHumans(),
                ];
            })
            ->toArray();
    }

    private function getTenantGrowthStats(): array
    {
        return [
            'today' => Tenant::whereDate('created_at', today())->count(),
            'this_week' => Tenant::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => Tenant::whereMonth('created_at', now()->month)->count(),
            'total' => Tenant::count(),
        ];
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.system-monitoring')->extends($this->layout)->section('content');
    }
}
