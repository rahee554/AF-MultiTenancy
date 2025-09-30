<?php

namespace ArtflowStudio\Tenancy\Commands\Analytics;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;
use ArtflowStudio\Tenancy\Services\TenantResourceQuotaService;

class TenantAnalyticsCommand extends Command
{
    protected $signature = 'tenant:analytics 
                          {tenant? : Tenant ID to analyze}
                          {--summary : Show summary only}
                          {--quotas : Show quota information}
                          {--health : Show health metrics}
                          {--recommendations : Show quota recommendations}';

    protected $description = 'Display tenant analytics and metrics';

    protected TenantAnalyticsService $analyticsService;
    protected TenantResourceQuotaService $quotaService;

    public function __construct(TenantAnalyticsService $analyticsService, TenantResourceQuotaService $quotaService)
    {
        parent::__construct();
        $this->analyticsService = $analyticsService;
        $this->quotaService = $quotaService;
    }

    public function handle()
    {
        $tenantId = $this->argument('tenant');

        if (!$tenantId) {
            return $this->showAllTenants();
        }

        $tenantModel = config('tenancy.tenant_model');
        $tenant = $tenantModel::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return 1;
        }

        $this->info("📊 Analytics for Tenant: {$tenant->id}");
        
        // Safely get domain if it exists
        $domain = 'No domain';
        try {
            $tenant->load('domains');
            if ($tenant->domains && $tenant->domains->count() > 0) {
                $domain = $tenant->domains->first()->domain;
            }
        } catch (\Exception $e) {
            // Keep default domain text
        }
        
        $this->line("Domain: " . $domain);
        $this->line('');

        if ($this->option('summary')) {
            $this->showSummary($tenant->id);
        } elseif ($this->option('quotas')) {
            $this->showQuotas($tenant->id);
        } elseif ($this->option('health')) {
            $this->showHealth($tenant->id);
        } elseif ($this->option('recommendations')) {
            $this->showRecommendations($tenant->id);
        } else {
            $this->showFullAnalytics($tenant->id);
        }

