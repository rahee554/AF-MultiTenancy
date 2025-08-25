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
            $testCommand = '"' . $binary . '" ' . $testArg;
            $process = Process::run($testCommand);
            return !$process->failed();
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
     * Create a backup for a tenant
     */
    public function createBackup(Tenant $tenant, array $options = []): array
    {
        $method = $options['method'] ?? $this->getRecommendedMethod();
        $compress = $options['compress'] ?? config('artflow-tenancy.backup.compress_by_default', true);
        $structureOnly = $options['structure_only'] ?? false;
        $force = $options['force'] ?? false;

        // Validate that the selected method is available
        if (!in_array($method, $this->availableMethods)) {
            throw new \Exception("Backup method '{$method}' is not available. Available methods: " . implode(', ', $this->availableMethods));
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
        
        $backupPath = $tenantBackupPath . '/' . $filename;
        $tempPath = storage_path('app/temp/' . $filename);
        
        // Ensure temp directory exists
        File::ensureDirectoryExists(dirname($tempPath));
        
        try {
            // Create backup using the selected method
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
                'method' => $method,
                'compressed' => $compress,
                'created_at' => Carbon::now(),
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
        // Validate MySQL binaries before attempting restore
        $this->validateMysqlBinaries();
        
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
        $sql .= "-- Server version: " . $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION) . "\n\n";
        
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
        $tables = $connection->select("SHOW TABLES");
        $tableColumn = 'Tables_in_' . $dbInfo['database'];

        foreach ($tables as $table) {
            $tableName = $table->$tableColumn;
            
            // Get table structure
            $createTable = $connection->select("SHOW CREATE TABLE `{$tableName}`")[0];
            $sql .= "-- Table structure for table `{$tableName}`\n";
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable->{'Create Table'} . ";\n\n";
            
            // Export data if not structure only
            if (!$structureOnly) {
                $sql .= "-- Dumping data for table `{$tableName}`\n";
                $sql .= "LOCK TABLES `{$tableName}` WRITE;\n";
                
                $rows = $connection->select("SELECT * FROM `{$tableName}`");
                if (!empty($rows)) {
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
                        $values[] = '(' . implode(',', $rowData) . ')';
                    }
                    
                    $sql .= implode(",\n", $values) . ";\n";
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
        $sql .= "-- Generation Time: " . Carbon::now()->format('M d, Y \a\t h:i A') . "\n";
        $sql .= "-- Server version: " . $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION) . "\n";
        $sql .= "-- PHP Version: " . PHP_VERSION . "\n\n";
        
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
        $tables = $connection->select("SHOW TABLES");
        $tableColumn = 'Tables_in_' . $dbInfo['database'];

        foreach ($tables as $table) {
            $tableName = $table->$tableColumn;
            
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "--\n";
            $sql .= "-- Table structure for table `{$tableName}`\n";
            $sql .= "--\n\n";
            
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            
            $createTable = $connection->select("SHOW CREATE TABLE `{$tableName}`")[0];
            $sql .= $createTable->{'Create Table'} . ";\n\n";
            
            // Export data if not structure only
            if (!$structureOnly) {
                $sql .= "--\n";
                $sql .= "-- Dumping data for table `{$tableName}`\n";
                $sql .= "--\n\n";
                
                $rows = $connection->select("SELECT * FROM `{$tableName}`");
                if (!empty($rows)) {
                    $columns = array_keys((array) $rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
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
                        $values[] = '(' . implode(', ', $rowData) . ')';
                    }
                    
                    $sql .= implode(",\n", $values) . ";\n\n";
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
            throw new \Exception('Backup compression failed: ' . $e->getMessage());
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
            throw new \Exception('Backup decompression failed: ' . $e->getMessage());
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
     * Validate that MySQL binaries are available
     */
    private function validateMysqlBinaries(): void
    {
        // Check if mysqldump is available
        $testCommand = '"' . $this->mysqldumpPath . '"' . ' --version';
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
            $errorMessage .= "Error details: " . $process->errorOutput();
            
            throw new \Exception($errorMessage);
        }
        
        // Check if mysql restore binary is available
        $testCommand = '"' . $this->mysqlPath . '"' . ' --version';
        $process = Process::run($testCommand);
        
        if ($process->failed()) {
            $errorMessage = "MySQL restore binary not found at: {$this->mysqlPath}\n\n";
            $errorMessage .= "Please configure TENANT_BACKUP_MYSQL_PATH in your .env file.\n";
            $errorMessage .= "Error details: " . $process->errorOutput();
            
            throw new \Exception($errorMessage);
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
