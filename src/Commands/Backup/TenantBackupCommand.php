<?php

namespace ArtflowStudio\Tenancy\Commands\Backup;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TenantBackupCommand extends Command
{
    protected $signature = 'tenant:backup 
                            {action? : Action to perform (backup, restore, list, cleanup)}
                            {tenant? : Tenant ID/slug}
                            {--all : Backup all tenants}
                            {--compress : Compress backup files}
                            {--no-data : Backup structure only}
                            {--force : Force backup without confirmation}
                            {--cleanup-days=30 : Days to keep backups}
                            {--method= : Backup method (mysqldump, mysql-client, php-export)}
                            {--test-methods : Test all available backup methods}
                            {--auto-detect : Auto-detect best available method}
                            {--restore-database-only : Restore only database, skip directories}
                            {--restore-directories-only : Restore only directories, skip database}';

    protected $description = 'Comprehensive tenant database backup and restore system';

    protected TenantBackupService $backupService;

    public function __construct(TenantBackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        // Handle test methods option
        if ($this->option('test-methods')) {
            return $this->testBackupMethods();
        }

        if (! $action) {
            return $this->showInteractiveMenu();
        }

        switch ($action) {
            case 'backup':
                return $this->handleBackup();
            case 'restore':
                return $this->handleRestore();
            case 'list':
                return $this->handleList();
            case 'cleanup':
                return $this->handleCleanup();
            default:
                $this->error("Unknown action: {$action}");

                return 1;
        }
    }

    /**
     * Test all available backup methods
     */
    private function testBackupMethods(): int
    {
        $this->info('🧪 Testing Available Backup Methods...');
        $this->newLine();

        $methods = [
            'mysqldump' => 'Test mysqldump binary availability',
            'mysql-client' => 'Test mysql client binary availability',
            'php-export' => 'Test PHP-based SQL export (always available)',
            'phpmyadmin' => 'Test phpMyAdmin-style export (PHP-based)',
        ];

        $results = [];

        foreach ($methods as $method => $description) {
            $this->info("Testing {$method}...");
            $result = $this->testBackupMethod($method);
            $status = $result['available'] ? '✅ Available' : '❌ Not Available';
            $this->line("  {$status} - {$description}");

            if (! $result['available'] && isset($result['reason'])) {
                $this->line("    Reason: {$result['reason']}");
            }

            $results[$method] = $result;
        }

        $this->newLine();
        $this->info('📋 Recommendation:');
        $recommended = $this->getRecommendedMethod($results);
        $this->info("Best available method: <fg=green>{$recommended}</fg=green>");

        return 0;
    }

    /**
     * Test a specific backup method
     */
    private function testBackupMethod(string $method): array
    {
        switch ($method) {
            case 'mysqldump':
                return $this->testMysqldump();
            case 'mysql-client':
                return $this->testMysqlClient();
            case 'php-export':
            case 'phpmyadmin':
                return ['available' => true, 'reason' => 'PHP-based export always available'];
            default:
                return ['available' => false, 'reason' => 'Unknown method'];
        }
    }

