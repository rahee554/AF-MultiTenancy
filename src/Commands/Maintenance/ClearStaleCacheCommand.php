<?php

namespace ArtflowStudio\Tenancy\Commands\Maintenance;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ClearStaleCacheCommand extends Command
{
    protected $signature = 'tenancy:clear-stale-cache
                            {--tenant= : Clear cache for specific tenant UUID}
                            {--all : Clear cache for all tenants}
                            {--sessions : Also clear sessions}
                            {--force : Skip confirmation}';

    protected $description = 'Clear stale cache and sessions to prevent 403 Forbidden errors after database recreation';

    public function handle(): int
    {
        $this->info('🧹 AF-MultiTenancy: Clear Stale Cache & Sessions');
        $this->newLine();

        $tenantId = $this->option('tenant');
        $clearAll = $this->option('all');
        $clearSessions = $this->option('sessions');

        if (!$tenantId && !$clearAll) {
            $this->error('Please specify either --tenant=UUID or --all');
            return 1;
        }

        if ($clearAll && !$this->option('force')) {
            if (!$this->confirm('Clear cache and sessions for ALL tenants? This will log out all users.')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $tenants = $clearAll 
            ? Tenant::all() 
            : Tenant::where('id', $tenantId)->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return 1;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...");
        $this->newLine();

        $totalCacheCleared = 0;
        $totalSessionsCleared = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing: {$tenant->name} ({$tenant->id})");

            // Clear cache
            $cacheCleared = $this->clearTenantCache($tenant);
            $totalCacheCleared += $cacheCleared;

            // Clear sessions if requested
            if ($clearSessions) {
                $sessionsCleared = $this->clearTenantSessions($tenant);
                $totalSessionsCleared += $sessionsCleared;
            }

            $this->newLine();
        }

        // Summary
        $this->info('✅ Cache Clearing Complete');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tenants Processed', $tenants->count()],
                ['Cache Keys Cleared', $totalCacheCleared],
                ['Sessions Cleared', $clearSessions ? $totalSessionsCleared : 'N/A'],
            ]
        );

        // Clear application cache
        $this->info('Clearing application cache...');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        $this->line('   ✓ Application cache cleared');

        return 0;
    }

    private function clearTenantCache(Tenant $tenant): int
    {
        $tenantId = $tenant->id;
        $cleared = 0;

        try {
            // Clear Redis cache
            if (config('cache.default') === 'redis') {
                try {
                    $redis = Redis::connection();
                    $pattern = "tenant_{$tenantId}_*";
                    $keys = $redis->keys($pattern);
                    
                    if (!empty($keys)) {
                        $redis->del($keys);
                        $cleared = count($keys);
                        $this->line("   ✓ Cleared {$cleared} Redis cache keys");
                    } else {
                        $this->line("   • No Redis cache keys found");
                    }
                } catch (\Exception $e) {
                    $this->warn("   ⚠ Redis cache clear failed: " . $e->getMessage());
                }
            } else {
                // Database cache driver
                try {
                    $deletedPrefix = DB::table('cache')
                        ->where('key', 'like', "tenant_{$tenantId}_%")
                        ->delete();
                    
                    $deletedLaravel = DB::table('cache')
                        ->where('key', 'like', "laravel_cache:tenant_{$tenantId}_%")
                        ->delete();
                    
                    $cleared = $deletedPrefix + $deletedLaravel;
                    
                    if ($cleared > 0) {
                        $this->line("   ✓ Cleared {$cleared} database cache entries");
                    } else {
                        $this->line("   • No database cache entries found");
                    }
                } catch (\Exception $e) {
                    $this->warn("   ⚠ Database cache clear failed: " . $e->getMessage());
                }
            }

            // Clear tenant context cache
            try {
                $cacheService = app(\ArtflowStudio\Tenancy\Services\TenantContextCache::class);
                $domains = $tenant->domains;
                foreach ($domains as $domain) {
                    $cacheService->forget($domain->domain);
                }
                $this->line("   ✓ Cleared tenant context cache");
            } catch (\Exception $e) {
                $this->warn("   ⚠ Tenant context cache clear failed: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error("   ✗ Cache clear failed: " . $e->getMessage());
        }

        return $cleared;
    }

    private function clearTenantSessions(Tenant $tenant): int
    {
        $tenantId = $tenant->id;
        $cleared = 0;

        try {
            // Clear database sessions
            if (config('session.driver') === 'database') {
                $table = config('session.table', 'sessions');
                
                // Clear by tenant ID
                $deletedById = DB::table($table)
                    ->where('payload', 'like', "%{$tenantId}%")
                    ->delete();
                
                // Clear by domain
                $domains = $tenant->domains;
                $deletedByDomain = 0;
                foreach ($domains as $domain) {
                    $deletedByDomain += DB::table($table)
                        ->where('payload', 'like', "%{$domain->domain}%")
                        ->delete();
                }
                
                $cleared = $deletedById + $deletedByDomain;
                
                if ($cleared > 0) {
                    $this->line("   ✓ Cleared {$cleared} database sessions");
                } else {
                    $this->line("   • No database sessions found");
                }
            }

            // Clear file sessions
            if (config('session.driver') === 'file') {
                $sessionPath = storage_path('framework/sessions');
                if (is_dir($sessionPath)) {
                    $files = glob($sessionPath . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $content = file_get_contents($file);
                            if (strpos($content, $tenantId) !== false) {
                                unlink($file);
                                $cleared++;
                            }
                        }
                    }
                    if ($cleared > 0) {
                        $this->line("   ✓ Cleared {$cleared} file sessions");
                    } else {
                        $this->line("   • No file sessions found");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("   ✗ Session clear failed: " . $e->getMessage());
        }

        return $cleared;
    }
}
