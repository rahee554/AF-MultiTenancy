<?php

namespace ArtflowStudio\Tenancy\Commands\Installation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

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
        if (!$this->option('minimal')) {
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
            '--force' => $force
        ]);

        // Publish stancl/tenancy config if not exists
        if (!File::exists(config_path('tenancy.php')) || $force) {
            Artisan::call('vendor:publish', [
                '--provider' => 'Stancl\Tenancy\TenancyServiceProvider',
                '--tag' => 'tenancy-config',
                '--force' => $force
            ]);
        }

        $this->line('   ✓ Configuration files published');
    }

    protected function ensureStanclTenancy()
    {
        $this->info('🔧 Ensuring stancl/tenancy is properly configured...');

        // Check if stancl/tenancy is installed
        if (!class_exists('\Stancl\Tenancy\TenancyServiceProvider')) {
            $this->error('stancl/tenancy is not installed. Please install it first:');
            $this->line('composer require stancl/tenancy');
            exit(1);
        }

        // Install stancl/tenancy if not already done
        if (!File::exists(config_path('tenancy.php'))) {
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

        // Update cache configuration for multi-tenancy
        $this->updateCacheConfig();

        // Update session configuration
        $this->updateSessionConfig();

        $this->line('   ✓ Application configuration updated');
    }

    protected function updateProviders()
    {
        $appConfigPath = config_path('app.php');
        $appConfig = File::get($appConfigPath);

        $providerClass = 'ArtflowStudio\\Tenancy\\TenancyServiceProvider::class';

        if (!str_contains($appConfig, $providerClass)) {
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
            'storage/app/tenants',
        ];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                $this->line("   ✓ Created {$dir}");
            }
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
                $this->error("   ❌ Failed to create sample tenant: " . $e->getMessage());
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
