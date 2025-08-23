<?php

namespace ArtflowStudio\Tenancy\Commands\Core;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class FindUnusedFilesCommand extends Command
{
    protected $signature = 'tenancy:find-unused {--delete : Delete unused files}';
    protected $description = 'Find and optionally remove unused files in the package';

    private array $checkedFiles = [];
    private array $unusedFiles = [];

    public function handle(): int
    {
        $this->info('🔍 Scanning for unused files...');
        $this->newLine();

        // Check different file types
        $this->checkCommandFiles();
        $this->checkServiceFiles();
        $this->checkMiddlewareFiles();
        $this->checkModelFiles();
        $this->checkViewFiles();
        $this->checkConfigFiles();

        $this->displayResults();

        if ($this->option('delete') && !empty($this->unusedFiles)) {
            $this->deleteUnusedFiles();
        }

        return 0;
    }

    private function checkCommandFiles(): void
    {
        $this->line('📁 Checking Command files...');
        
        $commandsPath = base_path('src/Commands');
        if (!File::exists($commandsPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($commandsPath)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            
            if ($this->isCommandUsed($filePath)) {
                $this->checkedFiles[] = $relativePath . ' ✅';
            } else {
                $this->unusedFiles[] = $relativePath;
                $this->checkedFiles[] = $relativePath . ' ❌ UNUSED';
            }
        }
    }

    private function isCommandUsed(string $filePath): bool
    {
        $content = File::get($filePath);
        
        // Extract class name
        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $matches)) {
            $className = $matches[1];
            
            // Check if registered in service provider
            $serviceProviderPath = base_path('src/TenancyServiceProvider.php');
            if (File::exists($serviceProviderPath)) {
                $serviceContent = File::get($serviceProviderPath);
                
                // Check for class reference
                if (str_contains($serviceContent, $className . '::class')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function checkServiceFiles(): void
    {
        $this->line('📁 Checking Service files...');
        
        $servicesPath = base_path('src/Services');
        if (!File::exists($servicesPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($servicesPath)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            
            if ($this->isServiceUsed($filePath)) {
                $this->checkedFiles[] = $relativePath . ' ✅';
            } else {
                $this->unusedFiles[] = $relativePath;
                $this->checkedFiles[] = $relativePath . ' ❌ UNUSED';
            }
        }
    }

    private function isServiceUsed(string $filePath): bool
    {
        $content = File::get($filePath);
        
        // Extract class name
        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $matches)) {
            $className = $matches[1];
            
            // Search for usage in the entire src directory
            $srcPath = base_path('src');
            $finder = new Finder();
            $finder->files()->in($srcPath)->name('*.php');

            foreach ($finder as $file) {
                $fileContent = File::get($file->getRealPath());
                
                // Skip the file itself
                if ($file->getRealPath() === $filePath) {
                    continue;
                }
                
                // Check for class usage
                if (str_contains($fileContent, $className)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function checkMiddlewareFiles(): void
    {
        $this->line('📁 Checking Middleware files...');
        
        $middlewarePath = base_path('src/Http/Middleware');
        if (!File::exists($middlewarePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($middlewarePath)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            
            if ($this->isMiddlewareUsed($filePath)) {
                $this->checkedFiles[] = $relativePath . ' ✅';
            } else {
                $this->unusedFiles[] = $relativePath;
                $this->checkedFiles[] = $relativePath . ' ❌ UNUSED';
            }
        }
    }

    private function isMiddlewareUsed(string $filePath): bool
    {
        $content = File::get($filePath);
        
        // Extract class name
        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $matches)) {
            $className = $matches[1];
            
            // Check service provider for middleware registration
            $serviceProviderPath = base_path('src/TenancyServiceProvider.php');
            if (File::exists($serviceProviderPath)) {
                $serviceContent = File::get($serviceProviderPath);
                
                if (str_contains($serviceContent, $className)) {
                    return true;
                }
            }
            
            // Check route files
            $routePath = base_path('routes');
            if (File::exists($routePath)) {
                $finder = new Finder();
                $finder->files()->in($routePath)->name('*.php');

                foreach ($finder as $routeFile) {
                    $routeContent = File::get($routeFile->getRealPath());
                    if (str_contains($routeContent, $className)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function checkModelFiles(): void
    {
        $this->line('📁 Checking Model files...');
        
        $modelsPath = base_path('src/Models');
        if (!File::exists($modelsPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($modelsPath)->name('*.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            
            // Models are generally always used
            $this->checkedFiles[] = $relativePath . ' ✅';
        }
    }

    private function checkViewFiles(): void
    {
        $this->line('📁 Checking View files...');
        
        $viewsPath = base_path('resources/views');
        if (!File::exists($viewsPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($viewsPath)->name('*.blade.php');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            
            // For now, mark all views as used (would need more complex analysis)
            $this->checkedFiles[] = $relativePath . ' ✅';
        }
    }

    private function checkConfigFiles(): void
    {
        $this->line('📁 Checking Config files...');
        
        $configPath = base_path('config');
        if (!File::exists($configPath)) {
            return;
        }

        $configFiles = File::files($configPath);
        
        foreach ($configFiles as $file) {
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            
            // Check if config is published in service provider
            $serviceProviderPath = base_path('src/TenancyServiceProvider.php');
            if (File::exists($serviceProviderPath)) {
                $serviceContent = File::get($serviceProviderPath);
                $fileName = $file->getFilename();
                
                if (str_contains($serviceContent, $fileName)) {
                    $this->checkedFiles[] = $relativePath . ' ✅';
                } else {
                    $this->unusedFiles[] = $relativePath;
                    $this->checkedFiles[] = $relativePath . ' ❌ UNUSED';
                }
            }
        }
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📊 Scan Results:');
        $this->newLine();

        foreach ($this->checkedFiles as $file) {
            $this->line("  {$file}");
        }

        $this->newLine();
        
        if (empty($this->unusedFiles)) {
            $this->info('🎉 No unused files found!');
        } else {
            $this->warn('⚠️  Found ' . count($this->unusedFiles) . ' potentially unused files:');
            foreach ($this->unusedFiles as $file) {
                $this->line("  • {$file}");
            }
            
            $this->newLine();
            $this->line('💡 To remove these files, run: php artisan tenancy:find-unused --delete');
        }
    }

    private function deleteUnusedFiles(): void
    {
        if (!$this->confirm('Are you sure you want to delete all unused files? This cannot be undone!')) {
            $this->info('Operation cancelled.');
            return;
        }

        $this->info('🗑️  Deleting unused files...');
        
        foreach ($this->unusedFiles as $file) {
            $fullPath = base_path($file);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
                $this->line("  ✅ Deleted: {$file}");
            }
        }

        $this->newLine();
        $this->info('✅ Cleanup complete!');
    }
}
