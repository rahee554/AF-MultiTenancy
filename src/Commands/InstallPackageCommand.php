<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallPackageCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:install {--force : Force overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install Artflow Studio Tenancy package with proper stancl/tenancy integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->showWelcome();
        
        $force = $this->option('force');
        
        // Step 1: Publish stancl/tenancy configuration
        $this->info('📦 Publishing stancl/tenancy configuration...');
        if ($this->publishStanclConfig($force)) {
            $this->line('   ✅ stancl/tenancy config published successfully');
        } else {
            $this->error('   ❌ Failed to publish stancl/tenancy config');
            return 1;
        }

        // Step 2: Publish Artflow Tenancy configuration
        $this->info('📦 Publishing Artflow Tenancy configuration...');
        if ($this->publishArtflowConfig($force)) {
            $this->line('   ✅ Artflow Tenancy config published successfully');
        } else {
            $this->error('   ❌ Failed to publish Artflow Tenancy config');
            return 1;
        }

        // Step 3: Publish and run migrations
        $this->info('📦 Publishing and running migrations...');
        if ($this->handleMigrations($force)) {
            $this->line('   ✅ Migrations completed successfully');
        } else {
            $this->error('   ❌ Failed to run migrations');
            return 1;
        }

        // Step 4: Publish routes (optional)
        if ($this->confirm('📦 Do you want to publish routes for customization?', false)) {
            $this->publishRoutes($force);
            $this->line('   ✅ Routes published successfully');
        }

        // Step 5: Publish views (optional)
        if ($this->confirm('📦 Do you want to publish views for dashboard customization?', false)) {
            $this->publishViews($force);
            $this->line('   ✅ Views published successfully');
        }

        $this->showSuccess();
        
        return 0;
    }

    /**
     * Show welcome message
     */
    protected function showWelcome(): void
    {
        $this->line('');
        $this->line('🚀 <fg=green;options=bold>Artflow Studio Tenancy Package</fg=green;options=bold>');
        $this->line('📦 <fg=yellow>Version: 0.4.6</fg=yellow>');
        $this->line('⚡ <fg=cyan>Powered by stancl/tenancy</fg=cyan>');
        $this->line('');
        $this->line('<fg=yellow;options=bold>Installing package with proper stancl/tenancy integration...</fg=yellow;options=bold>');
        $this->line('');
    }

    /**
     * Show success message
     */
    protected function showSuccess(): void
    {
        $this->line('');
        $this->line('<fg=green;options=bold>🎉 Installation completed successfully!</fg=green;options=bold>');
        $this->line('');
        $this->line('<fg=yellow;options=bold>Next Steps:</fg=yellow;options=bold>');
        $this->line('   1. 🏗️  Create your first tenant:');
        $this->line('      <fg=cyan>php artisan tenancy:create-tenant "Acme Corp" "acme.your-app.com"</fg=cyan>');
        $this->line('');
        $this->line('   2. 🧪 Test performance:');
        $this->line('      <fg=cyan>php artisan tenancy:test-performance</fg=cyan>');
        $this->line('');
        $this->line('   3. 📊 Monitor real-time stats:');
        $this->line('      <fg=cyan>php artisan tenancy:monitor</fg=cyan>');
        $this->line('');
        $this->line('<fg=yellow;options=bold>Available Endpoints:</fg=yellow;options=bold>');
        $this->line('   • Admin Dashboard: <fg=cyan>/admin/dashboard</fg=cyan>');
        $this->line('   • Real-time Monitoring: <fg=cyan>/admin/monitoring/dashboard</fg=cyan>');
        $this->line('   • API Documentation: <fg=cyan>/tenancy/stats</fg=cyan>');
        $this->line('');
        $this->line('<fg=yellow;options=bold>Resources:</fg=yellow;options=bold>');
        $this->line('   📖 Documentation: <fg=cyan>https://tenancy.artflow-studio.com</fg=cyan>');
        $this->line('   💬 Support: <fg=cyan>https://discord.gg/artflow-tenancy</fg=cyan>');
        $this->line('   🐛 Issues: <fg=cyan>https://github.com/artflow-studio/tenancy/issues</fg=cyan>');
        $this->line('');
    }

    /**
     * Publish stancl/tenancy configuration
     */
    protected function publishStanclConfig(bool $force): bool
    {
        try {
            $params = [
                '--tag' => 'tenancy-stancl-config',
            ];

            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Publish Artflow Tenancy configuration
     */
    protected function publishArtflowConfig(bool $force): bool
    {
        try {
            $params = [
                '--tag' => 'tenancy-config',
            ];

            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Handle migrations
     */
    protected function handleMigrations(bool $force): bool
    {
        try {
            // Publish migrations first
            $params = [
                '--tag' => 'tenancy-migrations',
            ];

            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);

            // Run migrations
            if ($this->confirm('🔄 Run migrations now?', true)) {
                Artisan::call('migrate');
                $this->line('   📊 Database migrated successfully');
            } else {
                $this->line('   📝 Migrations published. Run `php artisan migrate` when ready.');
            }

            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Publish routes
     */
    protected function publishRoutes(bool $force): bool
    {
        try {
            $params = [
                '--tag' => 'tenancy-routes',
            ];

            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Publish views
     */
    protected function publishViews(bool $force): bool
    {
        try {
            $params = [
                '--tag' => 'tenancy-views',
            ];

            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()}");
            return false;
        }
    }
}
