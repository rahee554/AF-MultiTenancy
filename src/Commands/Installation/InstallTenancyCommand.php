<?php

namespace ArtflowStudio\Tenancy\Commands\Installation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallTenancyCommand extends Command
{
    protected $signature = 'af-tenancy:install 
                          {--force : Overwrite existing files}
                          {--minimal : Install minimal configuration only}';

    protected $description = 'Install ArtflowStudio Multi-Tenancy package';

    public function handle()
    {
        $this->info('🚀 Installing ArtflowStudio Multi-Tenancy Package...');

        // Step 1: Publish configurations
        $this->publishConfigurations();

        // Step 2: Install stancl/tenancy if not already installed
        $this->ensureStanclTenancy();

        // Step 3: Update application configuration
        $this->updateApplicationConfig();

        // Step 4: Create directory structure
        $this->createDirectoryStructure();

        // Step 5: Publish and run migrations
        $this->handleMigrations();

        // Step 6: Update middleware configuration
        $this->updateMiddlewareConfig();

        // Step 7: Create sample tenant (optional)
        if (! $this->option('minimal')) {
            $this->createSampleTenant();
        }

        // Step 8: Final instructions
        $this->displayCompletionInstructions();

        $this->info('✅ Installation completed successfully!');
    }

    protected function publishConfigurations()
    {
        $this->info('📄 Publishing configuration files...');

        $force = $this->option('force');

        // Publish our configurations
        Artisan::call('vendor:publish', [
            '--provider' => 'ArtflowStudio\Tenancy\TenancyServiceProvider',
            '--tag' => 'af-tenancy-config',
            '--force' => $force,
        ]);

        // Publish stancl/tenancy config if not exists
        if (! File::exists(config_path('tenancy.php')) || $force) {
            Artisan::call('vendor:publish', [
                '--provider' => 'Stancl\Tenancy\TenancyServiceProvider',
                '--tag' => 'tenancy-config',
                '--force' => $force,
            ]);
        }

        $this->line('   ✓ Configuration files published');
    }

    protected function ensureStanclTenancy()
    {
        $this->info('🔧 Ensuring stancl/tenancy is properly configured...');

        // Check if stancl/tenancy is installed
        if (! class_exists('\Stancl\Tenancy\TenancyServiceProvider')) {
            $this->error('stancl/tenancy is not installed. Please install it first:');
            $this->line('composer require stancl/tenancy');
            exit(1);
        }

        // Install stancl/tenancy if not already done
        if (! File::exists(config_path('tenancy.php'))) {
            Artisan::call('tenancy:install');
            $this->line('   ✓ stancl/tenancy installed');
        } else {
            $this->line('   ✓ stancl/tenancy already configured');
        }
    }

    protected function updateApplicationConfig()
    {
        $this->info('⚙️ Updating application configuration...');

        // Update app.php to include our service provider
        $this->updateProviders();

        // Auto-add tenant template to database.php
        $this->addTenantTemplateToDatabase();

        // Update cache configuration for multi-tenancy
        $this->updateCacheConfig();

        // Update session configuration
        $this->updateSessionConfig();

        $this->line('   ✓ Application configuration updated');
    }

