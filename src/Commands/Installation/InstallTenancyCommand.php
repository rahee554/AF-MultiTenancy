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

    private array $createdPaths = [];

    public function handle()
    {
        $this->info('🚀 Installing ArtflowStudio Multi-Tenancy Package...');

        // Step 1: Publish configurations
        $this->publishConfigurations();

        // Step 2: Install stancl/tenancy if not already installed
        $this->ensureStanclTenancy();

        // Step 3: Update application configuration
        $this->updateApplicationConfig();

        // Step 4: Create minimal required directory structure
        $this->createMinimalDirectoryStructure();

        // Step 5: Create TenantDatabaseSeeder if needed
        $this->createTenantDatabaseSeeder();

        // Step 6: Publish and run migrations
        $this->handleMigrations();

        // Step 7: Update middleware configuration
        $this->updateMiddlewareConfig();

        // Step 8: Save installation state
        $this->saveInstallationState();

        // Step 9: Final instructions
        $this->displayCompletionInstructions();

        $this->info('✅ Installation completed successfully!');
    }

    protected function publishConfigurations()
    {
        $this->info('📄 Publishing configuration files...');

        $force = $this->option('force');

        // Publish OUR configuration files (including our modified tenancy.php)
        Artisan::call('vendor:publish', [
            '--provider' => 'ArtflowStudio\Tenancy\TenancyServiceProvider',
            '--tag' => 'af-tenancy-config',
            '--force' => $force,
        ]);

        // Publish our tenancy.php (NOT stancl's) as the primary config
        Artisan::call('vendor:publish', [
            '--provider' => 'ArtflowStudio\Tenancy\TenancyServiceProvider',
            '--tag' => 'af-tenancy-tenancy-config',
            '--force' => $force,
        ]);

        $this->line('   ✓ Configuration files published');
        $this->line('   ℹ️  Using ArtflowStudio tenancy configuration (not stancl/tenancy defaults)');
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
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode=\'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION\', SESSION wait_timeout=120, SESSION interactive_timeout=120',
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
        $bootstrapProvidersPath = bootstrap_path('providers.php');

        if (! File::exists($bootstrapProvidersPath)) {
            $this->warn('⚠️  bootstrap/providers.php not found');
            return;
        }

        $providersContent = File::get($bootstrapProvidersPath);
        $providerClass = 'ArtflowStudio\Tenancy\TenancyServiceProvider::class';

        if (str_contains($providersContent, $providerClass)) {
            $this->line('   ✓ Service provider already registered in bootstrap/providers.php');
            return;
        }

        // Add provider to the array
        $updatedContent = str_replace(
            "return [",
            "return [\n    {$providerClass},",
            $providersContent
        );

        File::put($bootstrapProvidersPath, $updatedContent);
        $this->createdPaths[] = $bootstrapProvidersPath;
        $this->line('   ✓ Service provider registered in bootstrap/providers.php');
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

    protected function createMinimalDirectoryStructure()
    {
        $this->info('📁 Creating minimal required directory structure...');

        // Only create essential tenant-specific directories
        $directories = [
            'database/migrations/tenant',
            'storage/app/tenants',
            'storage/app/public/tenants',
        ];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (! File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                $this->createdPaths[] = $path;
                $this->line("   ✓ Created {$dir}");
            } else {
                $this->line("   ℹ️  {$dir} already exists");
            }
        }

        // Create README files for tenant directories
        $this->createTenantDirectoryReadmes();
    }

    protected function createTenantDatabaseSeeder()
    {
        $this->info('📝 Setting up database seeders...');

        $tenantSeederPath = database_path('seeders/TenantDatabaseSeeder.php');

        if (File::exists($tenantSeederPath) && !$this->option('force')) {
            $this->line('   ✓ TenantDatabaseSeeder.php already exists');
            return;
        }

        $seederContent = <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds for tenants.
     */
    public function run(): void
    {
        // Add tenant-specific seeders here
        // $this->call(TenantUsersSeeder::class);
    }
}
PHP;

        File::put($tenantSeederPath, $seederContent);
        $this->createdPaths[] = $tenantSeederPath;
        $this->line('   ✓ Created TenantDatabaseSeeder.php');
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

    protected function saveInstallationState()
    {
        $installationFile = storage_path('app/installation-state.json');

        $state = [
            'af-tenancy-installed' => true,
            'installed-at' => now()->toIso8601String(),
            'created-paths' => $this->createdPaths,
            'version' => '0.8.0',
        ];

        File::put($installationFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line('   ✓ Installation state saved');
    }

    protected function displayCompletionInstructions()
    {
        $this->info('📋 Next Steps:');
        $this->line('');
        $this->line('1. Run migrations (if not already done):');
        $this->line('   php artisan migrate');
        $this->line('   php artisan tenants:migrate');
        $this->line('');
        $this->line('2. Create your first tenant:');
        $this->line('   php artisan tenant:create --domain=tenant1.localhost');
        $this->line('');
        $this->line('3. Configure your routes in routes/web.php:');
        $this->line('   - Use "central.web" for central domain routes');
        $this->line('   - Use "tenant.web" for tenant-specific routes');
        $this->line('   - Use "central.tenant.web" for shared routes');
        $this->line('');
        $this->line('4. Configure domains in config/artflow-tenancy.php');
        $this->line('');
        $this->line('5. To uninstall and fix issues, run:');
        $this->line('   php artisan af-tenancy:uninstall');
        $this->line('   php artisan af-tenancy:install');
        $this->line('');
        $this->line('📚 Documentation: https://github.com/your-repo/af-multi-tenancy');
    }
}
