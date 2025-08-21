<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TenantAnalytics extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    
    public ?Tenant $tenant = null;
    public array $analytics = [];
    public string $timeRange = '7d'; // 1d, 7d, 30d, 90d
    public array $chartData = [];
    public bool $autoRefresh = false;
    public array $availableTenants = []; // Add missing property
    public array $topTenants = []; // Add missing topTenants property

    protected $listeners = ['refreshAnalytics'];

    public function mount(?string $tenantId = null)
    {
        if ($tenantId) {
            $this->tenant = Tenant::find($tenantId);
        }
        
        // Load available tenants for dropdown
        $this->availableTenants = Tenant::all()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ];
        })->toArray();
        
        // Load top tenants for the table display
        $this->loadTopTenants();
        
        $this->refreshAnalytics();
    }

    public function refreshAnalytics()
    {
        if ($this->tenant) {
            $this->analytics = $this->getTenantAnalytics($this->tenant);
            $this->chartData = $this->getChartData($this->tenant);
        } else {
            $this->analytics = $this->getSystemAnalytics();
            $this->chartData = $this->getSystemChartData();
        }
    }

    public function setTimeRange(string $range)
    {
        $this->timeRange = $range;
        $this->refreshAnalytics();
    }

    private function getTenantAnalytics(Tenant $tenant): array
    {
        $cacheKey = "tenant_analytics_{$tenant->id}_{$this->timeRange}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenant) {
            try {
                $analytics = [];
                
                $tenant->run(function () use (&$analytics) {
                    $analytics = [
                        'database_info' => $this->getDatabaseInfo(),
                        'table_stats' => $this->getTableStats(),
                        'performance_metrics' => $this->getPerformanceMetrics(),
                        'usage_stats' => $this->getUsageStats(),
                        'growth_metrics' => $this->getGrowthMetrics(),
                    ];
                });

                return $analytics;
            } catch (\Exception $e) {
                return [
                    'error' => $e->getMessage(),
                    'database_info' => [],
                    'table_stats' => [],
                    'performance_metrics' => [],
                    'usage_stats' => [],
                    'growth_metrics' => [],
                ];
            }
        });
    }

    private function getSystemAnalytics(): array
    {
        $cacheKey = "system_analytics_{$this->timeRange}";
        
        return Cache::remember($cacheKey, 300, function () {
            $tenants = Tenant::all();
            $totalTenants = $tenants->count();
            
            return [
                'tenant_overview' => [
                    'total_tenants' => $totalTenants,
                    'active_tenants' => $tenants->where('status', 'active')->count(),
                    'inactive_tenants' => $tenants->where('status', 'inactive')->count(),
                    'suspended_tenants' => $tenants->where('status', 'suspended')->count(),
                ],
                'resource_usage' => $this->getSystemResourceUsage($tenants),
                'performance_overview' => $this->getSystemPerformanceOverview(),
                'growth_trends' => $this->getSystemGrowthTrends(),
            ];
        });
    }

    private function getDatabaseInfo(): array
    {
        try {
            $databaseName = DB::connection()->getDatabaseName();
            
            // Get database size
            $sizeQuery = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                COUNT(*) AS table_count
                FROM information_schema.tables 
                WHERE table_schema = ?";
            
            $result = DB::select($sizeQuery, [$databaseName]);
            
            return [
                'name' => $databaseName,
                'size_mb' => $result[0]->size_mb ?? 0,
                'table_count' => $result[0]->table_count ?? 0,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'name' => 'Unknown',
                'size_mb' => 0,
                'table_count' => 0,
            ];
        }
    }

    private function getTableStats(): array
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $stats = [];
            
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                
                try {
                    $count = DB::table($tableName)->count();
                    $stats[] = [
                        'name' => $tableName,
                        'rows' => $count,
                        'size_estimate' => $this->estimateTableSize($tableName),
                    ];
                } catch (\Exception $e) {
                    $stats[] = [
                        'name' => $tableName,
                        'rows' => 0,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            // Sort by row count
            usort($stats, function ($a, $b) {
                return ($b['rows'] ?? 0) <=> ($a['rows'] ?? 0);
            });
            
            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getPerformanceMetrics(): array
    {
        try {
            $start = microtime(true);
            
            // Test query performance
            DB::select('SELECT 1');
            $queryTime = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            return [
                'avg_query_time' => round($queryTime, 2),
                'connection_time' => round($queryTime, 2),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'avg_query_time' => 0,
                'connection_time' => 0,
                'memory_usage' => 0,
                'peak_memory' => 0,
            ];
        }
    }

    private function getUsageStats(): array
    {
        $days = $this->getDaysFromTimeRange();
        $startDate = Carbon::now()->subDays($days);
        
        return [
            'period' => $this->timeRange,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'daily_activity' => $this->getDailyActivity($startDate),
        ];
    }

    private function getGrowthMetrics(): array
    {
        $tables = ['users', 'orders', 'invoices', 'customers'];
        $growth = [];
        
        foreach ($tables as $table) {
            try {
                $total = DB::table($table)->count();
                $recent = DB::table($table)
                    ->where('created_at', '>=', Carbon::now()->subDays($this->getDaysFromTimeRange()))
                    ->count();
                
                $growth[$table] = [
                    'total' => $total,
                    'recent' => $recent,
                    'growth_rate' => $total > 0 ? round(($recent / $total) * 100, 1) : 0,
                ];
            } catch (\Exception $e) {
                $growth[$table] = [
                    'total' => 0,
                    'recent' => 0,
                    'growth_rate' => 0,
                    'error' => 'Table not found',
                ];
            }
        }
        
        return $growth;
    }

    private function getChartData($tenant = null): array
    {
        if ($tenant) {
            return $this->getTenantChartData($tenant);
        }
        
        return $this->getSystemChartData();
    }

    private function getTenantChartData(Tenant $tenant): array
    {
        $days = $this->getDaysFromTimeRange();
        $dates = [];
        $data = [];
        
        for ($i = $days; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dates[] = $date->format('M d');
            
            // This would ideally come from activity logs
            $data[] = rand(10, 100); // Placeholder data
        }
        
        return [
            'labels' => $dates,
            'datasets' => [
                [
                    'label' => 'Activity',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
        ];
    }

    private function getSystemChartData(): array
    {
        $days = $this->getDaysFromTimeRange();
        $dates = [];
        $tenantData = [];
        $activeData = [];
        
        for ($i = $days; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dates[] = $date->format('M d');
            
            $tenantData[] = Tenant::whereDate('created_at', '<=', $date)->count();
            $activeData[] = Tenant::where('status', 'active')
                ->whereDate('created_at', '<=', $date)
                ->count();
        }
        
        return [
            'labels' => $dates,
            'datasets' => [
                [
                    'label' => 'Total Tenants',
                    'data' => $tenantData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Active Tenants',
                    'data' => $activeData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                ],
            ],
        ];
    }

    private function getDaysFromTimeRange(): int
    {
        return match($this->timeRange) {
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
    }

    private function estimateTableSize(string $tableName): string
    {
        try {
            $query = "SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() AND table_name = ?";
            
            $result = DB::select($query, [$tableName]);
            return ($result[0]->size_mb ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getDailyActivity(Carbon $startDate): array
    {
        // This would ideally come from activity logs
        // For now, return placeholder data
        $activity = [];
        $current = $startDate->copy();
        
        while ($current->lte(Carbon::now())) {
            $activity[] = [
                'date' => $current->format('Y-m-d'),
                'activity_count' => rand(5, 50),
            ];
            $current->addDay();
        }
        
        return $activity;
    }

    private function getSystemResourceUsage($tenants): array
    {
        $totalSize = 0;
        $totalTables = 0;
        
        foreach ($tenants as $tenant) {
            try {
                $status = app(TenantService::class)->getTenantStatus($tenant);
                $totalSize += $status['database_size'] ?? 0;
                $totalTables += $status['table_count'] ?? 0;
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return [
            'total_database_size' => round($totalSize, 2),
            'total_tables' => $totalTables,
            'avg_size_per_tenant' => $tenants->count() > 0 ? round($totalSize / $tenants->count(), 2) : 0,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
    }

    private function getSystemPerformanceOverview(): array
    {
        return [
            'avg_response_time' => '25ms', // This would come from application monitoring
            'uptime' => '99.9%',
            'error_rate' => '0.1%',
            'throughput' => '1500 req/min',
        ];
    }

    private function getSystemGrowthTrends(): array
    {
        $days = $this->getDaysFromTimeRange();
        
        return [
            'tenants_created' => Tenant::where('created_at', '>=', Carbon::now()->subDays($days))->count(),
            'growth_rate' => '15%', // This would be calculated from historical data
            'projected_growth' => '25%',
        ];
    }

    private function loadTopTenants(): void
    {
        $this->topTenants = Tenant::select([
            'id',
            'name', 
            'database',
            'status',
            'created_at'
        ])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get()
        ->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->database ?? 'No database',
                'storage' => '0 MB', // This would be calculated from actual usage
                'requests' => rand(100, 1000) . 'k', // This would be from actual metrics
                'status' => $tenant->status,
                'status_color' => $this->getStatusColor($tenant->status),
            ];
        })
        ->toArray();
    }

    private function getStatusColor(string $status): string
    {
        return match($status) {
            'active' => 'text-green-600 bg-green-100',
            'inactive' => 'text-gray-600 bg-gray-100',
            'suspended' => 'text-yellow-600 bg-yellow-100',
            'blocked' => 'text-red-600 bg-red-100',
            default => 'text-gray-600 bg-gray-100',
        };
    }

    public function render()
    {
        return view('af-tenancy::livewire.admin.tenant-analytics')->extends($this->layout);
    }
}
