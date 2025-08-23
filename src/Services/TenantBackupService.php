<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ArtflowStudio\Tenancy\Models\Tenant;
use Carbon\Carbon;
use ZipArchive;

class TenantBackupService
{
    private string $backupDisk;
    private string $mysqldumpPath;
    private string $mysqlPath;

    public function __construct()
    {
        $this->backupDisk = config('artflow-tenancy.backup.disk', 'tenant-backups');
        $this->mysqldumpPath = config('artflow-tenancy.backup.mysqldump_path', 'mysqldump');
        $this->mysqlPath = config('artflow-tenancy.backup.mysql_path', 'mysql');
        
        $this->ensureBackupDiskExists();
    }

    /**
     * Create a backup for a tenant
     */
    public function createBackup(Tenant $tenant, array $options = []): array
    {
        $compress = $options['compress'] ?? false;
        $structureOnly = $options['structure_only'] ?? false;
        $force = $options['force'] ?? false;

        // Get tenant database info
        $dbInfo = $this->getTenantDatabaseInfo($tenant);
        
        // Generate backup filename
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $type = $structureOnly ? 'structure' : 'full';
        $extension = $compress ? '.sql.gz' : '.sql';
        $filename = "tenant_{$tenant->id}_{$timestamp}_{$type}{$extension}";
        
        // Create tenant backup directory
        $tenantBackupPath = $this->getTenantBackupPath($tenant);
        $this->ensureTenantDirectoryExists($tenant);
        
        $backupPath = $tenantBackupPath . '/' . $filename;
        $tempPath = storage_path('app/temp/' . $filename);
        
        // Ensure temp directory exists
        File::ensureDirectoryExists(dirname($tempPath));
        
        try {
            // Create backup using mysqldump
            $this->createMysqlDump($dbInfo, $tempPath, $structureOnly);
            
            // Compress if requested
            if ($compress) {
                $this->compressBackup($tempPath);
            }
            
            // Move to final location
            Storage::disk($this->backupDisk)->put($backupPath, File::get($tempPath));
            
            // Clean up temp file
            File::delete($tempPath);
            
            // Get file info
            $fileSize = Storage::disk($this->backupDisk)->size($backupPath);
            
            // Store backup metadata
            $this->storeBackupMetadata($tenant, [
                'filename' => $filename,
                'path' => $backupPath,
                'size' => $fileSize,
                'type' => $type,
                'compressed' => $compress,
                'created_at' => Carbon::now(),
            ]);
            
            return [
                'filename' => $filename,
                'path' => $backupPath,
                'size' => $fileSize,
                'size_human' => $this->formatBytes($fileSize),
                'type' => $type,
                'compressed' => $compress,
                'created_at' => Carbon::now(),
            ];
            
        } catch (\Exception $e) {
            // Clean up on failure
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }
            throw $e;
        }
    }

    /**
     * Restore a backup for a tenant
     */
    public function restoreBackup(Tenant $tenant, array $backup): array
    {
        $dbInfo = $this->getTenantDatabaseInfo($tenant);
        $backupPath = $backup['path'];
        
        // Download backup file to temp location
        $tempPath = storage_path('app/temp/' . $backup['filename']);
        File::ensureDirectoryExists(dirname($tempPath));
        
        $backupContent = Storage::disk($this->backupDisk)->get($backupPath);
        File::put($tempPath, $backupContent);
        
        try {
            // Decompress if needed
            if ($backup['compressed'] ?? false) {
                $this->decompressBackup($tempPath);
            }
            
            // Drop and recreate database
            $this->recreateDatabase($dbInfo);
            
            // Restore from backup
            $result = $this->restoreMysqlDump($dbInfo, $tempPath);
            
            // Clean up temp file
            File::delete($tempPath);
            
            return $result;
            
        } catch (\Exception $e) {
            // Clean up on failure
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }
            throw $e;
        }
    }

    /**
     * List backups for a specific tenant
     */
    public function listTenantBackups(Tenant $tenant): array
    {
        $tenantBackupPath = $this->getTenantBackupPath($tenant);
        $files = Storage::disk($this->backupDisk)->files($tenantBackupPath);
        
        $backups = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz')) {
                $backups[] = $this->getBackupInfo($file);
            }
        }
        
        // Sort by creation time (most recent first)
        usort($backups, function ($a, $b) {
            return $b['created_at']->timestamp - $a['created_at']->timestamp;
        });
        
        return array_values($backups);
    }

    /**
     * List all backups for all tenants
     */
    public function listAllBackups(): array
    {
        $allBackups = [];
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            $backups = $this->listTenantBackups($tenant);
            if (!empty($backups)) {
                $allBackups[$tenant->id] = $backups;
            }
        }
        
        return $allBackups;
    }

    /**
     * Clean up old backups
     */
    public function cleanupOldBackups(int $days): int
    {
        $cutoffDate = Carbon::now()->subDays($days);
        $deleted = 0;
        
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $tenantBackupPath = $this->getTenantBackupPath($tenant);
            $files = Storage::disk($this->backupDisk)->files($tenantBackupPath);
            
            foreach ($files as $file) {
                $lastModified = Carbon::createFromTimestamp(
                    Storage::disk($this->backupDisk)->lastModified($file)
                );
                
                if ($lastModified->lt($cutoffDate)) {
                    Storage::disk($this->backupDisk)->delete($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    /**
     * Get tenant database connection info
     */
    private function getTenantDatabaseInfo(Tenant $tenant): array
    {
        // Switch to tenant context to get database info
        $originalConnection = DB::getDefaultConnection();
        
        try {
            tenancy()->initialize($tenant);
            
            $config = config('database.connections.' . config('database.default'));
            
            return [
                'host' => $config['host'],
                'port' => $config['port'] ?? 3306,
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'],
                'charset' => $config['charset'] ?? 'utf8mb4',
            ];
            
        } finally {
            tenancy()->end();
            DB::setDefaultConnection($originalConnection);
        }
    }

    /**
     * Create MySQL dump using mysqldump
     */
    private function createMysqlDump(array $dbInfo, string $outputPath, bool $structureOnly = false): void
    {
        $command = [
            $this->mysqldumpPath,
            '--host=' . $dbInfo['host'],
            '--port=' . $dbInfo['port'],
            '--user=' . $dbInfo['username'],
            '--password=' . $dbInfo['password'],
            '--default-character-set=' . $dbInfo['charset'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--set-gtid-purged=OFF',
        ];

        if ($structureOnly) {
            $command[] = '--no-data';
        }

        $command[] = $dbInfo['database'];

        $process = Process::run(implode(' ', array_map('escapeshellarg', $command)) . ' > ' . escapeshellarg($outputPath));

        if ($process->failed()) {
            throw new \Exception('MySQL dump failed: ' . $process->errorOutput());
        }
    }

    /**
     * Restore MySQL dump using mysql
     */
    private function restoreMysqlDump(array $dbInfo, string $inputPath): array
    {
        $command = [
            $this->mysqlPath,
            '--host=' . $dbInfo['host'],
            '--port=' . $dbInfo['port'],
            '--user=' . $dbInfo['username'],
            '--password=' . $dbInfo['password'],
            '--default-character-set=' . $dbInfo['charset'],
            $dbInfo['database'],
        ];

        $process = Process::run(implode(' ', array_map('escapeshellarg', $command)) . ' < ' . escapeshellarg($inputPath));

        if ($process->failed()) {
            throw new \Exception('MySQL restore failed: ' . $process->errorOutput());
        }

        // Get stats about restored data
        return $this->getRestoreStats($dbInfo);
    }

    /**
     * Recreate database (drop and create)
     */
    private function recreateDatabase(array $dbInfo): void
    {
        // Connect without database selection to drop/create
        $rootConfig = config('database.connections.' . config('database.default'));
        $rootConfig['database'] = '';
        
        $connection = DB::connection();
        
        // Drop database if exists
        DB::statement("DROP DATABASE IF EXISTS `{$dbInfo['database']}`");
        
        // Create database
        DB::statement("CREATE DATABASE `{$dbInfo['database']}` CHARACTER SET {$dbInfo['charset']} COLLATE {$dbInfo['charset']}_unicode_ci");
    }

    /**
     * Compress backup file
     */
    private function compressBackup(string $filePath): void
    {
        $compressedPath = $filePath . '.gz';
        
        $process = Process::run("gzip -c " . escapeshellarg($filePath) . " > " . escapeshellarg($compressedPath));
        
        if ($process->failed()) {
            throw new \Exception('Backup compression failed: ' . $process->errorOutput());
        }
        
        // Replace original with compressed
        File::move($compressedPath, $filePath);
    }

    /**
     * Decompress backup file
     */
    private function decompressBackup(string $filePath): void
    {
        $decompressedPath = str_replace('.gz', '', $filePath);
        
        $process = Process::run("gunzip -c " . escapeshellarg($filePath) . " > " . escapeshellarg($decompressedPath));
        
        if ($process->failed()) {
            throw new \Exception('Backup decompression failed: ' . $process->errorOutput());
        }
        
        // Replace compressed with decompressed
        File::move($decompressedPath, $filePath);
    }

    /**
     * Get backup file information
     */
    private function getBackupInfo(string $filePath): array
    {
        $filename = basename($filePath);
        $size = Storage::disk($this->backupDisk)->size($filePath);
        $lastModified = Carbon::createFromTimestamp(
            Storage::disk($this->backupDisk)->lastModified($filePath)
        );
        
        // Parse filename for type and compression
        $compressed = str_ends_with($filename, '.gz');
        $type = str_contains($filename, '_structure_') ? 'structure' : 'full';
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'created_at' => $lastModified,
            'type' => $type,
            'compressed' => $compressed,
        ];
    }

    /**
     * Get restore statistics
     */
    private function getRestoreStats(array $dbInfo): array
    {
        // Connect to restored database to get stats
        $originalConnection = DB::getDefaultConnection();
        
        try {
            // Temporarily set connection to restored database
            config(['database.connections.restore_temp' => [
                'driver' => 'mysql',
                'host' => $dbInfo['host'],
                'port' => $dbInfo['port'],
                'database' => $dbInfo['database'],
                'username' => $dbInfo['username'],
                'password' => $dbInfo['password'],
                'charset' => $dbInfo['charset'],
            ]]);
            
            $tables = DB::connection('restore_temp')
                ->select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [$dbInfo['database']]);
            
            $records = DB::connection('restore_temp')
                ->select("SELECT SUM(table_rows) as count FROM information_schema.tables WHERE table_schema = ?", [$dbInfo['database']]);
            
            return [
                'tables_count' => $tables[0]->count ?? 0,
                'records_count' => $records[0]->count ?? 0,
            ];
            
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }

    /**
     * Store backup metadata
     */
    private function storeBackupMetadata(Tenant $tenant, array $metadata): void
    {
        $metadataPath = $this->getTenantBackupPath($tenant) . '/metadata.json';
        
        $existingMetadata = [];
        if (Storage::disk($this->backupDisk)->exists($metadataPath)) {
            $existingMetadata = json_decode(
                Storage::disk($this->backupDisk)->get($metadataPath),
                true
            ) ?? [];
        }
        
        $existingMetadata[] = array_merge($metadata, [
            'created_at' => $metadata['created_at']->toISOString(),
        ]);
        
        Storage::disk($this->backupDisk)->put(
            $metadataPath,
            json_encode($existingMetadata, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Get tenant backup directory path
     */
    private function getTenantBackupPath(Tenant $tenant): string
    {
        return "tenants/{$tenant->id}/backups";
    }

    /**
     * Ensure tenant backup directory exists
     */
    private function ensureTenantDirectoryExists(Tenant $tenant): void
    {
        $path = $this->getTenantBackupPath($tenant);
        Storage::disk($this->backupDisk)->makeDirectory($path);
    }

    /**
     * Ensure backup disk exists
     */
    private function ensureBackupDiskExists(): void
    {
        if (!array_key_exists($this->backupDisk, config('filesystems.disks'))) {
            // Create backup disk configuration
            config([
                "filesystems.disks.{$this->backupDisk}" => [
                    'driver' => 'local',
                    'root' => storage_path('app/tenant-backups'),
                    'throw' => false,
                ]
            ]);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
