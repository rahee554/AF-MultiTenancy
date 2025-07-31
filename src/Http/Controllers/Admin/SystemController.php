<?php

namespace ArtflowStudio\Tenancy\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use ArtflowStudio\Tenancy\Services\TenantService;

class SystemController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Show system dashboard
     */
    public function dashboard()
    {
        $stats = $this->tenantService->getSystemStats();
        $health = $this->tenantService->checkSystemHealth();

        // Get configuration information
        $cacheDriver = config('cache.default');
        $sessionDriver = config('session.driver');
        $tenancyBootstrappers = config('tenancy.bootstrappers', []);
        
        // Check if enhanced bootstrappers are enabled
        $enhancedSessionEnabled = in_array('ArtflowStudio\\Tenancy\\Bootstrappers\\SessionTenancyBootstrapper', $tenancyBootstrappers);
        $enhancedCacheEnabled = in_array('ArtflowStudio\\Tenancy\\Bootstrappers\\EnhancedCacheTenancyBootstrapper', $tenancyBootstrappers);

        return view('artflow-tenancy::admin.system.dashboard', compact(
            'stats',
            'health',
            'cacheDriver',
            'sessionDriver',
            'enhancedSessionEnabled',
            'enhancedCacheEnabled'
        ));
    }

    /**
     * Show configuration management
     */
    public function configuration()
    {
        $currentConfig = [
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'session_isolation' => config('artflow-tenancy.session.isolation_enabled'),
            'cache_isolation_mode' => config('artflow-tenancy.cache.isolation_mode'),
            'quota_enforcement' => config('artflow-tenancy.quotas.enabled'),
            'quota_warning_threshold' => config('artflow-tenancy.quotas.warning_threshold'),
        ];

        $tenancyBootstrappers = config('tenancy.bootstrappers', []);
        
        return view('artflow-tenancy::admin.system.configuration', compact(
            'currentConfig',
            'tenancyBootstrappers'
        ));
    }

    /**
     * Update cache configuration
     */
    public function updateCacheConfiguration(Request $request)
    {
        $request->validate([
            'cache_driver' => 'required|in:database,redis,file,array',
            'cache_isolation_mode' => 'required|in:database,prefix,tags',
        ]);

        // This would typically update configuration files
        // For now, we'll show what commands to run
        
        $commands = [];
        
        if ($request->cache_driver === 'redis') {
            $commands[] = 'php artisan tenancy:cache-setup redis';
        } else {
            $commands[] = 'php artisan tenancy:cache-setup database';
        }

        return redirect()
            ->route('admin.system.configuration')
            ->with('info', 'To apply these changes, run the following commands: ' . implode(', ', $commands));
    }

    /**
     * Enable enhanced tenancy features
     */
    public function enableEnhancedTenancy(Request $request)
    {
        $request->validate([
            'enable_session_isolation' => 'boolean',
            'enable_cache_isolation' => 'boolean',
            'cache_isolation_mode' => 'required_if:enable_cache_isolation,true|in:database,prefix,tags',
        ]);

        // This would update the tenancy.php config file to include our enhanced bootstrappers
        $configPath = config_path('tenancy.php');
        
        if (File::exists($configPath)) {
            $configContent = File::get($configPath);
            
            // Check if we need to update bootstrappers
            $newBootstrappers = [
                '\\Stancl\\Tenancy\\Bootstrappers\\DatabaseTenancyBootstrapper::class',
            ];
            
            if ($request->enable_session_isolation) {
                $newBootstrappers[] = 'ArtflowStudio\\Tenancy\\Bootstrappers\\SessionTenancyBootstrapper::class';
            }
            
            if ($request->enable_cache_isolation) {
                $newBootstrappers[] = 'ArtflowStudio\\Tenancy\\Bootstrappers\\EnhancedCacheTenancyBootstrapper::class';
            } else {
                $newBootstrappers[] = '\\Stancl\\Tenancy\\Bootstrappers\\CacheTenancyBootstrapper::class';
            }
            
            $newBootstrappers[] = '\\Stancl\\Tenancy\\Bootstrappers\\QueueTenancyBootstrapper::class';
            
            $message = 'Enhanced tenancy features configuration updated. ' .
                      'Please update config/tenancy.php bootstrappers to: [' . 
                      implode(', ', $newBootstrappers) . ']';
        } else {
            $message = 'tenancy.php config file not found. Please ensure the package is properly installed.';
        }

        return redirect()
            ->route('admin.system.configuration')
            ->with('info', $message);
    }

    /**
     * Run system maintenance
     */
    public function maintenance(Request $request)
    {
        $action = $request->get('action');
        $output = '';
        $success = true;

        try {
            switch ($action) {
                case 'clear_cache':
                    Artisan::call('cache:clear');
                    $output = 'Cache cleared successfully.';
                    break;
                    
                case 'clear_config':
                    Artisan::call('config:clear');
                    $output = 'Configuration cache cleared successfully.';
                    break;
                    
                case 'optimize':
                    Artisan::call('optimize');
                    $output = 'Application optimized successfully.';
                    break;
                    
                case 'migrate_tenants':
                    $results = $this->tenantService->migrateAllTenants();
                    $output = "Migration completed. Success: {$results['success']}, Failed: {$results['failed']}";
                    if (!empty($results['errors'])) {
                        $output .= "\nErrors: " . implode(', ', $results['errors']);
                        $success = false;
                    }
                    break;
                    
                default:
                    $output = 'Unknown action.';
                    $success = false;
            }
        } catch (\Exception $e) {
            $output = 'Error: ' . $e->getMessage();
            $success = false;
        }

        return redirect()
            ->route('admin.system.dashboard')
            ->with($success ? 'success' : 'error', $output);
    }

    /**
     * Get real-time system stats for AJAX
     */
    public function stats()
    {
        $stats = $this->tenantService->getSystemStats();
        $health = $this->tenantService->checkSystemHealth();

        return response()->json([
            'stats' => $stats,
            'health' => $health,
            'updated_at' => now()->toISOString(),
        ]);
    }
}