    /**
     * Test mysqldump availability
     */
    private function testMysqldump(): array
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'which mysqldump';

        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            // Test actual execution
            exec('mysqldump --version 2>&1', $versionOutput, $versionCode);
            if ($versionCode === 0) {
                return ['available' => true, 'version' => $versionOutput[0] ?? 'Unknown'];
            }
        }

        return ['available' => false, 'reason' => 'mysqldump binary not found in PATH'];
    }

    /**
     * Test mysql client availability
     */
    private function testMysqlClient(): array
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where mysql' : 'which mysql';

        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            // Test actual execution
            exec('mysql --version 2>&1', $versionOutput, $versionCode);
            if ($versionCode === 0) {
                return ['available' => true, 'version' => $versionOutput[0] ?? 'Unknown'];
            }
        }

        return ['available' => false, 'reason' => 'mysql client binary not found in PATH'];
    }

    /**
     * Get recommended backup method based on availability
     */
    private function getRecommendedMethod(array $results): string
    {
        // Priority order: mysqldump > mysql-client > php-export
        $priority = ['mysqldump', 'mysql-client', 'phpmyadmin', 'php-export'];

        foreach ($priority as $method) {
            if ($results[$method]['available']) {
                return $method;
            }
        }

        return 'php-export'; // Fallback
    }

    private function showInteractiveMenu(): int
    {
        $this->info('🗄️  Tenant Backup & Restore System');
        $this->info('Features: ✅ Auto-detect best method, ✅ Compression enabled, ✅ Full directories backup');
        $this->newLine();

        $this->info('What would you like to do?');
        $this->line('  [0] 📦 Create Backup');
        $this->line('  [1] 🔄 Restore from Backup');
        $this->line('  [2] 📋 List Backups');
        $this->line('  [3] 🧹 Cleanup Old Backups');
        $this->newLine();

        $choice = $this->ask('Select option by number', '0');

        switch ($choice) {
            case '0':
                return $this->handleBackup();
            case '1':
                return $this->handleRestore();
            case '2':
                return $this->handleList();
            case '3':
                return $this->handleCleanup();
            default:
                $this->error('Invalid choice. Please select 0-3.');

                return $this->showInteractiveMenu();
        }

        return 0;
    }

    private function handleBackup(): int
    {
        $this->info('📦 Creating Tenant Backup...');
        $this->newLine();

        if ($this->option('all')) {
            return $this->backupAllTenants();
        }

        $tenant = $this->selectTenant();
        if (! $tenant) {
            return 1;
        }

        return $this->createBackup($tenant);
    }

    private function handleRestore(): int
    {
        $this->info('🔄 Restoring Tenant from Backup...');
        $this->newLine();

        $tenant = $this->selectTenant();
        if (! $tenant) {
            return 1;
        }

        // Check if command-line options are provided
        $hasCommandLineOptions = $this->option('restore-database-only') || $this->option('restore-directories-only');

        if ($hasCommandLineOptions) {
            // Use command-line options - need to select backup first
            $backups = $this->backupService->listTenantBackups($tenant);
            if (empty($backups)) {
                $this->error("No backups found for tenant: {$tenant->id}");

                return 1;
            }

            $backup = $this->selectBackup($backups);
            if (! $backup) {
                return 1;
            }

            return $this->restoreBackup($tenant, $backup);
        } else {
            // Use interactive menu - don't pre-select backup
            return $this->restoreBackupInteractive($tenant);
        }
    }

    private function handleList(): int
    {
        $this->info('📋 Listing Tenant Backups...');
        $this->newLine();

        $tenant = $this->argument('tenant');

        if ($tenant) {
            $tenantModel = Tenant::find($tenant);
            if (! $tenantModel) {
                $this->error("Tenant not found: {$tenant}");

                return 1;
            }
            $this->listTenantBackups($tenantModel);
        } else {
            $this->listAllBackups();
        }

        return 0;
    }

    private function handleCleanup(): int
    {
        $this->info('🧹 Backup Cleanup Wizard...');
        $this->newLine();

        $this->line('Choose cleanup strategy:');
        $this->line('  [0] Keep only latest N backups per tenant');
        $this->line('  [1] Delete backups older than N days');
        $this->line('  [2] Interactive selection (choose which backups to keep)');
        $this->newLine();

        $strategy = $this->ask('Select strategy by number', '0');

        switch ($strategy) {
            case '0':
                return $this->cleanupKeepLatest();
            case '1':
                return $this->cleanupByAge();
            case '2':
                return $this->cleanupInteractive();
            default:
                $this->error('Invalid strategy selection.');

                return 1;
        }
    }

    /**
     * Keep only latest N backups per tenant
     */
    private function cleanupKeepLatest(): int
    {
        $keep = (int) $this->ask('How many latest backups to keep per tenant?', '5');

        if ($keep < 1) {
            $this->error('Must keep at least 1 backup.');

            return 1;
        }

        $this->newLine();
        $this->line("Keeping <fg=green>{$keep}</> latest backup(s) per tenant, deleting older ones.");

        if (! $this->option('force')) {
            if (! $this->confirm('Continue with cleanup?')) {
                $this->info('Cleanup cancelled.');

                return 0;
            }
        }

        $deleted = $this->backupService->keepLatestBackups($keep);

        $this->newLine();
        $this->info('✅ Cleanup completed!');
        $this->line("Deleted <fg=green>{$deleted}</> backup file(s)");

        return 0;
    }

    /**
     * Delete backups older than N days
     */
    private function cleanupByAge(): int
    {
        $days = (int) $this->ask('Delete backups older than how many days?', '30');

        if ($days < 1) {
            $this->error('Must specify at least 1 day.');

            return 1;
        }

        $this->newLine();
        $this->line("Deleting backups older than <fg=green>{$days}</> days.");

        if (! $this->option('force')) {
            if (! $this->confirm('Continue with cleanup?')) {
                $this->info('Cleanup cancelled.');

                return 0;
            }
        }

        $deleted = $this->backupService->cleanupOldBackups($days);

        $this->newLine();
        $this->info('✅ Cleanup completed!');
        $this->line("Deleted <fg=green>{$deleted}</> backup file(s)");

        return 0;
    }

    /**
     * Interactive backup selection for deletion
     */
    private function cleanupInteractive(): int
    {
        $this->info('🗑️  Interactive Backup Deletion');
        $this->newLine();

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return 1;
        }

        $deleted = 0;

        foreach ($tenants as $tenant) {
            $backups = $this->backupService->listTenantBackups($tenant);

            if (empty($backups)) {
                continue;
            }

            $domain = $tenant->domains->count() > 0 ? $tenant->domains->first()->domain : 'no-domain';
            $this->newLine();
            $this->info("📦 Tenant: {$tenant->name} ({$domain})");

            $this->table(
                ['#', 'Filename', 'Size', 'Created', 'Type'],
                array_map(function ($backup, $index) {
                    return [
                        $index,
                        substr($backup['filename'], 0, 50).'...',
                        $backup['size_human'],
                        $backup['created_at']->format('Y-m-d H:i:s'),
                        $backup['type'],
                    ];
                }, $backups, array_keys($backups))
            );

            $this->newLine();
            $this->line('Select backup numbers to DELETE (comma-separated, or press Enter to skip):');
            $this->line('Example: 0,2,3 (deletes backups 0, 2, and 3)');

            $selection = $this->ask('Selection');

            if (empty($selection)) {
                continue;
            }

            $indices = array_map('trim', explode(',', $selection));
            $toDelete = [];

            foreach ($indices as $index) {
                if (is_numeric($index) && isset($backups[$index])) {
                    $toDelete[] = $backups[$index];
                }
            }

            if (! empty($toDelete)) {
                $count = count($toDelete);
                $this->warn("⚠️  About to delete {$count} backup(s) for {$tenant->name}");

                if ($this->confirm('Continue?', false)) {
                    foreach ($toDelete as $backup) {
                        Storage::disk(config('artflow-tenancy.backup.disk', 'tenant-backups'))->delete($backup['path']);
                        $deleted++;
                        $this->line("  ✓ Deleted: {$backup['filename']}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info('✅ Interactive cleanup completed!');
        $this->line("Total deleted: <fg=green>{$deleted}</> backup file(s)");

        return 0;
    }

    private function selectTenant()
    {
        $tenantId = $this->argument('tenant');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant not found: {$tenantId}");

                return null;
            }

            return $tenant;
        }

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return null;
        }

        // Create numbered choices with better formatting
        $this->info('Available tenants:');
        $this->newLine();

        $choices = [];
        foreach ($tenants as $index => $tenant) {
            $domain = $tenant->domains->first() ? $tenant->domains->first()->domain : 'no-domain';
            $displayName = "{$tenant->name} - {$domain} ({$tenant->id})";
            $choices[$index] = $displayName;
            $this->line("  [{$index}] {$displayName}");
        }

        $this->newLine();
        $choice = $this->ask('Select tenant by number', '0');

        if (! is_numeric($choice) || ! isset($tenants[$choice])) {
            $this->error('Invalid tenant selection.');

            return null;
        }

        return $tenants[$choice];
    }

    private function selectBackup(array $backups): ?array
    {
        $this->table(['#', 'Filename', 'Size', 'Created', 'Type'], array_map(function ($backup, $index) {
            return [
                $index,
                $backup['filename'],
                $backup['size_human'],
                $backup['created_at']->format('Y-m-d H:i:s'),
                $backup['type'],
            ];
        }, $backups, array_keys($backups)));

        $choice = $this->ask('Select backup number (most recent is 0):');

        if (! is_numeric($choice) || ! isset($backups[$choice])) {
            $this->error('Invalid backup selection.');

            return null;
        }

        return $backups[$choice];
    }

    private function createBackup(Tenant $tenant): int
    {
        $this->line("Creating backup for tenant: {$tenant->id} ({$tenant->name})");

        $options = [
            'compress' => true, // Always compress by default
            'structure_only' => $this->option('no-data'),
            'force' => $this->option('force'),
        ];

        try {
            $this->newLine();
            $this->withProgressBar(1, function () use ($tenant, $options) {
                $result = $this->backupService->createBackup($tenant, $options);

                $this->newLine(2);
                $this->info('✅ Backup created successfully!');
                $this->line("File: {$result['filename']}");
                $this->line("Size: {$result['size_human']}");
                $this->line("Location: {$result['path']}");
                $this->line("Method: {$result['method']}");
                $this->line('Compression: ✅ Enabled');

                // Show what directories were backed up
                if (isset($result['directories_backed_up']) && $result['directories_backed_up'] > 0) {
                    $domain = $tenant->domains?->first()?->domain ?? $tenant->id;
                    $this->newLine();
                    $this->info('📁 Directories Backed Up:');

                    // Show the separate ZIP file
                    if (isset($result['directory_manifest']['directories_zip'])) {
                        $zipFilename = $result['directory_manifest']['directories_zip'];
                        $this->line("• Directories ZIP: {$zipFilename}");
                        $this->line("• Total directories: {$result['directory_manifest']['total_directories']}");
                        $this->line("• Total files: {$result['directory_manifest']['total_files']}");
                    }

                    // Show individual directory details
                    if (isset($result['directory_manifest']['manifest'])) {
                        foreach ($result['directory_manifest']['manifest'] as $type => $info) {
                            if (isset($info['backed_up']) && $info['backed_up']) {
                                $filesCount = $info['files_count'] ?? 0;
                                $this->line("  - {$type}: {$filesCount} files");
                            }
                        }
                    }
                } else {
                    $this->newLine();
                    $this->warn('⚠️  No tenant directories found to backup');
                }
            });

            return 0;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('❌ Backup failed: '.$e->getMessage());

            return 1;
        }
    }

    private function restoreBackup(Tenant $tenant, array $backup): int
    {
        $this->warn("⚠️  This will REPLACE the current database for tenant: {$tenant->id}");
        $this->line("Backup file: {$backup['filename']}");
        $this->line("Created: {$backup['created_at']->format('Y-m-d H:i:s')}");

        // Check for selective restore options
        $restoreDatabaseOnly = $this->option('restore-database-only');
        $restoreDirectoriesOnly = $this->option('restore-directories-only');

        if ($restoreDatabaseOnly && $restoreDirectoriesOnly) {
            $this->error('Cannot use both --restore-database-only and --restore-directories-only options together.');

            return 1;
        }

        // If no command-line options provided, show interactive menu
        if (! $restoreDatabaseOnly && ! $restoreDirectoriesOnly) {
            return $this->restoreBackupInteractive($tenant, $backup);
        }

        // Validate backup file type matches restore operation
        if ($restoreDatabaseOnly) {
            if (! str_ends_with($backup['filename'], '.sql') && ! str_ends_with($backup['filename'], '.sql.gz')) {
                $this->error('Database restore requires a SQL backup file (.sql or .sql.gz).');
                $this->line('Selected file: '.$backup['filename']);

                return 1;
            }
        }

        if ($restoreDirectoriesOnly) {
            if (! str_ends_with($backup['filename'], '.zip')) {
                $this->error('Directory restore requires a ZIP backup file (.zip).');
                $this->line('Selected file: '.$backup['filename']);

                return 1;
            }
        }

        $restoreOptions = [];
        if ($restoreDatabaseOnly) {
            $restoreOptions = ['restore_database' => true, 'restore_directories' => false];
            $this->info('🔄 Restoring database only (skipping directories)...');
        } elseif ($restoreDirectoriesOnly) {
            $restoreOptions = ['restore_database' => false, 'restore_directories' => true];
            $this->info('🔄 Restoring directories only (skipping database)...');
        } else {
            $restoreOptions = ['restore_database' => true, 'restore_directories' => true];
            $this->info('🔄 Restoring both database and directories...');
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Restore cancelled.');

                return 0;
            }
        }

        try {
            $this->newLine();
            $this->withProgressBar(1, function () use ($tenant, $backup, $restoreOptions) {
                $result = $this->backupService->restoreBackup($tenant, $backup, $restoreOptions);

                $this->newLine(2);
                $this->info('✅ Restore completed successfully!');
                $this->line("Restored from: {$backup['filename']}");

                // Show what was restored
                $databaseRestored = $restoreOptions['restore_database'] ?? true;
                $directoriesRestored = $restoreOptions['restore_directories'] ?? true;

                if ($databaseRestored && isset($result['tables_count'])) {
                    $this->line("Database: ✅ Restored ({$result['tables_count']} tables, {$result['records_count']} records)");
                } elseif ($databaseRestored) {
                    $this->line('Database: ✅ Restored');
                } else {
                    $this->line('Database: ⏭️  Skipped');
                }

                if ($directoriesRestored && isset($result['directories_restored'])) {
                    $directoriesCount = count($result['directories_restored']);
                    $filesCount = array_sum(array_column($result['directories_restored'], 'files_restored'));
                    $this->line("Directories: ✅ Restored ({$directoriesCount} directories, {$filesCount} files)");
                } elseif ($directoriesRestored) {
                    $this->line('Directories: ✅ Restored');
                } else {
                    $this->line('Directories: ⏭️  Skipped');
                }
            });

            return 0;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('❌ Restore failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Interactive restore with menu system
     */
    private function restoreBackupInteractive(Tenant $tenant): int
    {
        $this->newLine();
        $this->info('Select restore type:');
        $this->line('0 - Both Database and Directories');
        $this->line('1 - Database Only');
        $this->line('2 - Directories Only');
        $this->newLine();

        $restoreType = trim($this->ask('Enter your choice (0-2)', '0'));

        // Extract just the first character in case user types "1 - Database Only"
        $restoreType = substr($restoreType, 0, 1);

        $restoreOptions = [];
        $backupToUse = null;

        switch ($restoreType) {
            case '0': // Both
                $restoreOptions = ['restore_database' => true, 'restore_directories' => true];
                $this->info('🔄 Restoring both database and directories...');

                // For "both", we need both database and directory backups
                // Find the latest SQL backup and latest ZIP backup
                $allBackups = $this->backupService->listTenantBackups($tenant);
                $sqlBackups = array_filter($allBackups, function ($b) {
                    return str_ends_with($b['filename'], '.sql') || str_ends_with($b['filename'], '.sql.gz');
                });
                $zipBackups = array_filter($allBackups, function ($b) {
                    return str_ends_with($b['filename'], '.zip');
                });

                if (empty($sqlBackups)) {
                    $this->error('No SQL backup files found for database restore.');

                    return 1;
                }
                if (empty($zipBackups)) {
                    $this->error('No ZIP backup files found for directory restore.');

                    return 1;
                }

                // Reindex arrays to ensure we can access index 0
                $sqlBackups = array_values($sqlBackups);
                $zipBackups = array_values($zipBackups);

                // Use latest of each type
                $databaseBackup = $sqlBackups[0];
                $directoryBackup = $zipBackups[0];

                $this->line("Database backup: {$databaseBackup['filename']}");
                $this->line("Directory backup: {$directoryBackup['filename']}");

                // We'll need to modify the restore logic to handle two backups
                // For now, let's restore database first, then directories
                $this->warn("⚠️  This will REPLACE current data for tenant: {$tenant->id}");
                $this->line("Database from: {$databaseBackup['filename']}");
                $this->line("Directories from: {$directoryBackup['filename']}");

                // Check for overwrite option for directories
                $this->newLine();
                $overwriteExisting = $this->confirm('Overwrite existing files if they exist?', false);
                if ($overwriteExisting) {
                    $this->warn('⚠️  Existing files will be overwritten!');
                } else {
                    $this->info('ℹ️  Existing files will be skipped.');
                }

                if (! $this->confirm('Are you sure you want to continue?')) {
                    $this->info('Restore cancelled.');

                    return 0;
                }

                // Restore database first
                try {
                    $this->newLine();
                    $this->info('Restoring database...');
                    $dbResult = $this->backupService->restoreBackup($tenant, $databaseBackup, [
                        'restore_database' => true,
                        'restore_directories' => false,
                    ]);

                    $this->info('✅ Database restored successfully!');
                    if (isset($dbResult['tables_count'])) {
                        $this->line("Restored: {$dbResult['tables_count']} tables, {$dbResult['records_count']} records");
                    }
                } catch (\Exception $e) {
                    $this->error('❌ Database restore failed: '.$e->getMessage());

                    return 1;
                }

                // Then restore directories
                try {
                    $this->newLine();
                    $this->info('Restoring directories...');
                    $dirResult = $this->backupService->restoreBackup($tenant, $directoryBackup, [
                        'restore_database' => false,
                        'restore_directories' => true,
                        'overwrite_existing' => $overwriteExisting,
                    ]);

                    $this->info('✅ Directories restored successfully!');
                    if (isset($dirResult['directories_restored'])) {
                        $directoriesCount = count($dirResult['directories_restored']);
                        $filesCount = array_sum(array_column($dirResult['directories_restored'], 'files_restored'));
                        $this->line("Restored: {$directoriesCount} directories, {$filesCount} files");
                    }
                } catch (\Exception $e) {
                    $this->error('❌ Directory restore failed: '.$e->getMessage());

                    return 1;
                }

                $this->newLine();
                $this->info('✅ Both database and directories restored successfully!');

                return 0;

            case '1': // Database Only
                $restoreOptions = ['restore_database' => true, 'restore_directories' => false];
                $this->info('🔄 Restoring database only...');

                // Get all SQL backups for this tenant
                $allBackups = $this->backupService->listTenantBackups($tenant);
                $sqlBackups = array_filter($allBackups, function ($b) {
                    return str_ends_with($b['filename'], '.sql') || str_ends_with($b['filename'], '.sql.gz');
                });

                if (empty($sqlBackups)) {
                    $this->error('No SQL backup files found for database restore.');

                    return 1;
                }

                // Show SQL backups
                $this->newLine();
                $this->info('Available database backups:');
                $this->table(['#', 'Filename', 'Size', 'Created'], array_map(function ($backup, $index) {
                    return [
                        $index,
                        $backup['filename'],
                        $backup['size_human'],
                        $backup['created_at']->format('Y-m-d H:i:s'),
                    ];
                }, array_values($sqlBackups), array_keys($sqlBackups)));

                $dbChoice = $this->ask('Select database backup number (0 = latest)', '0');

                if (! is_numeric($dbChoice) || ! isset($sqlBackups[$dbChoice])) {
                    $this->error('Invalid database backup selection.');

                    return 1;
                }

                $backupToUse = $sqlBackups[$dbChoice];
                break;

            case '2': // Directories Only
                $restoreOptions = ['restore_database' => false, 'restore_directories' => true];
                $this->info('🔄 Restoring directories only...');

                // Get all ZIP backups for this tenant
                $allBackups = $this->backupService->listTenantBackups($tenant);
                $zipBackups = array_filter($allBackups, function ($b) {
                    return str_ends_with($b['filename'], '.zip');
                });

                if (empty($zipBackups)) {
                    $this->error('No ZIP backup files found for directory restore.');

                    return 1;
                }

                // Show ZIP backups
                $this->newLine();
                $this->info('Available directory backups:');
                $this->table(['#', 'Filename', 'Size', 'Created'], array_map(function ($backup, $index) {
                    return [
                        $index,
                        $backup['filename'],
                        $backup['size_human'],
                        $backup['created_at']->format('Y-m-d H:i:s'),
                    ];
                }, array_values($zipBackups), array_keys($zipBackups)));

                $dirChoice = $this->ask('Select directory backup number (0 = latest)', '0');

                if (! is_numeric($dirChoice) || ! isset($zipBackups[$dirChoice])) {
                    $this->error('Invalid directory backup selection.');

                    return 1;
                }

                $backupToUse = $zipBackups[$dirChoice];

                // Now ask which directories to restore
                $this->newLine();
                $this->info('Select directories to restore:');
                $this->line('0 - All Directories');
                $this->line('1 - Public Directory Only');
                $this->line('2 - Views Directory Only');
                $this->newLine();

                $directoryChoice = trim($this->ask('Enter your choice (0-2)', '0'));

                // Extract just the first character in case user types "1 - Public Directory Only"
                $directoryChoice = substr($directoryChoice, 0, 1);

                // Set directory options based on choice
                switch ($directoryChoice) {
                    case '0': // All directories
                        $restoreOptions['restore_public'] = true;
                        $restoreOptions['restore_views'] = true;
                        $this->info('Restoring all directories (public and views)...');
                        break;
                    case '1': // Public only
                        $restoreOptions['restore_public'] = true;
                        $restoreOptions['restore_views'] = false;
                        $this->info('Restoring public directory only...');
                        break;
                    case '2': // Views only
                        $restoreOptions['restore_public'] = false;
                        $restoreOptions['restore_views'] = true;
                        $this->info('Restoring views directory only...');
                        break;
                    default:
                        $this->error('Invalid directory selection.');

                        return 1;
                }

                // Ask about overwriting existing files
                $this->newLine();
                $overwriteExisting = $this->confirm('Overwrite existing files if they exist?', false);
                $restoreOptions['overwrite_existing'] = $overwriteExisting;
                if ($overwriteExisting) {
                    $this->warn('⚠️  Existing files will be overwritten!');
                } else {
                    $this->info('ℹ️  Existing files will be skipped.');
                }
                break;

            default:
                $this->error('Invalid restore type selection.');

                return 1;
        }

        if (! $backupToUse) {
            $this->error('No backup selected for restore.');

            return 1;
        }

        $this->newLine();
        $this->warn("⚠️  This will REPLACE current data for tenant: {$tenant->id}");
        $this->line("Backup file: {$backupToUse['filename']}");
        $this->line("Created: {$backupToUse['created_at']->format('Y-m-d H:i:s')}");

        // Check for overwrite option if restoring directories (only if not already set in switch above)
        if (($restoreOptions['restore_directories'] ?? false) && ! isset($restoreOptions['overwrite_existing'])) {
            $overwriteExisting = $this->confirm('Overwrite existing files if they exist?', false);
            $restoreOptions['overwrite_existing'] = $overwriteExisting;
            if ($overwriteExisting) {
                $this->warn('⚠️  Existing files will be overwritten!');
            } else {
                $this->info('ℹ️  Existing files will be skipped.');
            }
        }

        if (! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Restore cancelled.');

            return 0;
        }

        try {
            $this->newLine();
            $this->withProgressBar(1, function () use ($tenant, $backupToUse, $restoreOptions) {
                $result = $this->backupService->restoreBackup($tenant, $backupToUse, $restoreOptions);

                $this->newLine(2);
                $this->info('✅ Restore completed successfully!');
                $this->line("Restored from: {$backupToUse['filename']}");

                // Show what was restored
                $databaseRestored = $restoreOptions['restore_database'] ?? true;
                $directoriesRestored = $restoreOptions['restore_directories'] ?? true;

                if ($databaseRestored && isset($result['tables_count'])) {
                    $this->line("Database: ✅ Restored ({$result['tables_count']} tables, {$result['records_count']} records)");
                } elseif ($databaseRestored) {
                    $this->line('Database: ✅ Restored');
                } else {
                    $this->line('Database: ⏭️  Skipped');
                }

                if ($directoriesRestored && isset($result['directories_restored'])) {
                    $directoriesCount = count($result['directories_restored']);
                    $filesCount = array_sum(array_column($result['directories_restored'], 'files_restored'));
                    $this->line("Directories: ✅ Restored ({$directoriesCount} directories, {$filesCount} files)");
                } elseif ($directoriesRestored) {
                    $this->line('Directories: ✅ Restored');
                } else {
                    $this->line('Directories: ⏭️  Skipped');
                }
            });

            return 0;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('❌ Restore failed: '.$e->getMessage());

            return 1;
        }
    }

    private function backupAllTenants(): int
    {
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return 1;
        }

        $this->info("Creating backups for {$tenants->count()} tenants...");
        $this->newLine();

        $options = [
            'compress' => true, // Always compress by default
            'structure_only' => $this->option('no-data'),
            'force' => true, // Force for bulk operations
        ];

        $successful = 0;
        $failed = 0;

        $this->withProgressBar($tenants->count(), function ($progressBar) use ($tenants, $options, &$successful, &$failed) {
            foreach ($tenants as $tenant) {
                try {
                    $this->backupService->createBackup($tenant, $options);
                    $successful++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed to backup tenant {$tenant->id}: ".$e->getMessage());
                }
                $progressBar->advance();
            }
        });

        $this->newLine(2);
        $this->info('✅ Bulk backup completed!');
        $this->line("Successful: {$successful}");
        if ($failed > 0) {
            $this->line("Failed: {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }

    private function listTenantBackups(Tenant $tenant): void
    {
        $backups = $this->backupService->listTenantBackups($tenant);

        if (empty($backups)) {
            $this->warn("No backups found for tenant: {$tenant->id}");

            return;
        }

        $this->info("Backups for tenant: {$tenant->id} ({$tenant->name})");
        $this->newLine();

        $this->table(['#', 'Filename', 'Size', 'Created', 'Type'], array_map(function ($backup, $index) {
            return [
                $index,
                $backup['filename'],
                $backup['size_human'],
                $backup['created_at']->format('Y-m-d H:i:s'),
                $backup['type'],
            ];
        }, $backups, array_keys($backups)));
    }

    private function listAllBackups(): void
    {
        $allBackups = $this->backupService->listAllBackups();

        if (empty($allBackups)) {
            $this->warn('No backups found.');

            return;
        }

        $this->info('All Tenant Backups:');
        $this->newLine();

        $tableData = [];
        foreach ($allBackups as $tenantId => $backups) {
            $tenant = Tenant::find($tenantId);
            $tenantName = $tenant ? $tenant->name : 'Unknown';
            $domain = $tenant && $tenant->domains->count() > 0 ? $tenant->domains->first()->domain : 'no-domain';

            foreach ($backups as $index => $backup) {
                $tableData[] = [
                    $tenantName,
                    $domain,
                    $backup['created_at']->format('Y-m-d H:i:s'),
                    $backup['type'],
                    $backup['size_human'],
                    $backup['filename'],
                ];
            }
        }

        $this->table(['Tenant Name', 'Domain', 'Created At', 'Type', 'Size', 'Filename'], $tableData);
    }
}
