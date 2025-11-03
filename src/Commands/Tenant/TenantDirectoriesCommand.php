<?php

namespace ArtflowStudio\Tenancy\Commands\Tenant;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TenantDirectoriesCommand extends Command
{
    protected $signature = 'tenant:directories 
                          {--check : Only check directory status}
                          {--create : Create missing directories}
                          {--domain= : Specific domain to manage}
                          {--all : Process all tenants}
                          {--force : Force create even if exists}';

    protected $description = 'Check and create tenant directory structures (public & private)';

    /**
     * Directory structure configuration
     */
    protected const PUBLIC_SUBDIRS = [
        'assets',      // General assets (images, CSS, JS, fonts)
        'pwa',         // PWA files (manifest.json, icons, service-worker.js)
        'seo',         // SEO files (robots.txt, sitemap.xml)
        'documents',   // Downloadable documents
        'media',       // Media files (videos, audio)
    ];

    protected const PRIVATE_SUBDIRS = [
        'backups',     // Database backups
        'logs',        // Tenant-specific logs
        'cache',       // Tenant cache
        'temp',        // Temporary files
        'documents',   // Private documents
        'uploads',     // Private uploads
        'config',      // Tenant-specific config
    ];

    protected const PWA_SUBDIRS = [
        'icons',       // PWA icons folder
    ];

    protected const SEO_SUBDIRS = [
        // SEO folder only contains files, no subdirs
    ];

    public function handle(): int
    {
        $this->info('🏢 Tenant Directories Manager');
        $this->line('─────────────────────────────────────────');

        // Determine which tenants to process
        $tenants = $this->getTenantsList();

        if (empty($tenants)) {
            $this->warn('⚠ No tenants found!');

            return Command::FAILURE;
        }

        // Process each tenant
        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }

        $this->info('✅ Operation completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Get list of tenants to process
     */
    protected function getTenantsList(): array
    {
        if ($this->option('domain')) {
            // Find tenant by domain from domains table
            $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $this->option('domain'))->first();
            if (! $domain || ! $domain->tenant) {
                $this->error("❌ Tenant with domain '{$this->option('domain')}' not found!");

                return [];
            }

            return [$domain->tenant];
        }

        if ($this->option('all')) {
            return Tenant::with('domains')->get()->toArray();
        }

        // Interactive mode - list all tenants
        $tenants = Tenant::with('domains')->get();

        if ($tenants->isEmpty()) {
            return [];
        }

        if ($tenants->count() === 1) {
            return $tenants->toArray();
        }

        $this->line("\n📋 Available Tenants:");

        foreach ($tenants as $index => $tenant) {
            $primaryDomain = $tenant->domains->first()->domain ?? 'No domain';
            $this->line("  [{$index}] {$primaryDomain} (Tenant: {$tenant->name})");
        }

        $selected = $this->ask('Select tenant(s) to process (comma-separated indices or "all")');

        if ($selected === 'all') {
            return $tenants->toArray();
        }

        $indices = array_map('trim', explode(',', $selected));
        $selectedTenants = [];

        foreach ($indices as $index) {
            if (isset($tenants[$index])) {
                $selectedTenants[] = $tenants[$index];
            }
        }

        return $selectedTenants;
    }

    /**
     * Process individual tenant
     */
    protected function processTenant($tenant): void
    {
        // Handle both array and object format
        if (is_array($tenant)) {
            $tenantObj = Tenant::with('domains')->find($tenant['id']);
            $domain = $tenantObj->domains->first()->domain ?? 'unknown';
        } else {
            $domain = $tenant->domains->first()->domain ?? 'unknown';
        }

        $this->line("\n📁 Processing tenant: <fg=cyan>{$domain}</>");

        // Check mode
        if ($this->option('check') || (! $this->option('create') && ! $this->option('force'))) {
            $this->checkTenantDirectories($domain);

            return;
        }

        // Create mode
        if ($this->option('create') || $this->option('force')) {
            $this->createTenantDirectories($domain);
        }
    }

    /**
     * Check tenant directory status
     */
    protected function checkTenantDirectories(string $domain): void
    {
        $publicPath = storage_path("app/public/tenants/{$domain}");
        $privatePath = storage_path("app/private/tenants/{$domain}");

        $this->line("  📂 Public Directory: <fg=cyan>{$publicPath}</>");
        $this->checkDirectoryStructure($publicPath, 'Public', static::PUBLIC_SUBDIRS);

        $this->line("\n  🔐 Private Directory: <fg=cyan>{$privatePath}</>");
        $this->checkDirectoryStructure($privatePath, 'Private', static::PRIVATE_SUBDIRS);

        // Check PWA subdirectories
        $pwaPath = "{$publicPath}/pwa";
        if (File::isDirectory($pwaPath)) {
            $this->line("\n  📱 PWA Subdirectories:");
            $this->checkDirectoryStructure($pwaPath, 'PWA', static::PWA_SUBDIRS);
        }

        // Check SEO subdirectories
        $seoPath = "{$publicPath}/seo";
        if (File::isDirectory($seoPath)) {
            $this->line("\n  🔍 SEO Files:");
            $this->checkSeoFiles($seoPath);
        }
    }

    /**
     * Check individual directory structure
     */
    protected function checkDirectoryStructure(string $basePath, string $label, array $subdirs): void
    {
        if (! File::isDirectory($basePath)) {
            $this->line("    ❌ <fg=red>{$label} directory does not exist</>");

            return;
        }

        $this->line("    ✅ <fg=green>{$label} directory exists</>");

        foreach ($subdirs as $subdir) {
            $fullPath = "{$basePath}/{$subdir}";
            if (File::isDirectory($fullPath)) {
                $fileCount = count(File::files($fullPath));
                $this->line("      ✅ {$subdir}/ <fg=gray>({$fileCount} files)</>");
            } else {
                $this->line("      ⚠️  {$subdir}/ <fg=yellow>(missing)</>");
            }
        }
    }

