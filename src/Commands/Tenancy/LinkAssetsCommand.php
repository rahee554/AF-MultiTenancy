<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LinkAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'af-tenancy:link-assets {--force : Force the operation to run when assets already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create symbolic links for Artflow Tenancy package assets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vendorPath = base_path('vendor/artflow-studio/tenancy/Public');
        $publicPath = public_path('vendor/artflow-studio/tenancy');

        if (! File::exists($vendorPath)) {
            $this->error('Tenancy package assets not found!');
            return 1;
        }

        // Create directory if it doesn't exist
        if (! File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0755, true);
            $this->info('Created directory: ' . $publicPath);
        }

        $cssSource = $vendorPath . '/css';
        $cssTarget = $publicPath . '/css';
        $jsSource = $vendorPath . '/js';
        $jsTarget = $publicPath . '/js';

        // Check if already exists
        if (File::exists($cssTarget) || File::exists($jsTarget)) {
            if (! $this->option('force')) {
                $this->error('Assets already exist! Use --force to overwrite.');
                return 1;
            }
            
            // Remove existing
            if (File::exists($cssTarget)) {
                File::deleteDirectory($cssTarget);
            }
            if (File::exists($jsTarget)) {
                File::deleteDirectory($jsTarget);
            }
        }

        try {
            // For Windows, use junction instead of symlink for directories
            if (PHP_OS_FAMILY === 'Windows') {
                $this->createWindowsLink($cssSource, $cssTarget);
                $this->createWindowsLink($jsSource, $jsTarget);
            } else {
                File::link($cssSource, $cssTarget);
                File::link($jsSource, $jsTarget);
            }

            $this->info('Successfully linked Tenancy assets:');
            $this->line('  CSS: ' . $cssTarget);
            $this->line('  JS: ' . $jsTarget);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create symbolic links: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create Windows directory junction
     */
    private function createWindowsLink(string $source, string $target): void
    {
        $source = str_replace('/', '\\', $source);
        $target = str_replace('/', '\\', $target);
        
        exec("mklink /J \"$target\" \"$source\"", $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \Exception('Failed to create Windows junction');
        }
    }
}
