<?php

namespace ArtflowStudio\Tenancy\Commands\Backup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantBackupService;
use Carbon\Carbon;

class TenantBackupCommand extends Command
{
    protected $signature = 'tenant:backup 
                            {action? : Action to perform (backup, restore, list, cleanup)}
                            {tenant? : Tenant ID/slug}
                            {--all : Backup all tenants}
                            {--compress : Compress backup files}
                            {--no-data : Backup structure only}
                            {--force : Force backup without confirmation}
                            {--cleanup-days=30 : Days to keep backups}';

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

        if (!$action) {
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

    private function showInteractiveMenu(): int
    {
        $this->info('🗄️  Tenant Backup & Restore System');
        $this->newLine();

        $action = $this->choice('What would you like to do?', [
            'backup' => 'Create Backup',
            'restore' => 'Restore from Backup',
            'list' => 'List Backups',
            'cleanup' => 'Cleanup Old Backups',
        ], 'backup');

        switch ($action) {
            case 'backup':
                return $this->handleBackup();
            case 'restore':
                return $this->handleRestore();
            case 'list':
                return $this->handleList();
            case 'cleanup':
                return $this->handleCleanup();
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
        if (!$tenant) {
            return 1;
        }

        return $this->createBackup($tenant);
    }

    private function handleRestore(): int
    {
        $this->info('🔄 Restoring Tenant from Backup...');
        $this->newLine();

        $tenant = $this->selectTenant();
        if (!$tenant) {
            return 1;
        }

        $backups = $this->backupService->listTenantBackups($tenant);
        if (empty($backups)) {
            $this->error("No backups found for tenant: {$tenant->id}");
            return 1;
        }

        $backup = $this->selectBackup($backups);
        if (!$backup) {
            return 1;
        }

        return $this->restoreBackup($tenant, $backup);
    }

    private function handleList(): int
    {
        $this->info('📋 Listing Tenant Backups...');
        $this->newLine();

        $tenant = $this->argument('tenant');
        
        if ($tenant) {
            $tenantModel = Tenant::find($tenant);
            if (!$tenantModel) {
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
        $this->info('🧹 Cleaning up Old Backups...');
        $this->newLine();

        $days = (int) $this->option('cleanup-days');
        
        if (!$this->option('force')) {
            if (!$this->confirm("Delete backups older than {$days} days?")) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $deleted = $this->backupService->cleanupOldBackups($days);
        $this->info("✅ Deleted {$deleted} old backup files.");

        return 0;
    }

    private function selectTenant(): ?Tenant
    {
        $tenantId = $this->argument('tenant');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
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

        $choices = [];
        foreach ($tenants as $tenant) {
            $choices[$tenant->id] = "{$tenant->id} - {$tenant->name}";
        }

        $selectedId = $this->choice('Select a tenant:', $choices);
        return Tenant::find($selectedId);
    }

    private function selectBackup(array $backups): ?array
    {
        $this->table(['#', 'Filename', 'Size', 'Created', 'Type'], array_map(function ($backup, $index) {
            return [
                $index,
                $backup['filename'],
                $backup['size_human'],
                $backup['created_at']->format('Y-m-d H:i:s'),
                $backup['type']
            ];
        }, $backups, array_keys($backups)));

        $choice = $this->ask('Select backup number (most recent is 0):');
        
        if (!is_numeric($choice) || !isset($backups[$choice])) {
            $this->error('Invalid backup selection.');
            return null;
        }

        return $backups[$choice];
    }

    private function createBackup(Tenant $tenant): int
    {
        $this->line("Creating backup for tenant: {$tenant->id} ({$tenant->name})");

        $options = [
            'compress' => $this->option('compress'),
            'structure_only' => $this->option('no-data'),
            'force' => $this->option('force'),
        ];

        try {
            $this->newLine();
            $this->withProgressBar(1, function () use ($tenant, $options) {
                $result = $this->backupService->createBackup($tenant, $options);
                
                $this->newLine(2);
                $this->info("✅ Backup created successfully!");
                $this->line("File: {$result['filename']}");
                $this->line("Size: {$result['size_human']}");
                $this->line("Location: {$result['path']}");
                
                if (isset($result['compressed']) && $result['compressed']) {
                    $this->line("Compression: Enabled");
                }
            });

            return 0;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error("❌ Backup failed: " . $e->getMessage());
            return 1;
        }
    }

    private function restoreBackup(Tenant $tenant, array $backup): int
    {
        $this->warn("⚠️  This will REPLACE the current database for tenant: {$tenant->id}");
        $this->line("Backup file: {$backup['filename']}");
        $this->line("Created: {$backup['created_at']->format('Y-m-d H:i:s')}");
        
        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Restore cancelled.');
                return 0;
            }
        }

        try {
            $this->newLine();
            $this->withProgressBar(1, function () use ($tenant, $backup) {
                $result = $this->backupService->restoreBackup($tenant, $backup);
                
                $this->newLine(2);
                $this->info("✅ Database restored successfully!");
                $this->line("Restored from: {$backup['filename']}");
                $this->line("Tables restored: {$result['tables_count']}");
                $this->line("Records restored: {$result['records_count']}");
            });

            return 0;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error("❌ Restore failed: " . $e->getMessage());
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
            'compress' => $this->option('compress'),
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
                    $this->warn("Failed to backup tenant {$tenant->id}: " . $e->getMessage());
                }
                $progressBar->advance();
            }
        });

        $this->newLine(2);
        $this->info("✅ Bulk backup completed!");
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
                $backup['type']
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
            
            foreach ($backups as $index => $backup) {
                $tableData[] = [
                    $tenantId,
                    $tenantName,
                    $index,
                    $backup['filename'],
                    $backup['size_human'],
                    $backup['created_at']->format('Y-m-d H:i:s'),
                    $backup['type']
                ];
            }
        }

        $this->table(['Tenant ID', 'Tenant Name', '#', 'Filename', 'Size', 'Created', 'Type'], $tableData);
    }
}
