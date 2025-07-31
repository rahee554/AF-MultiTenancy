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
        // NOTE: repository uses lowercase `public` folder. Use lowercase here to match.
        $vendorPath = base_path('vendor/artflow-studio/tenancy/public');
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

        // Define asset directories to link
        $assetDirectories = ['css', 'js', 'media'];
        $linkedDirectories = [];

        // Check if any assets already exist
        $existingAssets = [];
        foreach ($assetDirectories as $dir) {
            $targetPath = $publicPath . '/' . $dir;
            if (File::exists($targetPath)) {
                $existingAssets[] = $dir;
            }
        }

        if (!empty($existingAssets) && !$this->option('force')) {
            $this->error('Assets already exist (' . implode(', ', $existingAssets) . ')! Use --force to overwrite.');
            return 1;
        }

        // Remove existing assets if force option is used
        if (!empty($existingAssets) && $this->option('force')) {
            foreach ($existingAssets as $dir) {
                $targetPath = $publicPath . '/' . $dir;
                if (File::exists($targetPath)) {
                    if (is_link($targetPath)) {
                        unlink($targetPath);
                    } else {
                        File::deleteDirectory($targetPath);
                    }
                }
            }
        }

        try {
            // Link each asset directory that exists in the vendor package
            foreach ($assetDirectories as $dir) {
                $sourcePath = $vendorPath . '/' . $dir;
                $targetPath = $publicPath . '/' . $dir;

                if (File::exists($sourcePath)) {
                    // For Windows, use junction instead of symlink for directories
                    if (PHP_OS_FAMILY === 'Windows') {
                        $this->createWindowsLink($sourcePath, $targetPath);
                    } else {
                        File::link($sourcePath, $targetPath);
                    }
                    $linkedDirectories[] = $dir . ': ' . $targetPath;
                }
            }

            if (!empty($linkedDirectories)) {
                $this->info('Successfully linked Tenancy assets:');
                foreach ($linkedDirectories as $link) {
                    $this->line('  ' . $link);
                }
            } else {
                $this->warn('No asset directories found to link.');
            }
            
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
