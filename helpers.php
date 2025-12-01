<?php

use ArtflowStudio\Tenancy\Models\Domain;
use ArtflowStudio\Tenancy\Models\Tenant;

// Note: tenant_asset() is defined by stancl/tenancy
// We use the TenantAssetService in app/Services for custom URL generation
// instead of using this helper, to avoid URL routing through /tenancy/assets/ routes

/**
 * Get tenant domain from localhost URL parameter or current request
 * For localhost, checks for 'url' query parameter: ?url=al-emaan.pk
 *
 * @return string|null Domain name or null if not found
 */
function get_current_domain(): ?string
{
    if (function_exists('request')) {
        $host = request()->getHost();

        // Check if localhost - if so, get domain from URL parameter
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $urlParam = request()->query('url');
            if ($urlParam) {
                return strtolower($urlParam);
            }
        }

        return strtolower($host);
    }

    return null;
}

if (! function_exists('tenant_pwa_asset')) {
    /**
     * Generate URL for tenant-specific PWA asset
     *
     * @param  string  $path  Path relative to tenant's PWA folder
     * @param  Tenant|null  $tenant  Optional tenant (auto-detects from current domain if null)
     * @return string Full URL to tenant PWA asset
     */
    function tenant_pwa_asset(string $path, ?Tenant $tenant = null): string
    {
        // Try to get tenant from context, then from current domain
        if (! $tenant) {
            $tenant = tenancy()->tenant;

            if (! $tenant) {
                $currentDomain = get_current_domain();
                if ($currentDomain) {
                    $domainModel = Domain::where('domain', $currentDomain)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        if (! $tenant) {
            return asset($path);
        }

        $domain = $tenant->domains()->first()->domain ?? 'default';
        $domainFolder = tenant_domain_folder($domain);

        $path = ltrim($path, '/');

        // Return relative path pointing to public/storage symlink
        return '/storage/tenants/'.$domainFolder.'/pwa/'.$path;
    }
}

if (! function_exists('tenant_seo_asset')) {
    /**
     * Generate URL for tenant-specific SEO asset
     *
     * @param  string  $path  Path relative to tenant's SEO folder
     * @param  Tenant|null  $tenant  Optional tenant (auto-detects from current domain if null)
     * @return string Full URL to tenant SEO asset
     */
    function tenant_seo_asset(string $path, ?Tenant $tenant = null): string
    {
        // Try to get tenant from context, then from current domain
        if (! $tenant) {
            $tenant = tenancy()->tenant;

            if (! $tenant) {
                $currentDomain = get_current_domain();
                if ($currentDomain) {
                    $domainModel = Domain::where('domain', $currentDomain)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        if (! $tenant) {
            return asset($path);
        }

        $domain = $tenant->domains()->first()->domain ?? 'default';
        $domainFolder = tenant_domain_folder($domain);

        $path = ltrim($path, '/');

        // Return relative path pointing to public/storage symlink
        return '/storage/tenants/'.$domainFolder.'/seo/'.$path;
    }
}

if (! function_exists('tenant_path')) {
    /**
     * Get absolute storage path for tenant's folder
     *
     * @param  string  $subfolder  Subfolder (assets, pwa, seo, etc.)
     * @param  Tenant|null  $tenant  Optional tenant (auto-detects from current domain if null)
     * @return string Absolute path to tenant folder
     */
    function tenant_path(string $subfolder = '', ?Tenant $tenant = null): string
    {
        // Try to get tenant from context, then from current domain
        if (! $tenant) {
            $tenant = tenancy()->tenant;

            if (! $tenant) {
                $currentDomain = get_current_domain();
                if ($currentDomain) {
                    $domainModel = Domain::where('domain', $currentDomain)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        if (! $tenant) {
            return base_path('storage/app/public');
        }

        $domain = $tenant->domains()->first()->domain ?? 'default';
        $domainFolder = tenant_domain_folder($domain);

        // Use base_path instead of storage_path to avoid tenant-specific storage overrides
        $basePath = base_path("storage/app/public/tenants/{$domainFolder}");

        if ($subfolder) {
            $subfolder = trim($subfolder, '/');

            return "{$basePath}/{$subfolder}";
        }

        return $basePath;
    }
}

if (! function_exists('tenant_url')) {
    /**
     * Get public URL path for tenant's folder
     *
     * @param  string  $subfolder  Subfolder (assets, pwa, seo, etc.)
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Public URL path
     */
    function tenant_url(string $subfolder = '', ?Tenant $tenant = null): string
    {
        if (! $tenant) {
            $tenant = tenancy()->tenant;

            if (! $tenant) {
                $currentDomain = get_current_domain();
                if ($currentDomain) {
                    $domainModel = Domain::where('domain', $currentDomain)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        if (! $tenant) {
            return '/storage';
        }

        $domain = $tenant->domains()->first()->domain ?? 'default';
        $domainFolder = tenant_domain_folder($domain);

        $basePath = "/storage/tenants/{$domainFolder}";

        if ($subfolder) {
            $subfolder = trim($subfolder, '/');

            return "{$basePath}/{$subfolder}";
        }

        return $basePath;
    }
}

if (! function_exists('tenant_domain_folder')) {
    /**
     * Get domain folder name (exact domain as-is, lowercased)
     *
     * @return string Domain folder name (exact domain)
     */
    function tenant_domain_folder(string $domain): string
    {
        // Use exact domain name, just lowercase it
        // tenant1.local -> tenant1.local
        // subdomain.example.com -> subdomain.example.com
        return strtolower($domain);
    }
}

if (! function_exists('current_tenant')) {
    /**
     * Get current tenant instance
     */
    function current_tenant(): ?Tenant
    {
        return tenancy()->tenant;
    }
}

if (! function_exists('tenant_config')) {
    /**
     * Get tenant-specific configuration value
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $default  Default value
     * @param  Tenant|null  $tenant  Optional tenant
     * @return mixed
     */
    function tenant_config(string $key, $default = null, ?Tenant $tenant = null)
    {
        $tenant = $tenant ?? tenancy()->tenant;

        if (! $tenant) {
            return $default;
        }

        // Try to get from tenant's config array
        if ($tenant->config && isset($tenant->config[$key])) {
            return $tenant->config[$key];
        }

        return $default;
    }
}

if (! function_exists('tenant_private_path')) {
    /**
     * Get absolute storage path for tenant's PRIVATE folder
     * ⚠️  IMPORTANT: Private folders are NOT accessible via web
     *
     * Use for: Backups, logs, cache, temp files, private documents
     *
     * @param  string  $subfolder  Subfolder (backups, logs, cache, etc.)
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Absolute path to tenant private folder
     */
    function tenant_private_path(string $subfolder = '', ?Tenant $tenant = null): string
    {
        if (! $tenant) {
            $tenant = tenancy()->tenant;

            if (! $tenant) {
                $currentDomain = get_current_domain();
                if ($currentDomain) {
                    $domainModel = Domain::where('domain', $currentDomain)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        if (! $tenant) {
            return base_path('storage/app/private');
        }

        $domain = $tenant->domains()->first()->domain ?? 'default';
        $domainFolder = tenant_domain_folder($domain);

        $basePath = base_path("storage/app/private/tenants/{$domainFolder}");

        if ($subfolder) {
            $subfolder = trim($subfolder, '/');

            return "{$basePath}/{$subfolder}";
        }

        return $basePath;
    }
}

if (! function_exists('tenant_private_dir')) {
    /**
     * Alias for tenant_private_path() - Get tenant private directory path
     *
     * @param  string  $subfolder  Subfolder name
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Absolute path (NOT web accessible)
     */
    function tenant_private_dir(string $subfolder = '', ?Tenant $tenant = null): string
    {
        return tenant_private_path($subfolder, $tenant);
    }
}

if (! function_exists('tenant_dir')) {
    /**
     * Alias for tenant_path() - Get tenant public directory path
     *
     * @param  string  $subfolder  Subfolder name (assets, pwa, seo, etc.)
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Absolute path to tenant public folder
     */
    function tenant_dir(string $subfolder = '', ?Tenant $tenant = null): string
    {
        return tenant_path($subfolder, $tenant);
    }
}

if (! function_exists('tenant_backup_path')) {
    /**
     * Get path for tenant backups
     *
     * @param  string  $filename  Optional backup filename
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Path to backup file or directory
     */
    function tenant_backup_path(string $filename = '', ?Tenant $tenant = null): string
    {
        $backupDir = tenant_private_path('backups', $tenant);

        if ($filename) {
            return "{$backupDir}/{$filename}";
        }

        return $backupDir;
    }
}

if (! function_exists('tenant_logs_path')) {
    /**
     * Get path for tenant logs
     *
     * @param  string  $filename  Optional log filename
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Path to log file or directory
     */
    function tenant_logs_path(string $filename = '', ?Tenant $tenant = null): string
    {
        $logsDir = tenant_private_path('logs', $tenant);

        if ($filename) {
            return "{$logsDir}/{$filename}";
        }

        return $logsDir;
    }
}

if (! function_exists('tenant_cache_path')) {
    /**
     * Get path for tenant cache
     *
     * @param  string  $filename  Optional cache filename
     * @param  Tenant|null  $tenant  Optional tenant
     * @return string Path to cache file or directory
     */
    function tenant_cache_path(string $filename = '', ?Tenant $tenant = null): string
    {
        $cacheDir = tenant_private_path('cache', $tenant);

        if ($filename) {
            return "{$cacheDir}/{$filename}";
        }

        return $cacheDir;
    }
}

/**
 * =====================================================
 * ARTFLOW CUSTOM TENANT ASSET HELPERS (af_ prefix)
 * =====================================================
 *
 * These are custom implementations that generate /storage/tenants/ URLs
 * instead of routing through /tenancy/assets/ like stancl/tenancy does.
 *
 * Use these functions to get proper symlinked storage URLs for tenant assets.
 * These are intentionally NOT guarded with function_exists() checks
 * to ensure they take precedence over any other implementations.
 */

/**
 * Generate URL for tenant-specific asset stored in public storage
 *
 * Generates URLs like: /storage/tenants/{domain}/assets/image.jpg
 * Instead of stancl's: /tenancy/assets/image.jpg
 *
 * @param  string  $path  Path relative to tenant's assets folder (e.g., 'image.jpg', 'css/style.css')
 * @param  Tenant|null  $tenant  Optional tenant (auto-detects from current domain if null)
 * @return string Full URL to tenant asset
 */
function af_tenant_asset(string $path, ?Tenant $tenant = null): string
{
    $domainToUse = null;

    if (! $tenant) {
        // PRIORITY 1: Check if we're on localhost with ?url= parameter
        // This takes precedence over tenancy context
        if (function_exists('request')) {
            $host = request()->getHost();
            if ($host === 'localhost' || $host === '127.0.0.1') {
                $urlParam = request()->query('url');
                if ($urlParam) {
                    $domainToUse = strtolower($urlParam);
                    $domainModel = Domain::where('domain', $domainToUse)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        // PRIORITY 2: Check tenancy context if not on localhost preview
        if (! $tenant) {
            $tenant = tenancy()->tenant;
        }

        // PRIORITY 3: Fall back to current domain detection
        if (! $tenant) {
            $currentDomain = get_current_domain();
            if ($currentDomain) {
                $domainToUse = $currentDomain;
                $domainModel = Domain::where('domain', $currentDomain)->first();
                if ($domainModel) {
                    $tenant = Tenant::find($domainModel->tenant_id);
                }
            }
        }
    }

    if (! $tenant) {
        return asset($path);
    }

    // Use the tracked domain, or fall back to tenant's first domain
    if (! $domainToUse) {
        $domainToUse = $tenant->domains()->first()?->domain ?? 'default';
    }

    $domainFolder = tenant_domain_folder($domainToUse);
    $path = ltrim($path, '/');

    return "/storage/tenants/{$domainFolder}/assets/{$path}";
}

/**
 * Generate URL for tenant-specific PWA asset
 *
 * Generates URLs like: /storage/tenants/{domain}/pwa/manifest.json
 *
 * @param  string  $path  Path relative to tenant's PWA folder
 * @param  Tenant|null  $tenant  Optional tenant (auto-detects from current domain if null)
 * @return string Full URL to tenant PWA asset
 */
function af_tenant_pwa_asset(string $path, ?Tenant $tenant = null): string
{
    $domainToUse = null;

    if (! $tenant) {
        // PRIORITY 1: Check if we're on localhost with ?url= parameter
        if (function_exists('request')) {
            $host = request()->getHost();
            if ($host === 'localhost' || $host === '127.0.0.1') {
                $urlParam = request()->query('url');
                if ($urlParam) {
                    $domainToUse = strtolower($urlParam);
                    $domainModel = Domain::where('domain', $domainToUse)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        // PRIORITY 2: Check tenancy context
        if (! $tenant) {
            $tenant = tenancy()->tenant;
        }

        // PRIORITY 3: Fall back to current domain detection
        if (! $tenant) {
            $currentDomain = get_current_domain();
            if ($currentDomain) {
                $domainToUse = $currentDomain;
                $domainModel = Domain::where('domain', $currentDomain)->first();
                if ($domainModel) {
                    $tenant = Tenant::find($domainModel->tenant_id);
                }
            }
        }
    }

    if (! $tenant) {
        return asset($path);
    }

    // Use the tracked domain, or fall back to tenant's first domain
    if (! $domainToUse) {
        $domainToUse = $tenant->domains()->first()?->domain ?? 'default';
    }

    $domainFolder = tenant_domain_folder($domainToUse);
    $path = ltrim($path, '/');

    return "/storage/tenants/{$domainFolder}/pwa/{$path}";
}

/**
 * Generate URL for tenant-specific SEO asset
 *
 * Generates URLs like: /storage/tenants/{domain}/seo/sitemap.xml
 *
 * @param  string  $path  Path relative to tenant's SEO folder
 * @param  Tenant|null  $tenant  Optional tenant (auto-detects from current domain if null)
 * @return string Full URL to tenant SEO asset
 */
function af_tenant_seo_asset(string $path, ?Tenant $tenant = null): string
{
    $domainToUse = null;

    if (! $tenant) {
        // PRIORITY 1: Check if we're on localhost with ?url= parameter
        if (function_exists('request')) {
            $host = request()->getHost();
            if ($host === 'localhost' || $host === '127.0.0.1') {
                $urlParam = request()->query('url');
                if ($urlParam) {
                    $domainToUse = strtolower($urlParam);
                    $domainModel = Domain::where('domain', $domainToUse)->first();
                    if ($domainModel) {
                        $tenant = Tenant::find($domainModel->tenant_id);
                    }
                }
            }
        }

        // PRIORITY 2: Check tenancy context
        if (! $tenant) {
            $tenant = tenancy()->tenant;
        }

        // PRIORITY 3: Fall back to current domain detection
        if (! $tenant) {
            $currentDomain = get_current_domain();
            if ($currentDomain) {
                $domainToUse = $currentDomain;
                $domainModel = Domain::where('domain', $currentDomain)->first();
                if ($domainModel) {
                    $tenant = Tenant::find($domainModel->tenant_id);
                }
            }
        }
    }

    if (! $tenant) {
        return asset($path);
    }

    // Use the tracked domain, or fall back to tenant's first domain
    if (! $domainToUse) {
        $domainToUse = $tenant->domains()->first()?->domain ?? 'default';
    }

    $domainFolder = tenant_domain_folder($domainToUse);
    $path = ltrim($path, '/');

    return "/storage/tenants/{$domainFolder}/seo/{$path}";
}
