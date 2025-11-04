<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TenantBackupService
{
    private string $backupDisk;

    private string $mysqldumpPath;

    private string $mysqlPath;

    private array $availableMethods;

    public function __construct()
    {
        $this->backupDisk = config('artflow-tenancy.backup.disk', 'tenant-backups');
        $this->mysqldumpPath = config('artflow-tenancy.backup.mysqldump_path', 'mysqldump');
        $this->mysqlPath = config('artflow-tenancy.backup.mysql_path', 'mysql');

        $this->ensureBackupDiskExists();
        $this->detectAvailableMethods();
    }

    /**
     * Detect available backup methods
     */
    private function detectAvailableMethods(): void
    {
        $this->availableMethods = [];

        // Test mysqldump
        if ($this->testBinaryAvailability($this->mysqldumpPath, '--version')) {
            $this->availableMethods[] = 'mysqldump';
        }

        // Test mysql client
        if ($this->testBinaryAvailability($this->mysqlPath, '--version')) {
            $this->availableMethods[] = 'mysql-client';
        }

        // PHP-based methods are always available
        $this->availableMethods[] = 'php-export';
        $this->availableMethods[] = 'phpmyadmin-style';
    }

    /**
     * Test if a binary is available
     */
    private function testBinaryAvailability(string $binary, string $testArg = '--version'): bool
    {
        try {
            $testCommand = '"'.$binary.'" '.$testArg;
            $process = Process::run($testCommand);

            return ! $process->failed();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available backup methods
     */
    public function getAvailableMethods(): array
    {
        return $this->availableMethods;
    }

    /**
     * Get recommended backup method
     */
    public function getRecommendedMethod(): string
    {
        $priority = ['mysqldump', 'php-export', 'phpmyadmin-style', 'mysql-client'];

        foreach ($priority as $method) {
            if (in_array($method, $this->availableMethods)) {
                return $method;
            }
        }

        return 'php-export'; // Ultimate fallback
    }

    /**
     * Create a backup for a tenant (includes database + all directories)
     */
    public function createBackup($tenant, array $options = []): array
    {
        $method = $options['method'] ?? $this->getRecommendedMethod();
        $compress = $options['compress'] ?? config('artflow-tenancy.backup.compress_by_default', true);
        $structureOnly = $options['structure_only'] ?? false;
        $force = $options['force'] ?? false;

        // Validate that the selected method is available
        if (! in_array($method, $this->availableMethods)) {
            throw new \Exception("Backup method '{$method}' is not available. Available methods: ".implode(', ', $this->availableMethods));
        }

        // Get tenant database info
        $dbInfo = $this->getTenantDatabaseInfo($tenant);

        // Generate backup filename with better format: database_domain_timestamp_type_method.sql(.gz)
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $type = $structureOnly ? 'structure' : 'full';
        $extension = $compress ? '.sql.gz' : '.sql';

        // Get primary domain for filename
        $domain = 'unknown';
        if ($tenant->domains && $tenant->domains->count() > 0) {
            $domain = $tenant->domains->first()->domain;
        }
        $domain = str_replace(['.', ' '], ['_', '_'], $domain); // Replace dots and spaces for filename safety

        // Create filename: database_domain_timestamp_type_method.sql(.gz)
        $filename = "{$tenant->database}_{$domain}_{$timestamp}_{$type}_{$method}{$extension}";

        // Create tenant backup directory
        $tenantBackupPath = $this->getTenantBackupPath($tenant);
        $this->ensureTenantDirectoryExists($tenant);

        $backupPath = $tenantBackupPath.'/'.$filename;
        $tempPath = storage_path('app/temp/'.$filename);
        $tempDirBackupPath = storage_path('app/temp/tenant_directories_'.$timestamp);

        // Ensure temp directory exists
        File::ensureDirectoryExists(dirname($tempPath));

        try {
            // 1. Create backup using the selected method
            switch ($method) {
                case 'mysqldump':
                    $this->createMysqlDump($dbInfo, $tempPath, $structureOnly);
                    break;
                case 'php-export':
                    $this->createPhpExport($dbInfo, $tempPath, $structureOnly);
                    break;
                case 'phpmyadmin-style':
                    $this->createPhpMyAdminStyleExport($dbInfo, $tempPath, $structureOnly);
                    break;
                default:
                    throw new \Exception("Unsupported backup method: {$method}");
            }

            // 2. Backup all tenant directories (public and private)
            $directoryManifest = $this->backupTenantDirectories($tenant, $tempPath, $tempDirBackupPath);

            // 3. Compress if requested
            if ($compress) {
                $this->compressBackup($tempPath);
            }

            // 4. Move to final location
            Storage::disk($this->backupDisk)->put($backupPath, File::get($tempPath));

            // 5. Store directory manifest separately and move ZIP file
            if (! empty($directoryManifest) && isset($directoryManifest['directories_zip'])) {
                $zipPath = $directoryManifest['directories_zip_path'];
                $zipFilename = $directoryManifest['directories_zip'];
                $zipStoragePath = $tenantBackupPath.'/'.$zipFilename;

                // Move ZIP file to storage
                if (File::exists($zipPath)) {
                    Storage::disk($this->backupDisk)->put($zipStoragePath, File::get($zipPath));
                    File::delete($zipPath);
                }

                // Store manifest
                $manifestPath = $tenantBackupPath.'/manifest_'.$timestamp.'.json';
                Storage::disk($this->backupDisk)->put($manifestPath, json_encode($directoryManifest, JSON_PRETTY_PRINT));
            }

            // Clean up temp files
            File::delete($tempPath);
            if (File::isDirectory($tempDirBackupPath)) {
                File::deleteDirectory($tempDirBackupPath);
            }

            // Get file info
            $fileSize = Storage::disk($this->backupDisk)->size($backupPath);

            // Store backup metadata
            $this->storeBackupMetadata($tenant, [
                'filename' => $filename,
                'path' => $backupPath,
                'size' => $fileSize,
                'type' => $type,
                'method' => $method,
                'compressed' => $compress,
                'created_at' => Carbon::now(),
                'includes_directories' => ! empty($directoryManifest),
            ]);

            return [
                'filename' => $filename,
                'path' => $backupPath,
                'size' => $fileSize,
                'size_human' => $this->formatBytes($fileSize),
                'type' => $type,
                'method' => $method,
                'compressed' => $compress,
                'created_at' => Carbon::now(),
                'includes_directories' => ! empty($directoryManifest),
                'directories_backed_up' => count($directoryManifest),
                'directory_manifest' => $directoryManifest,
            ];

        } catch (\Exception $e) {
            // Clean up on failure
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }
            if (File::isDirectory($tempDirBackupPath)) {
                File::deleteDirectory($tempDirBackupPath);
            }
            throw $e;
        }
    }

    /**
     * Backup all tenant directories (public and views only)
     * Returns manifest of backed up directories
     */
    private function backupTenantDirectories($tenant, string $sqlPath, string $backupDir): array
    {
        $domain = $tenant->domains?->first()?->domain ?? $tenant->id;
        $manifest = [];

        // Directories to backup - only public and views, no private
        $directories = [
            'public' => base_path("storage/app/public/tenants/{$domain}"),
            'views' => base_path("resources/views/tenants/{$domain}"),
        ];

        File::ensureDirectoryExists($backupDir);

        // Create a single ZIP file containing all directories
        $zipPath = "{$backupDir}/tenant_directories_{$domain}.zip";
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Failed to create directories ZIP archive: {$zipPath}");
        }

        $totalDirectories = 0;
        $totalFiles = 0;

        foreach ($directories as $type => $sourcePath) {
            if (File::isDirectory($sourcePath)) {
                $files = File::allFiles($sourcePath);
                $totalDirectories++;

                foreach ($files as $file) {
                    // Create relative path starting from the directory type
                    // e.g., "public/assets/file.jpg" instead of full absolute path
                    $fullPath = $file->getRealPath();
                    // Normalize paths to use forward slashes for consistent replacement
                    $normalizedSourcePath = rtrim(str_replace('\\', '/', $sourcePath), '/');
                    $normalizedFullPath = str_replace('\\', '/', $fullPath);
                    $relativePath = $type.'/'.str_replace($normalizedSourcePath.'/', '', $normalizedFullPath);
                    $zip->addFile($fullPath, $relativePath);
                    $totalFiles++;
                }

                $manifest[$type] = [
                    'original_path' => $sourcePath,
                    'type' => $type,
                    'backed_up' => true,
                    'files_count' => count($files),
                ];
            } else {
                $manifest[$type] = [
                    'original_path' => $sourcePath,
                    'type' => $type,
                    'backed_up' => false,
                    'reason' => 'Directory does not exist',
                ];
            }
        }

        $zip->close();

        // Remove the ZIP file if no directories were backed up
        if ($totalDirectories === 0) {
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }

            return $manifest;
        }

        return [
            'directories_zip' => basename($zipPath),
            'directories_zip_path' => $zipPath,
            'total_directories' => $totalDirectories,
            'total_files' => $totalFiles,
            'manifest' => $manifest,
        ];
    }

    /**
     * Create a ZIP archive from a directory
     */
    private function createZipArchive(string $sourcePath, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Failed to create ZIP archive: {$zipPath}");
        }

        $files = File::allFiles($sourcePath);

        foreach ($files as $file) {
            $relativePath = str_replace($sourcePath.'/', '', $file->getRealPath());
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();
    }

    /**
     * Embed directory archives into SQL file as base64-encoded comments
     */
    private function embedDirectoriesIntoSQL(string $sqlPath, string $backupDir, array $manifest): void
    {
        $header = "\n\n-- =====================================================\n";
        $header .= "-- EMBEDDED DIRECTORY BACKUPS\n";
        $header .= "-- =====================================================\n";
        $header .= "-- The following directories are embedded as base64-encoded archives\n";
        $header .= "-- They will be automatically restored when using the restore command\n";
        $header .= "-- =====================================================\n\n";

        $sqlContent = File::get($sqlPath);
        $sqlContent .= $header;

        foreach ($manifest as $type => $info) {
            if (isset($info['archive'])) {
                $archivePath = "{$backupDir}/{$info['archive']}";

                if (File::exists($archivePath)) {
                    // Check file size - if too large, skip embedding to avoid memory issues
                    $fileSize = File::size($archivePath);
                    if ($fileSize > 50 * 1024 * 1024) { // 50MB limit
                        $sqlContent .= "-- DIRECTORY ARCHIVE: {$type}\n";
                        $sqlContent .= "-- Original Path: {$info['original_path']}\n";
                        $sqlContent .= "-- Archive: {$info['archive']}\n";
                        $sqlContent .= "-- Status: SKIPPED - File too large ({$fileSize} bytes)\n";
                        $sqlContent .= "-- Note: Archive exists but was not embedded due to size\n";
                        $sqlContent .= "-- The archive file should be backed up separately\n\n";

                        continue;
                    }

                    try {
                        // Read file in chunks to avoid memory exhaustion
                        $handle = fopen($archivePath, 'rb');
                        $binaryContent = '';
                        while (! feof($handle)) {
                            $binaryContent .= fread($handle, 8192); // Read 8KB chunks
                        }
                        fclose($handle);

                        $base64Content = base64_encode($binaryContent);

                        $sqlContent .= "-- DIRECTORY ARCHIVE: {$type}\n";
                        $sqlContent .= "-- Original Path: {$info['original_path']}\n";
                        $sqlContent .= "-- Archive: {$info['archive']}\n";
                        $sqlContent .= "-- Base64 Encoded Content:\n";

                        // Split base64 into lines of 100 characters for readability
                        $lines = str_split($base64Content, 100);
                        foreach ($lines as $line) {
                            $sqlContent .= '-- '.$line."\n";
                        }
                        $sqlContent .= "\n";
                    } catch (\Exception $e) {
                        $sqlContent .= "-- DIRECTORY ARCHIVE: {$type}\n";
                        $sqlContent .= "-- Original Path: {$info['original_path']}\n";
                        $sqlContent .= "-- Archive: {$info['archive']}\n";
                        $sqlContent .= "-- Status: ERROR - Failed to embed: {$e->getMessage()}\n\n";
                    }
                }
            }
        }

        File::put($sqlPath, $sqlContent);
    }

    /**
     * Restore tenant directories from backup
     */
    private function restoreTenantDirectories($tenant, string $backupPath, array $options = []): array
    {
        $domain = $tenant->domains?->first()?->domain ?? $tenant->id;
        $restored = [];

        // Directory selection options
        $restorePublic = $options['restore_public'] ?? true;
        $restoreViews = $options['restore_views'] ?? true;
        $overwriteExisting = $options['overwrite_existing'] ?? false;

        // Check if backupPath is a ZIP file (direct restore) or SQL file (manifest-based restore)
        if (str_ends_with($backupPath, '.zip')) {
            // Direct ZIP file restore (from restoreBackup or direct call)
            $zipPath = $backupPath;
            $isDirectZip = true;
        } else {
            // Manifest-based restore (from separate directory restore)
            $backupDir = dirname($backupPath);
            $manifestFiles = Storage::disk($this->backupDisk)->files($backupDir);

            $manifestFile = null;
            foreach ($manifestFiles as $file) {
                if (str_contains($file, 'manifest_') && str_ends_with($file, '.json')) {
                    $manifestFile = $file;
                    break;
                }
            }

            if (! $manifestFile) {
                return $restored; // No manifest found
            }

            $manifestContent = Storage::disk($this->backupDisk)->get($manifestFile);
            $manifest = json_decode($manifestContent, true);

            if (! isset($manifest['directories_zip'])) {
                return $restored; // No ZIP file in manifest
            }

            $zipFilename = $manifest['directories_zip'];
            $zipPath = $backupDir.'/'.$zipFilename;

            if (! Storage::disk($this->backupDisk)->exists($zipPath)) {
                return $restored; // ZIP file not found
            }

            $isDirectZip = false;
        }

        $tempDir = storage_path('app/temp/restore_'.time());
        File::ensureDirectoryExists($tempDir);

        try {
            if ($isDirectZip) {
                // ZIP file path is from restoreBackup - it's already in temp location
                // Just use it directly
                $tempZipPath = $zipPath;
            } else {
                // Download ZIP to temp location from storage disk
                $zipContent = Storage::disk($this->backupDisk)->get($zipPath);
                $tempZipPath = $tempDir.'/'.basename($zipPath);
                File::put($tempZipPath, $zipContent);
            }

            // Verify the ZIP file exists before trying to open it
            if (! File::exists($tempZipPath)) {
                File::deleteDirectory($tempDir);
                throw new \Exception("ZIP file not found at: {$tempZipPath}");
            }

            // Extract ZIP file
            $zip = new ZipArchive;
            $openResult = $zip->open($tempZipPath);
            if ($openResult !== true) {
                File::deleteDirectory($tempDir);
                throw new \Exception("Failed to open ZIP archive: {$tempZipPath} (Error code: {$openResult})");
            }

            $publicFilesRestored = 0;
            $viewsFilesRestored = 0;
            $directoriesCreated = 0;

            // Extract each directory type to its proper location
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $parts = explode('/', $entry, 2);

                if (count($parts) >= 2) {
                    $type = $parts[0];
                    $relativePath = $parts[1];

                    // Check if this directory type should be restored
                    $shouldRestore = match ($type) {
                        'public' => $restorePublic,
                        'views' => $restoreViews,
                        default => false, // Skip unknown types
                    };

                    if (! $shouldRestore) {
                        continue; // Skip this directory type
                    }

                    // Determine destination path based on type
                    switch ($type) {
                        case 'public':
                            $destinationBase = base_path("storage/app/public/tenants/{$domain}");
                            break;
                        case 'views':
                            $destinationBase = base_path("resources/views/tenants/{$domain}");
                            break;
                        default:
                            continue 2; // Skip unknown types
                    }

                    $destinationPath = $destinationBase.'/'.$relativePath;
                    $destinationDir = dirname($destinationPath);

                    // Ensure destination directory exists
                    if (! File::isDirectory($destinationDir)) {
                        File::ensureDirectoryExists($destinationDir);
                        $directoriesCreated++;
                    }

                    // Check if this is a directory entry (ends with /)
                    if (substr($entry, -1) === '/') {
                        // Create directory if it doesn't exist
                        if (! File::isDirectory($destinationPath)) {
                            File::ensureDirectoryExists($destinationPath);
                            $directoriesCreated++;
                        }

                        continue;
                    }

                    // Check if file already exists
                    if (File::exists($destinationPath) && ! $overwriteExisting) {
                        continue; // Skip this file
                    }

                    // Extract file content and write directly to destination
                    $fileContent = $zip->getFromName($entry);
                    if ($fileContent !== false) {
                        File::ensureDirectoryExists(dirname($destinationPath));
                        File::put($destinationPath, $fileContent);

                        // Increment the correct counter
                        if ($type === 'public') {
                            $publicFilesRestored++;
                        } elseif ($type === 'views') {
                            $viewsFilesRestored++;
                        }
                    }
                }
            }

            $zip->close();

            // Clean up temp directory if we created it
            if (! $isDirectZip) {
                File::deleteDirectory($tempDir);
            }

            // Return proper structure
            $restored = [
                'public' => [
                    'type' => 'public',
                    'files_restored' => $restorePublic ? $publicFilesRestored : 0,
                    'directories_created' => $restorePublic ? $directoriesCreated : 0,
                    'restored' => $restorePublic,
                ],
                'views' => [
                    'type' => 'views',
                    'files_restored' => $restoreViews ? $viewsFilesRestored : 0,
                    'directories_created' => $restoreViews ? $directoriesCreated : 0,
                    'restored' => $restoreViews,
                ],
            ];

        } catch (\Exception $e) {
            // Clean up temp directory on error
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            $restored['error'] = [
                'message' => $e->getMessage(),
                'restored' => false,
            ];
        }

        return $restored;
    }

    /**
     * Extract ZIP archive to a destination directory
     */
    private function extractZipArchive(string $zipPath, string $destinationPath): void
    {
        // Create destination if it doesn't exist
        File::ensureDirectoryExists($destinationPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Failed to open ZIP archive: {$zipPath}");
        }

        // Extract all files
        if (! $zip->extractTo($destinationPath)) {
            $zip->close();
            throw new \Exception("Failed to extract ZIP archive to: {$destinationPath}");
        }

        $zip->close();
    }

    /**
     * Restore a backup for a tenant (database + directories)
     */
    public function restoreBackup($tenant, array $backup, array $options = []): array
    {
        $restoreDatabase = $options['restore_database'] ?? true;
        $restoreDirectories = $options['restore_directories'] ?? true;
        $restorePublic = $options['restore_public'] ?? true;
        $restoreViews = $options['restore_views'] ?? true;
        $overwriteExisting = $options['overwrite_existing'] ?? false;

        $dbInfo = $this->getTenantDatabaseInfo($tenant);
        $backupPath = $backup['path'];

        // Determine the backup method used (from filename or metadata)
        $backupMethod = $this->getBackupMethod($backup);

        // Validate binaries only if needed for the backup method
        if (in_array($backupMethod, ['mysqldump', 'mysql-client'])) {
            $this->validateMysqlBinaries();
        }

        // Download backup file to temp location
        $tempPath = storage_path('app/temp/'.$backup['filename']);
        File::ensureDirectoryExists(dirname($tempPath));

        $backupContent = Storage::disk($this->backupDisk)->get($backupPath);
        File::put($tempPath, $backupContent);

        $result = [
            'database_restored' => false,
            'directories_restored' => [],
        ];

        try {
            // Decompress if needed
            if ($backup['compressed'] ?? false) {
                $this->decompressBackup($tempPath);
            }

            // Restore database if requested
            if ($restoreDatabase) {
                // Drop and recreate database
                $this->recreateDatabase($dbInfo);

                // Restore database using the same method it was created with
                $dbResult = $this->restoreDatabase($dbInfo, $tempPath, $backupMethod);
                $result['database_restored'] = true;
                $result = array_merge($result, $dbResult);
            }

            // Restore directories if requested and included in backup
            if ($restoreDirectories && ($backup['includes_directories'] ?? false)) {
                $directoryOptions = [
                    'restore_public' => $restorePublic,
                    'restore_views' => $restoreViews,
                    'overwrite_existing' => $overwriteExisting,
                ];
                $directoriesRestored = $this->restoreTenantDirectories($tenant, $tempPath, $directoryOptions);
                $result['directories_restored'] = $directoriesRestored;
            }

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
    public function listTenantBackups($tenant): array
    {
        $tenantBackupPath = $this->getTenantBackupPath($tenant);
        $files = Storage::disk($this->backupDisk)->files($tenantBackupPath);

        $backups = [];
        foreach ($files as $file) {
            // Include SQL files and ZIP files, but exclude manifest files
            if ((str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz') ||
                 str_ends_with($file, '.zip')) && ! str_contains($file, 'manifest_')) {
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
            if (! empty($backups)) {
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
     * Keep only latest N backups per tenant, delete older ones
     */
    public function keepLatestBackups(int $keep): int
    {
        $deleted = 0;
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $backups = $this->listTenantBackups($tenant);

            if (count($backups) <= $keep) {
                continue; // Skip if we have fewer backups than desired
            }

            // Keep the first $keep backups (already sorted by most recent)
            $backupsToDelete = array_slice($backups, $keep);

            foreach ($backupsToDelete as $backup) {
                Storage::disk($this->backupDisk)->delete($backup['path']);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get tenant database connection info
     */
    private function getTenantDatabaseInfo($tenant): array
    {
        // Switch to tenant context to get database info
        $originalConnection = DB::getDefaultConnection();

        try {
            tenancy()->initialize($tenant);

            $config = config('database.connections.'.config('database.default'));

            return [
                'host' => $config['host'],
                'port' => $config['port'] ?? 3306,
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'],
                'charset' => $config['charset'] ?? 'utf8mb4',
                'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
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
            '--host='.$dbInfo['host'],
            '--port='.$dbInfo['port'],
            '--user='.$dbInfo['username'],
            '--password='.$dbInfo['password'],
            '--default-character-set='.$dbInfo['charset'],
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

        $process = Process::run(implode(' ', array_map('escapeshellarg', $command)).' > '.escapeshellarg($outputPath));

        if ($process->failed()) {
            throw new \Exception('MySQL dump failed: '.$process->errorOutput());
        }
    }

    /**
     * Create backup using PHP-based export
     */
    private function createPhpExport(array $dbInfo, string $outputPath, bool $structureOnly = false): void
    {
        $connection = $this->createTenantConnection($dbInfo);
        $sql = '';

        // Add header
        $sql .= "-- MySQL dump created by PHP Export\n";
        $sql .= "-- Host: {$dbInfo['host']}    Database: {$dbInfo['database']}\n";
        $sql .= "-- ------------------------------------------------------\n";
        $sql .= '-- Server version: '.$connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION)."\n\n";

        $sql .= "SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;\n";
        $sql .= "SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;\n";
        $sql .= "SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n";
        $sql .= "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, AUTOCOMMIT=0;\n";
        $sql .= "START TRANSACTION;\n\n";

        // Get all tables
        $tables = $connection->select('SHOW TABLES');
        $tableColumn = 'Tables_in_'.$dbInfo['database'];

        foreach ($tables as $table) {
            $tableName = $table->$tableColumn;

            // Get table structure
            $createTable = $connection->select("SHOW CREATE TABLE `{$tableName}`")[0];
            $sql .= "-- Table structure for table `{$tableName}`\n";
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable->{'Create Table'}.";\n\n";

            // Export data if not structure only
            if (! $structureOnly) {
                $sql .= "-- Dumping data for table `{$tableName}`\n";
                $sql .= "LOCK TABLES `{$tableName}` WRITE;\n";

                $rows = $connection->select("SELECT * FROM `{$tableName}`");
                if (! empty($rows)) {
                    $sql .= "INSERT INTO `{$tableName}` VALUES ";
                    $values = [];

                    foreach ($rows as $row) {
                        $rowData = [];
                        foreach ((array) $row as $value) {
                            if ($value === null) {
                                $rowData[] = 'NULL';
                            } else {
                                $rowData[] = $connection->getPdo()->quote($value);
                            }
                        }
                        $values[] = '('.implode(',', $rowData).')';
                    }

                    $sql .= implode(",\n", $values).";\n";
                }

                $sql .= "UNLOCK TABLES;\n\n";
            }
        }

        // Add footer
        $sql .= "COMMIT;\n";
        $sql .= "SET SQL_MODE=@OLD_SQL_MODE;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;\n";
        $sql .= "SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;\n";
        $sql .= "SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;\n";
        $sql .= "SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;\n";
        $sql .= "SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;\n";
        $sql .= "SET AUTOCOMMIT=@OLD_AUTOCOMMIT;\n";

        File::put($outputPath, $sql);
    }

    /**
     * Create backup using phpMyAdmin-style export
     */
    private function createPhpMyAdminStyleExport(array $dbInfo, string $outputPath, bool $structureOnly = false): void
    {
        $connection = $this->createTenantConnection($dbInfo);
        $sql = '';

        // Add phpMyAdmin style header
        $sql .= "-- phpMyAdmin SQL Dump\n";
        $sql .= "-- version 5.2.0 (Generated by ArtFlow Tenancy)\n";
        $sql .= "-- https://www.phpmyadmin.net/\n";
        $sql .= "--\n";
        $sql .= "-- Host: {$dbInfo['host']}:{$dbInfo['port']}\n";
        $sql .= '-- Generation Time: '.Carbon::now()->format('M d, Y \a\t h:i A')."\n";
        $sql .= '-- Server version: '.$connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION)."\n";
        $sql .= '-- PHP Version: '.PHP_VERSION."\n\n";

        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "START TRANSACTION;\n";
        $sql .= "SET time_zone = \"+00:00\";\n\n";

        $sql .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
        $sql .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
        $sql .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
        $sql .= "/*!40101 SET NAMES utf8mb4 */;\n\n";

        $sql .= "--\n";
        $sql .= "-- Database: `{$dbInfo['database']}`\n";
        $sql .= "--\n\n";

        // Get all tables
        $tables = $connection->select('SHOW TABLES');
        $tableColumn = 'Tables_in_'.$dbInfo['database'];

        foreach ($tables as $table) {
            $tableName = $table->$tableColumn;

            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "--\n";
            $sql .= "-- Table structure for table `{$tableName}`\n";
            $sql .= "--\n\n";

            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";

            $createTable = $connection->select("SHOW CREATE TABLE `{$tableName}`")[0];
            $sql .= $createTable->{'Create Table'}.";\n\n";

            // Export data if not structure only
            if (! $structureOnly) {
                $sql .= "--\n";
                $sql .= "-- Dumping data for table `{$tableName}`\n";
                $sql .= "--\n\n";

                $rows = $connection->select("SELECT * FROM `{$tableName}`");
                if (! empty($rows)) {
                    $columns = array_keys((array) $rows[0]);
                    $columnList = '`'.implode('`, `', $columns).'`';

                    $sql .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n";
                    $values = [];

                    foreach ($rows as $row) {
                        $rowData = [];
                        foreach ((array) $row as $value) {
                            if ($value === null) {
                                $rowData[] = 'NULL';
                            } else {
                                $rowData[] = $connection->getPdo()->quote($value);
                            }
                        }
                        $values[] = '('.implode(', ', $rowData).')';
                    }

                    $sql .= implode(",\n", $values).";\n\n";
                } else {
                    $sql .= "-- No data to dump for table `{$tableName}`\n\n";
                }
            }
        }

        // Add footer
        $sql .= "COMMIT;\n\n";
        $sql .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
        $sql .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
        $sql .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

        File::put($outputPath, $sql);
    }

    /**
     * Create a tenant database connection
     */
    private function createTenantConnection(array $dbInfo): \Illuminate\Database\Connection
    {
        $config = [
            'driver' => 'mysql',
            'host' => $dbInfo['host'],
            'port' => $dbInfo['port'],
            'database' => $dbInfo['database'],
            'username' => $dbInfo['username'],
            'password' => $dbInfo['password'],
            'charset' => $dbInfo['charset'],
            'collation' => $dbInfo['collation'],
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];

        // Create a new PDO connection for the tenant database
        $pdo = new \PDO(
            "mysql:host={$dbInfo['host']};port={$dbInfo['port']};dbname={$dbInfo['database']};charset={$dbInfo['charset']}",
            $dbInfo['username'],
            $dbInfo['password'],
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            ]
        );

        return new \Illuminate\Database\MySqlConnection($pdo, $dbInfo['database'], '', $config);
    }

    /**
     * Restore MySQL dump using mysql
     */
    private function restoreMysqlDump(array $dbInfo, string $inputPath): array
    {
        $command = [
            $this->mysqlPath,
            '--host='.$dbInfo['host'],
            '--port='.$dbInfo['port'],
            '--user='.$dbInfo['username'],
            '--password='.$dbInfo['password'],
            '--default-character-set='.$dbInfo['charset'],
            $dbInfo['database'],
        ];

        $process = Process::run(implode(' ', array_map('escapeshellarg', $command)).' < '.escapeshellarg($inputPath));

        if ($process->failed()) {
            throw new \Exception('MySQL restore failed: '.$process->errorOutput());
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
        $rootConfig = config('database.connections.'.config('database.default'));
        $rootConfig['database'] = '';

        $connection = DB::connection();

        // Drop database if exists
        DB::statement("DROP DATABASE IF EXISTS `{$dbInfo['database']}`");

        // Create database
        DB::statement("CREATE DATABASE `{$dbInfo['database']}` CHARACTER SET {$dbInfo['charset']} COLLATE {$dbInfo['charset']}_unicode_ci");
    }

    /**
     * Compress backup file using PHP's gzip functions (cross-platform)
     */
    private function compressBackup(string $filePath): void
    {
        try {
            // Read the original file
            $originalContent = File::get($filePath);

            // Compress using PHP's gzcompress
            $compressedContent = gzencode($originalContent, 9); // Maximum compression level

            if ($compressedContent === false) {
                throw new \Exception('Failed to compress backup file');
            }

            // Write compressed content back to the same file
            File::put($filePath, $compressedContent);

        } catch (\Exception $e) {
            throw new \Exception('Backup compression failed: '.$e->getMessage());
        }
    }

    /**
     * Decompress backup file using PHP's gzip functions (cross-platform)
     */
    private function decompressBackup(string $filePath): void
    {
        try {
            // Read the compressed file
            $compressedContent = File::get($filePath);

            // Decompress using PHP's gzdecode
            $originalContent = gzdecode($compressedContent);

            if ($originalContent === false) {
                throw new \Exception('Failed to decompress backup file');
            }

            // Write decompressed content back to the same file
            File::put($filePath, $originalContent);

        } catch (\Exception $e) {
            throw new \Exception('Backup decompression failed: '.$e->getMessage());
        }
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

        // Detect backup method from filename
        $method = $this->getBackupMethodFromFilename($filename);

        // Check if directories are included (for ZIP files)
        $includesDirectories = str_contains($filename, 'directories') && str_ends_with($filename, '.zip');

        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'created_at' => $lastModified,
            'type' => $type,
            'compressed' => $compressed,
            'method' => $method,
            'includes_directories' => $includesDirectories,
        ];
    }

    /**
     * Get backup method from filename
     */
    private function getBackupMethodFromFilename(string $filename): string
    {
        $parts = explode('_', $filename);

        // Find the method part (should be before .sql or .zip extension)
        foreach ($parts as $part) {
            if (in_array($part, ['mysqldump', 'mysql-client', 'php-export', 'phpmyadmin', 'phpmyadmin-style'])) {
                return $part === 'phpmyadmin-style' ? 'phpmyadmin' : $part;
            }
        }

        // Default to php-export if method cannot be determined
        return 'php-export';
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
                ->select('SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?', [$dbInfo['database']]);

            $records = DB::connection('restore_temp')
                ->select('SELECT SUM(table_rows) as count FROM information_schema.tables WHERE table_schema = ?', [$dbInfo['database']]);

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
    private function storeBackupMetadata($tenant, array $metadata): void
    {
        $metadataPath = $this->getTenantBackupPath($tenant).'/metadata.json';

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
     * Get tenant backup directory path using domain name instead of UUID
     */
    private function getTenantBackupPath($tenant): string
    {
        // Use domain name instead of UUID for better organization
        $domain = $tenant->domains?->first()?->domain ?? $tenant->id;

        return "tenants/{$domain}/backups";
    }

    /**
     * Ensure tenant backup directory exists
     */
    private function ensureTenantDirectoryExists($tenant): void
    {
        $path = $this->getTenantBackupPath($tenant);
        Storage::disk($this->backupDisk)->makeDirectory($path);
    }

    /**
     * Ensure backup disk exists - uses private storage directory
     */
    private function ensureBackupDiskExists(): void
    {
        if (! array_key_exists($this->backupDisk, config('filesystems.disks'))) {
            // Create backup disk configuration using private storage
            config([
                "filesystems.disks.{$this->backupDisk}" => [
                    'driver' => 'local',
                    'root' => storage_path('app/private'),
                    'throw' => false,
                ],
            ]);
        }
    }

    /**
     * Get the backup method used to create a backup
     */
    private function getBackupMethod(array $backup): string
    {
        // Check if method is already in backup info
        if (isset($backup['method'])) {
            return $backup['method'];
        }

        // Parse method from filename
        $filename = $backup['filename'];

        return $this->getBackupMethodFromFilename($filename);
    }

    /**
     * Validate that MySQL binaries are available
     */
    private function validateMysqlBinaries(): void
    {
        // Check if mysqldump is available
        $testCommand = '"'.$this->mysqldumpPath.'"'.' --version';
        $process = Process::run($testCommand);

        if ($process->failed()) {
            $errorMessage = "MySQL dump binary not found at: {$this->mysqldumpPath}\n\n";
            $errorMessage .= "To fix this issue:\n";
            $errorMessage .= "1. Install MySQL client tools and add to PATH, OR\n";
            $errorMessage .= "2. Set the binary path in your .env file:\n";
            $errorMessage .= "   TENANT_BACKUP_MYSQLDUMP_PATH=\"C:\\xampp\\mysql\\bin\\mysqldump.exe\"\n";
            $errorMessage .= "   TENANT_BACKUP_MYSQL_PATH=\"C:\\xampp\\mysql\\bin\\mysql.exe\"\n\n";
            $errorMessage .= "Common locations to check:\n";
            $errorMessage .= "  • C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe\n";
            $errorMessage .= "  • C:\\xampp\\mysql\\bin\\mysqldump.exe\n";
            $errorMessage .= "  • C:\\wamp64\\bin\\mysql\\mysql8.x.x\\bin\\mysqldump.exe\n\n";
            $errorMessage .= 'Error details: '.$process->errorOutput();

            throw new \Exception($errorMessage);
        }

        // Check if mysql restore binary is available
        $testCommand = '"'.$this->mysqlPath.'"'.' --version';
        $process = Process::run($testCommand);

        if ($process->failed()) {
            $errorMessage = "MySQL restore binary not found at: {$this->mysqlPath}\n\n";
            $errorMessage .= "Please configure TENANT_BACKUP_MYSQL_PATH in your .env file.\n";
            $errorMessage .= 'Error details: '.$process->errorOutput();

            throw new \Exception($errorMessage);
        }
    }

    /**
     * Restore database using the appropriate method
     */
    private function restoreDatabase(array $dbInfo, string $inputPath, string $method): array
    {
        switch ($method) {
            case 'mysqldump':
            case 'mysql-client':
                return $this->restoreMysqlDump($dbInfo, $inputPath);
            case 'php-export':
            case 'phpmyadmin':
                return $this->restorePhpExport($dbInfo, $inputPath);
            default:
                throw new \Exception("Unsupported restore method: {$method}");
        }
    }

    /**
     * Restore database from PHP-export backup
     */
    private function restorePhpExport(array $dbInfo, string $inputPath): array
    {
        $connection = $this->createTenantConnection($dbInfo);

        // Read the SQL file
        $sql = File::get($inputPath);

        // Split into individual statements (basic parsing)
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        $executedStatements = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || str_starts_with($statement, '--')) {
                continue; // Skip comments and empty statements
            }

            try {
                $connection->statement($statement);
                $executedStatements++;
            } catch (\Exception $e) {
                // Log the error but continue with other statements
                // Some statements might fail due to dependencies or already existing objects
                Log::warning("Failed to execute SQL statement during restore: {$e->getMessage()}", [
                    'statement' => substr($statement, 0, 100).'...',
                ]);
            }
        }

        // Get stats about restored data
        return $this->getRestoreStats($dbInfo);
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

        return round($bytes, 2).' '.$units[$pow];
    }
}