        return 0;
    }

    protected function showAllTenants()
    {
        $tenantModel = config('tenancy.tenant_model');
        $tenants = $tenantModel::with('domains')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return 0;
        }

        $this->info('📊 Tenant Analytics Overview');
        $this->line('');

        $tableData = [];
        foreach ($tenants as $tenant) {
            $quotaSummary = $this->quotaService->getQuotaSummary($tenant->id);
            $healthScore = $this->analyticsService->getHealthScore($tenant->id);
            
            $domain = $tenant->domains->first()?->domain ?? 'No domain';
            $status = $this->getStatusIcon($quotaSummary['overall_status']);
            
            $tableData[] = [
                $tenant->id,
                $domain,
                $healthScore . '%',
                $status . ' ' . $quotaSummary['overall_status'],
                $quotaSummary['exceeded'],
                $quotaSummary['warning'],
            ];
        }

        $this->table([
            'Tenant ID',
            'Domain',
            'Health Score',
            'Status',
            'Exceeded Quotas',
            'Warning Quotas',
        ], $tableData);

        $this->line('');
        $this->info('Use: php artisan tenant:analytics {tenant-id} for detailed analysis');
        
        return 0;
    }

    protected function showSummary(string $tenantId)
    {
        $metrics = $this->analyticsService->getTenantMetrics($tenantId);
        $quotaSummary = $this->quotaService->getQuotaSummary($tenantId);
        $healthScore = $this->analyticsService->getHealthScore($tenantId);

        $this->info('📈 Summary Report');
        $this->line('');

        // Health Score
        $this->line("🏥 Health Score: {$healthScore}%");
        
        // Quota Status
        $status = $this->getStatusIcon($quotaSummary['overall_status']);
        $this->line("📊 Quota Status: {$status} {$quotaSummary['overall_status']}");
        
        if (!empty($quotaSummary['critical_resources'])) {
            $this->line("⚠️  Critical Resources: " . implode(', ', $quotaSummary['critical_resources']));
        }

        // Quick Stats
        $this->line('');
        $this->info('🔢 Quick Stats:');
        
        if (isset($metrics['database']['table_count'])) {
            $this->line("   Database Tables: {$metrics['database']['table_count']}");
        }
        
        if (isset($metrics['database']['database_size'])) {
            $this->line("   Database Size: {$metrics['database']['database_size']} MB");
        }
        
        if (isset($metrics['usage']['active_users'])) {
            $this->line("   Active Users: {$metrics['usage']['active_users']}");
        }
    }

    protected function showQuotas(string $tenantId)
    {
        $quotas = $this->quotaService->checkQuotas($tenantId);

        $this->info('💾 Resource Quotas');
        $this->line('');

        $tableData = [];
        foreach ($quotas as $resource => $quota) {
            $status = $this->getStatusIcon($quota['status']);
            $percentage = $quota['percentage'] . '%';
            
            $tableData[] = [
                ucfirst(str_replace('_', ' ', $resource)),
                $quota['usage'],
                $quota['limit'],
                $percentage,
                $status . ' ' . $quota['status'],
                $quota['available'],
            ];
        }

        $this->table([
            'Resource',
            'Current Usage',
            'Limit',
            'Usage %',
            'Status',
            'Available',
        ], $tableData);
    }

    protected function showHealth(string $tenantId)
    {
        $metrics = $this->analyticsService->getTenantMetrics($tenantId);
        $health = $metrics['health'] ?? [];

        $this->info('🏥 Health Metrics');
        $this->line('');

        if (isset($health['status'])) {
            $statusIcon = $this->getStatusIcon($health['status']);
            $this->line("Status: {$statusIcon} {$health['status']}");
        }

        if (isset($health['uptime'])) {
            $this->line("Uptime: {$health['uptime']}%");
        }

        if (isset($health['last_activity'])) {
            $this->line("Last Activity: {$health['last_activity']}");
        }

        if (isset($health['maintenance_mode'])) {
            $maintenance = $health['maintenance_mode'] ? 'Yes' : 'No';
            $this->line("Maintenance Mode: {$maintenance}");
        }

        // Performance metrics
        if (isset($metrics['performance'])) {
            $this->line('');
            $this->info('⚡ Performance:');
            
            $perf = $metrics['performance'];
            if (isset($perf['avg_response_time'])) {
                $this->line("   Avg Response Time: {$perf['avg_response_time']}ms");
            }
            
            if (isset($perf['error_rate'])) {
                $this->line("   Error Rate: {$perf['error_rate']}%");
            }
            
            if (isset($perf['cache_hit_ratio'])) {
                $this->line("   Cache Hit Ratio: {$perf['cache_hit_ratio']}%");
            }
        }
    }

    protected function showRecommendations(string $tenantId)
    {
        $recommendations = $this->quotaService->getQuotaRecommendations($tenantId);

        $this->info('💡 Quota Recommendations');
        $this->line('');

        if (empty($recommendations)) {
            $this->line('✅ All quotas are optimally configured.');
            return;
        }

        $tableData = [];
        foreach ($recommendations as $rec) {
            $priority = $rec['priority'] === 'high' ? '🔴' : '🟡';
            
            $tableData[] = [
                $priority,
                ucfirst(str_replace('_', ' ', $rec['resource'])),
                $rec['current_limit'],
                $rec['suggested_limit'],
                $rec['reason'],
            ];
        }

        $this->table([
            'Priority',
            'Resource',
            'Current Limit',
            'Suggested Limit',
            'Reason',
        ], $tableData);

        $this->line('');
        $this->info('Use: php artisan tenant:quota:set {tenant} {resource} {limit} to update quotas');
    }

    protected function showFullAnalytics(string $tenantId)
    {
        $this->showSummary($tenantId);
        $this->line('');
        $this->showQuotas($tenantId);
        $this->line('');
        $this->showHealth($tenantId);
        
        $recommendations = $this->quotaService->getQuotaRecommendations($tenantId);
        if (!empty($recommendations)) {
            $this->line('');
            $this->showRecommendations($tenantId);
        }
    }

    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'ok', 'healthy' => '✅',
            'warning' => '⚠️',
            'exceeded', 'critical' => '🔴',
            default => '❓'
        };
    }
}
