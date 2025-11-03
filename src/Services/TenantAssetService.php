<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;
use Exception;

class TenantAssetService
{
    /**
     * Create tenant folder structure
     */
    public function createTenantStructure(Tenant $tenant): bool
    {
        try {
            $basePath = tenant_path('', $tenant);
            
            $folders = [
                'assets',      // General assets (images, fonts, etc.)
                'pwa',         // PWA files (manifest, service worker, icons)
                'seo',         // SEO files (robots.txt, sitemap.xml)
                'documents',   // Documents and downloads
                'media',       // Media files (videos, audio)
            ];
            
            foreach ($folders as $folder) {
                $folderPath = "{$basePath}/{$folder}";
                if (!File::exists($folderPath)) {
                    File::makeDirectory($folderPath, 0755, true);
                }
            }
            
            // Create .gitignore in base folder
            $gitignorePath = "{$basePath}/.gitignore";
            if (!File::exists($gitignorePath)) {
                File::put($gitignorePath, "*\n!.gitignore\n");
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to create tenant structure: {$e->getMessage()}");
        }
    }
    
    /**
     * Delete tenant folder structure
     */
    public function deleteTenantStructure(Tenant $tenant, bool $confirm = true): bool
    {
        try {
            $basePath = tenant_path('', $tenant);
            
            if (File::exists($basePath)) {
                File::deleteDirectory($basePath);
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to delete tenant structure: {$e->getMessage()}");
        }
    }
    
    /**
     * Copy asset to tenant's assets folder
     */
    public function copyAsset(Tenant $tenant, string $sourcePath, string $destinationPath): bool
    {
        try {
            $assetsPath = tenant_path('assets', $tenant);
            $fullDestination = "{$assetsPath}/{$destinationPath}";
            
            // Create directory if needed
            $directory = dirname($fullDestination);
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
            
            File::copy($sourcePath, $fullDestination);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to copy asset: {$e->getMessage()}");
        }
    }
    
    /**
     * Upload asset to tenant's assets folder
     */
    public function uploadAsset(Tenant $tenant, $file, string $subfolder = ''): string
    {
        try {
            $assetsPath = tenant_path('assets', $tenant);
            
            if ($subfolder) {
                $assetsPath .= '/' . trim($subfolder, '/');
            }
            
            if (!File::exists($assetsPath)) {
                File::makeDirectory($assetsPath, 0755, true);
            }
            
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move($assetsPath, $filename);
            
            $relativePath = $subfolder ? "{$subfolder}/{$filename}" : $filename;
            
            return tenant_asset($relativePath, $tenant);
        } catch (Exception $e) {
            throw new Exception("Failed to upload asset: {$e->getMessage()}");
        }
    }
    
    /**
     * Get tenant folder size
     */
    public function getTenantFolderSize(Tenant $tenant): array
    {
        $basePath = tenant_path('', $tenant);
        
        if (!File::exists($basePath)) {
            return [
                'total_bytes' => 0,
                'total_human' => '0 B',
                'breakdown' => []
            ];
        }
        
        $breakdown = [];
        $totalSize = 0;
        
        $folders = ['assets', 'pwa', 'seo', 'documents', 'media'];
        
        foreach ($folders as $folder) {
            $folderPath = "{$basePath}/{$folder}";
            $size = $this->calculateDirectorySize($folderPath);
            $breakdown[$folder] = [
                'bytes' => $size,
                'human' => $this->formatBytes($size)
            ];
            $totalSize += $size;
        }
        
        return [
            'total_bytes' => $totalSize,
            'total_human' => $this->formatBytes($totalSize),
            'breakdown' => $breakdown
        ];
    }
    
    /**
     * Calculate directory size recursively
     */
    protected function calculateDirectorySize(string $path): int
    {
        if (!File::exists($path)) {
            return 0;
        }
        
        $size = 0;
        $files = File::allFiles($path);
        
        foreach ($files as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * Format bytes to human-readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * List all files in tenant's subfolder
     */
    public function listFiles(Tenant $tenant, string $subfolder = 'assets'): array
    {
        $folderPath = tenant_path($subfolder, $tenant);
        
        if (!File::exists($folderPath)) {
            return [];
        }
        
        $files = File::allFiles($folderPath);
        $fileList = [];
        
        foreach ($files as $file) {
            $relativePath = str_replace($folderPath . '/', '', $file->getPathname());
            $fileList[] = [
                'path' => $relativePath,
                'url' => tenant_asset($relativePath, $tenant),
                'size' => $file->getSize(),
                'size_human' => $this->formatBytes($file->getSize()),
                'modified' => date('Y-m-d H:i:s', $file->getMTime())
            ];
        }
        
        return $fileList;
    }
}