    protected function addTenantTemplateToDatabase()
    {
        $databaseConfigPath = config_path('database.php');

        if (! File::exists($databaseConfigPath)) {
            $this->warn('   ⚠️  database.php not found, skipping tenant template addition');

            return;
        }

        $databaseConfig = File::get($databaseConfigPath);

        // Enhanced check for tenant_template - check for both quoted and unquoted versions
        if (str_contains($databaseConfig, "'tenant_template'") ||
            str_contains($databaseConfig, '"tenant_template"') ||
            str_contains($databaseConfig, 'tenant_template')) {
            $this->line('   ✓ tenant_template connection already exists in database.php');

            return;
        }

        // Find the mysql connection configuration
        $mysqlPattern = "/('mysql'\s*=>\s*\[[\s\S]*?\],)/";

        if (preg_match($mysqlPattern, $databaseConfig, $matches)) {
            $mysqlConfig = $matches[1];

            // Create tenant template configuration
            $tenantTemplateConfig = "
        'tenant_template' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => '', // This will be set dynamically by tenancy
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
             'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be FALSE for multi-tenancy
                PDO::ATTR_EMULATE_PREPARES => false, // Better performance
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Reduce memory usage
                PDO::ATTR_TIMEOUT => 10, // Shorter timeout for tenants
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode=\'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\', SESSION wait_timeout=120, SESSION interactive_timeout=120',
            ]) : [],
        ],

        ";

            // Insert tenant template before mysql configuration
            $updatedConfig = str_replace($mysqlConfig, $tenantTemplateConfig.$mysqlConfig, $databaseConfig);

            // Write the updated configuration back
            File::put($databaseConfigPath, $updatedConfig);

            $this->line('   ✅ Added tenant_template connection to database.php');
        } else {
            $this->warn('   ⚠️  Could not find mysql connection in database.php - please add tenant_template manually');
            $this->line('   📖 See installation docs for tenant_template configuration');
        }
    }

    protected function updateProviders()
    {
        $appConfigPath = config_path('app.php');
        $appConfig = File::get($appConfigPath);

        $providerClass = 'ArtflowStudio\\Tenancy\\TenancyServiceProvider::class';

        if (! str_contains($appConfig, $providerClass)) {
            $this->warn('⚠️  Please manually add the service provider to config/app.php:');
            $this->line("   {$providerClass}");
        } else {
            $this->line('   ✓ Service provider already registered');
        }
    }

    protected function updateCacheConfig()
    {
        $cacheConfigPath = config_path('cache.php');

        if (File::exists($cacheConfigPath)) {
            $this->line('   ℹ️  Consider configuring Redis with tenant prefixes in cache.php');
        }
    }

    protected function updateSessionConfig()
    {
        $sessionConfigPath = config_path('session.php');

        if (File::exists($sessionConfigPath)) {
            $this->line('   ℹ️  Session configuration detected - tenant scoping will be handled by middleware');
        }
    }

    protected function createDirectoryStructure()
    {
        $this->info('📁 Creating directory structure...');

        $directories = [
            'app/Models/Tenant',
            'app/Http/Middleware/Tenant',
            'database/migrations/tenant',
            'resources/views/tenant',
            'resources/views/tenants/default', // Default homepage folder
            'storage/app/tenants',
            'storage/app/public/tenants', // Main tenant assets directory
        ];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (! File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                $this->line("   ✓ Created {$dir}");
            }
        }

        // Create default home.blade.php
        $this->createDefaultHomepage();

        // Create README files for tenant directories
        $this->createTenantDirectoryReadmes();
    }

    protected function createDefaultHomepage()
    {
        $defaultHomePath = resource_path('views/tenants/default/home.blade.php');

        if (! File::exists($defaultHomePath)) {
            $content = <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Default Tenant</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            text-align: center;
            color: white;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.25rem;
            opacity: 0.9;
        }
        .info {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 Default Tenant Homepage</h1>
        <p>This is the default homepage template for all tenants.</p>
        <div class="info">
            <p>Create custom homepage folders in <strong>resources/views/tenants/</strong></p>
            <p>Use exact domain naming: <strong>tenant1.local</strong></p>
        </div>
    </div>
</body>
</html>
BLADE;

            File::put($defaultHomePath, $content);
            $this->line('   ✓ Created default home.blade.php');
        }
    }

    protected function createTenantDirectoryReadmes()
    {
        // Create README in storage/app/public/tenants
        $tenantsReadmePath = storage_path('app/public/tenants/README.md');

        if (! File::exists($tenantsReadmePath)) {
            $content = <<<'MARKDOWN'
# Tenant Assets Directory

This directory contains all tenant-specific assets organized by exact domain name.

## Structure

```
tenants/
├── tenant1.local/
│   ├── assets/      # General assets (images, fonts, etc.)
│   ├── pwa/         # PWA files (manifest, icons, service worker)
│   ├── seo/         # SEO files (robots.txt, sitemap.xml)
│   ├── documents/   # Documents and downloads
│   └── media/       # Media files (videos, audio)
├── tenant2.local/
│   └── ...
└── subdomain.example.com/
    └── ...
```

## Important Notes

- **Exact Domain Names**: Folder names use the exact domain (e.g., `tenant1.local`, not `tenant1_local`)
- **Automatic Creation**: Folders are created automatically when SEO is enabled or assets are uploaded
- **Public Access**: These folders are accessible via `/storage/tenants/{domain}/{subfolder}/`

## Helper Functions

**Standard Tenant Functions:**
- `tenant_asset('images/logo.png')` - ⚠️ Routes through /tenancy/assets/ (stancl/tenancy default)
- `tenant_pwa_asset('manifest.json')` - Generate URL for PWA assets
- `tenant_seo_asset('robots.txt')` - Generate URL for SEO assets
- `tenant_path('assets')` - Get absolute storage path

**Artflow Custom Functions (Recommended - Direct /storage/ URLs):**
- `af_tenant_asset('images/logo.png')` - Generate URL for tenant assets → /storage/tenants/{domain}/assets/
- `af_tenant_pwa_asset('manifest.json')` - Generate URL for PWA assets → /storage/tenants/{domain}/pwa/
- `af_tenant_seo_asset('robots.txt')` - Generate URL for SEO assets → /storage/tenants/{domain}/seo/

**Why use af_tenant_asset()?**
The artflow custom functions (with `af_` prefix) generate direct storage URLs without routing through the `/tenancy/assets/` route,
which means:
- ✅ Faster asset loading (direct symlink access)
- ✅ Better for static files, images, CSS, JS
- ✅ Works with Laravel's storage symlink
- ✅ No route processing overhead

## Commands

- `php artisan tenant:seo:enable --tenant=uuid` - Enable SEO (creates folder structure)
- `php artisan tenant:seo:status --all` - Check tenant SEO status
- `php artisan tenant:assets:upload` - Upload tenant-specific assets

For more information, see the package documentation.
MARKDOWN;

            File::put($tenantsReadmePath, $content);
            $this->line('   ✓ Created tenant directory README');
        }
    }

    protected function handleMigrations()
    {
        $this->info('🗃️ Handling migrations...');

        if ($this->confirm('Run migrations now?', true)) {
            // Run central migrations first
            Artisan::call('migrate', ['--force' => true]);
            $this->line('   ✓ Central migrations completed');

            // Run tenant migrations
            Artisan::call('tenants:migrate', ['--force' => true]);
            $this->line('   ✓ Tenant migrations completed');
        } else {
            $this->warn('   ⚠️  Remember to run migrations:');
            $this->line('      php artisan migrate');
            $this->line('      php artisan tenants:migrate');
        }
    }

    protected function updateMiddlewareConfig()
    {
        $this->info('🛡️ Updating middleware configuration...');

        $httpKernelPath = app_path('Http/Kernel.php');

        if (File::exists($httpKernelPath)) {
            $this->line('   ✓ Middleware groups are automatically registered by service provider');
            $this->line('   ℹ️  Available middleware groups:');
            $this->line('      - central.web (for central domain routes)');
            $this->line('      - tenant.web (for tenant domain routes)');
            $this->line('      - central.tenant.web (smart domain resolution)');
        }
    }

    protected function createSampleTenant()
    {
        if ($this->confirm('Create a sample tenant for testing?', false)) {
            $domain = $this->ask('Enter tenant domain (e.g., tenant1.yourapp.com)', 'tenant1.localhost');

            try {
                Artisan::call('tenant:create', [
                    'domain' => $domain,
                    '--name' => 'Sample Tenant',
                ]);
                $this->info("   ✅ Sample tenant created: {$domain}");
            } catch (\Exception $e) {
                $this->error('   ❌ Failed to create sample tenant: '.$e->getMessage());
            }
        }
    }

    protected function displayCompletionInstructions()
    {
        $this->info('📋 Next Steps:');
        $this->line('');
        $this->line('1. Update your route files to use appropriate middleware groups:');
        $this->line('   - Use "central.web" for central domain routes');
        $this->line('   - Use "tenant.web" for tenant-specific routes');
        $this->line('   - Use "central.tenant.web" for shared routes');
        $this->line('');
        $this->line('2. Configure your domains in config/artflow-tenancy.php');
        $this->line('');
        $this->line('3. Test the installation:');
        $this->line('   php artisan tenancy:test');
        $this->line('');
        $this->line('4. View comprehensive test:');
        $this->line('   php artisan tenancy:test:comprehensive');
        $this->line('');
        $this->line('📚 Documentation: https://github.com/your-repo/af-multi-tenancy');
    }
}