    /**
     * Check SEO files
     */
    protected function checkSeoFiles(string $seoPath): void
    {
        $files = ['robots.txt', 'sitemap.xml'];

        foreach ($files as $file) {
            $fullPath = "{$seoPath}/{$file}";
            if (File::exists($fullPath)) {
                $size = File::size($fullPath);
                $this->line("      ✅ {$file} <fg=gray>({$size} bytes)</>");
            } else {
                $this->line("      ⚠️  {$file} <fg=yellow>(missing)</>");
            }
        }
    }

    /**
     * Create all tenant directories
     */
    protected function createTenantDirectories(string $domain): void
    {
        $this->line("  Creating directory structure for <fg=cyan>{$domain}</>");

        // Create public directories
        $this->createPublicDirectories($domain);

        // Create private directories
        $this->createPrivateDirectories($domain);

        $this->line('  ✅ All directories created successfully!');
    }

    /**
     * Create public directory structure
     */
    protected function createPublicDirectories(string $domain): void
    {
        $basePath = storage_path("app/public/tenants/{$domain}");

        if (! File::isDirectory($basePath)) {
            File::makeDirectory($basePath, 0755, true);
            $this->line('    ✓ Created public base directory');
        }

        // Create main subdirectories
        foreach (static::PUBLIC_SUBDIRS as $subdir) {
            $path = "{$basePath}/{$subdir}";

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
                $this->line("    ✓ Created <fg=cyan>{$subdir}</>");
            }

            // Create nested directories for PWA
            if ($subdir === 'pwa') {
                foreach (static::PWA_SUBDIRS as $nestedDir) {
                    $nestedPath = "{$path}/{$nestedDir}";
                    if (! File::isDirectory($nestedPath)) {
                        File::makeDirectory($nestedPath, 0755, true);
                        $this->line("      ✓ Created <fg=cyan>pwa/{$nestedDir}</>");
                    }
                }

                // Create .gitkeep for PWA icons
                File::put("{$path}/icons/.gitkeep", '');
            }

            // Create .gitkeep files
            $gitkeepPath = "{$path}/.gitkeep";
            if (! File::exists($gitkeepPath)) {
                File::put($gitkeepPath, '');
            }
        }
    }

    /**
     * Create private directory structure
     */
    protected function createPrivateDirectories(string $domain): void
    {
        $basePath = storage_path("app/private/tenants/{$domain}");

        if (! File::isDirectory($basePath)) {
            File::makeDirectory($basePath, 0755, true);
            $this->line('    ✓ Created private base directory');
        }

        // Create main subdirectories
        foreach (static::PRIVATE_SUBDIRS as $subdir) {
            $path = "{$basePath}/{$subdir}";

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
                $this->line("    ✓ Created <fg=cyan>private/{$subdir}</>");
            }

            // Create .gitkeep files
            $gitkeepPath = "{$path}/.gitkeep";
            if (! File::exists($gitkeepPath)) {
                File::put($gitkeepPath, '');
            }
        }
    }

    /**
     * Display directory structure summary
     */
    protected function displayStructureSummary(string $domain): void
    {
        $this->line("\n\n📊 Directory Structure Summary for <fg=cyan>{$domain}</>");
        $this->line('═════════════════════════════════════════════════════');

        $this->line("\n🌐 <fg=yellow>PUBLIC DIRECTORIES</> (storage/app/public/tenants/{$domain}/):");
        $this->line('  ├── assets/          ← General tenant assets (images, fonts, CSS)');
        $this->line('  ├── pwa/');
        $this->line('  │   ├── icons/       ← PWA app icons');
        $this->line('  │   ├── manifest.json');
        $this->line('  │   └── service-worker.js');
        $this->line('  ├── seo/');
        $this->line('  │   ├── robots.txt');
        $this->line('  │   └── sitemap.xml');
        $this->line('  ├── documents/       ← Public documents');
        $this->line('  └── media/           ← Videos, audio files');

        $this->line("\n🔐 <fg=yellow>PRIVATE DIRECTORIES</> (storage/app/private/tenants/{$domain}/):");
        $this->line('  ├── backups/         ← Database backups (⚠️  NOT accessible via web)');
        $this->line('  ├── logs/            ← Tenant-specific logs');
        $this->line('  ├── cache/           ← Tenant cache');
        $this->line('  ├── temp/            ← Temporary files');
        $this->line('  ├── documents/       ← Private documents');
        $this->line('  ├── uploads/         ← Private uploads');
        $this->line('  └── config/          ← Tenant-specific configuration');

        $this->line("\n💡 <fg=green>Helper Functions</>:");
        $this->line("  • tenant_asset('file.jpg')        ← Access: storage/public/tenants/{$domain}/assets/");
        $this->line("  • tenant_pwa_asset('manifest')    ← Access: storage/public/tenants/{$domain}/pwa/");
        $this->line("  • tenant_seo_asset('robots.txt')  ← Access: storage/public/tenants/{$domain}/seo/");
        $this->line("  • tenant_dir('assets')            ← Path: storage/public/tenants/{$domain}/assets/");
        $this->line("  • tenant_private_dir('backups')   ← Path: storage/private/tenants/{$domain}/backups/ (secure)");

        $this->line("\n");
    }
}
