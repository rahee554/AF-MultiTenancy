<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Bootstrappers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Illuminate\Support\Facades\Log;

class SafeFilesystemTenancyBootstrapper implements TenancyBootstrapper
{
    /** @var Application */
    protected $app;

    /** @var array */
    public $originalPaths = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->initializeOriginalPaths();
    }

    protected function initializeOriginalPaths()
    {
        $this->originalPaths = [
            'disks' => [],
            'storage' => $this->app->storagePath(),
            'asset_url' => $this->app['config']['app.asset_url'],
        ];

        // Pre-populate disk paths to avoid "undefined array key" errors
        foreach ($this->app['config']['tenancy.filesystem.disks'] ?? [] as $disk) {
            $this->originalPaths['disks'][$disk] = $this->app['config']["filesystems.disks.{$disk}.root"] ?? null;
        }

        $this->app['url']->macro('setAssetRoot', function ($root) {
            $this->assetRoot = $root;
            return $this;
        });
    }

    public function bootstrap(Tenant $tenant)
    {
        $suffix = $this->app['config']['tenancy.filesystem.suffix_base'] . $tenant->getTenantKey();

        // storage_path()
        if ($this->app['config']['tenancy.filesystem.suffix_storage_path'] ?? true) {
            $this->app->useStoragePath($this->originalPaths['storage'] . "/{$suffix}");
        }

        // asset()
        if ($this->app['config']['tenancy.filesystem.asset_helper_tenancy'] ?? true) {
            if ($this->originalPaths['asset_url']) {
                $this->app['config']['app.asset_url'] = ($this->originalPaths['asset_url'] ?? $this->app['config']['app.url']) . "/$suffix";
                $this->app['url']->setAssetRoot($this->app['config']['app.asset_url']);
            } else {
                $this->app['url']->setAssetRoot($this->app['url']->route('stancl.tenancy.asset', ['path' => '']));
            }
        }

        // Storage facade
        Storage::forgetDisk($this->app['config']['tenancy.filesystem.disks']);

        foreach ($this->app['config']['tenancy.filesystem.disks'] as $disk) {
            $originalRoot = $this->app['config']["filesystems.disks.{$disk}.root"];
            
            // Ensure we have the original path stored
            if (!isset($this->originalPaths['disks'][$disk])) {
                $this->originalPaths['disks'][$disk] = $originalRoot;
            }

            $finalPrefix = str_replace(
                ['%storage_path%', '%tenant%'],
                [storage_path(), $tenant->getTenantKey()],
                $this->app['config']["tenancy.filesystem.root_override.{$disk}"] ?? '',
            );

            if (! $finalPrefix) {
                $finalPrefix = $originalRoot
                    ? rtrim($originalRoot, '/') . '/'. $suffix
                    : $suffix;
            }

            $this->app['config']["filesystems.disks.{$disk}.root"] = $finalPrefix;
        }
    }

    public function revert()
    {
        try {
            // storage_path()
            $this->app->useStoragePath($this->originalPaths['storage']);

            // asset()
            $this->app['config']['app.asset_url'] = $this->originalPaths['asset_url'];
            $this->app['url']->setAssetRoot($this->app['config']['app.asset_url']);

            // Storage facade - with safety checks
            Storage::forgetDisk($this->app['config']['tenancy.filesystem.disks']);
            
            foreach ($this->app['config']['tenancy.filesystem.disks'] as $disk) {
                // Safety check to prevent "undefined array key" errors
                if (isset($this->originalPaths['disks'][$disk])) {
                    $this->app['config']["filesystems.disks.{$disk}.root"] = $this->originalPaths['disks'][$disk];
                } else {
                    // Fallback to current filesystem config if original path is missing
                    $fallbackRoot = $this->app['config']["filesystems.disks.{$disk}.root"] ?? storage_path("app/{$disk}");
                    $this->app['config']["filesystems.disks.{$disk}.root"] = $fallbackRoot;
                    
                    Log::warning("SafeFilesystemTenancyBootstrapper: Missing original path for disk '{$disk}', using fallback: {$fallbackRoot}");
                }
            }
        } catch (\Exception $e) {
            Log::error("SafeFilesystemTenancyBootstrapper revert failed: " . $e->getMessage());
            
            // Emergency fallback - restore filesystem config to defaults
            $this->restoreDefaultFilesystemConfig();
        }
    }

    protected function restoreDefaultFilesystemConfig()
    {
        // Restore basic filesystem configuration as emergency fallback
        $defaults = [
            'local' => storage_path('app/private'),
            'public' => storage_path('app/public'),
        ];

        foreach ($defaults as $disk => $path) {
            if (in_array($disk, $this->app['config']['tenancy.filesystem.disks'] ?? [])) {
                $this->app['config']["filesystems.disks.{$disk}.root"] = $path;
            }
        }
    }
}
