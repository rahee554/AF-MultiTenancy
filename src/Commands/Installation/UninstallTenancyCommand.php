<?php

namespace ArtflowStudio\Tenancy\Commands\Installation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UninstallTenancyCommand extends Command
{
    protected $signature = 'af-tenancy:uninstall 
                          {--force : Skip confirmation prompts}
                          {--keep-migrations : Keep migration files}
                          {--keep-config : Keep configuration files}';

    protected $description = 'Uninstall ArtflowStudio Multi-Tenancy package and revert changes';

    public function handle()
    {
        if (! $this->option('force')) {
            $this->warn('⚠️  This will uninstall the Multi-Tenancy package and revert installation changes.');
            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Uninstallation cancelled.');
                return;
            }
        }

        $this->info('🗑️  Uninstalling ArtflowStudio Multi-Tenancy Package...');

        // Step 1: Remove service provider from bootstrap/providers.php
        $this->removeServiceProvider();

        // Step 2: Remove TenantDatabaseSeeder if it's empty
        $this->removeTenantDatabaseSeeder();

        // Step 3: Remove created directories (optional)
        $this->removeDirectories();

        // Step 4: Remove configuration files (optional)
        if (! $this->option('keep-config')) {
            $this->removeConfigurationFiles();
        }

        // Step 5: Remove installation state
        $this->removeInstallationState();

        $this->info('✅ Uninstallation completed successfully!');
        $this->displayPostUninstallInstructions();
    }

    protected function removeServiceProvider()
    {
        $this->info('🔌 Removing service provider...');

        $bootstrapProvidersPath = bootstrap_path('providers.php');

        if (! File::exists($bootstrapProvidersPath)) {
            $this->warn('   ⚠️  bootstrap/providers.php not found');
            return;
        }

        $providersContent = File::get($bootstrapProvidersPath);
        $providerClass = 'ArtflowStudio\Tenancy\TenancyServiceProvider::class';

        if (! str_contains($providersContent, $providerClass)) {
            $this->line('   ℹ️  Service provider not registered');
            return;
        }

        // Remove the provider line
        $updatedContent = str_replace(
            "    {$providerClass},\n",
            "",
            $providersContent
        );

        // Handle case where it might be on multiple lines or formatted differently
        if ($updatedContent === $providersContent) {
            $updatedContent = str_replace(
                "{$providerClass},",
                "",
                $providersContent
            );
        }

        File::put($bootstrapProvidersPath, $updatedContent);
        $this->line('   ✓ Service provider removed from bootstrap/providers.php');
    }

    protected function removeTenantDatabaseSeeder()
    {
        $this->info('📝 Cleaning up database seeders...');

        $tenantSeederPath = database_path('seeders/TenantDatabaseSeeder.php');

        if (! File::exists($tenantSeederPath)) {
            $this->line('   ℹ️  TenantDatabaseSeeder.php not found');
            return;
        }

        $content = File::get($tenantSeederPath);

        // Only remove if it's empty or just contains the template
        if (str_contains($content, '// Add tenant-specific seeders here')) {
            File::delete($tenantSeederPath);
            $this->line('   ✓ Removed TenantDatabaseSeeder.php');
        } else {
            $this->warn('   ⚠️  TenantDatabaseSeeder.php contains custom code - skipping deletion');
            $this->line('   ℹ️  Please remove manually if needed: '.$tenantSeederPath);
        }
    }

    protected function removeDirectories()
    {
        $this->info('📁 Cleaning up directories...');

        $directories = [
            'database/migrations/tenant',
            'storage/app/tenants',
            'storage/app/public/tenants',
        ];

        if (! $this->confirm('Remove tenant-specific directories?', true)) {
            $this->line('   ℹ️  Directories kept');
            return;
        }

        foreach ($directories as $dir) {
            $path = base_path($dir);

            if (! File::exists($path)) {
                continue;
            }

            // Check if directory is empty
            $files = File::allFiles($path);

            if (empty($files)) {
                File::deleteDirectory($path);
                $this->line("   ✓ Removed empty directory: {$dir}");
            } else {
                $this->warn("   ⚠️  Directory not empty, skipping: {$dir}");
                $this->line("   ℹ️  Contains ".count($files).' file(s)');
            }
        }
    }

    protected function removeConfigurationFiles()
    {
        $this->info('⚙️  Cleaning up configuration files...');

        $configFiles = [
            config_path('artflow-tenancy.php'),
            config_path('tenancy.php'),
        ];

        foreach ($configFiles as $configFile) {
            if (! File::exists($configFile)) {
                continue;
            }

            if ($this->confirm("Remove {$configFile}?", false)) {
                File::delete($configFile);
                $this->line("   ✓ Removed {$configFile}");
            } else {
                $this->line("   ℹ️  Kept {$configFile}");
            }
        }
    }

    protected function removeInstallationState()
    {
        $installationFile = storage_path('app/installation-state.json');

        if (File::exists($installationFile)) {
            File::delete($installationFile);
            $this->line('   ✓ Installation state removed');
        }
    }

    protected function displayPostUninstallInstructions()
    {
        $this->info('📋 What to do next:');
        $this->line('');
        $this->line('1. To reinstall with fixes, run:');
        $this->line('   php artisan af-tenancy:install');
        $this->line('');
        $this->line('2. To restore tenants from backup:');
        $this->line('   - Restore database backups');
        $this->line('   - Run: php artisan tenants:migrate');
        $this->line('');
        $this->line('3. Manual cleanup (if needed):');
        $this->line('   - Check bootstrap/providers.php');
        $this->line('   - Check config/database.php for tenant_template');
        $this->line('   - Review storage/app/tenants/ for leftover files');
        $this->line('');
        $this->line('⚠️  Note: Tenant databases remain intact');
        $this->line('   Delete them manually if desired via database client');
    }
}
