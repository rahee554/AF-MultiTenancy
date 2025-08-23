<?php

namespace ArtflowStudio\Tenancy\Commands\Backup;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantBackupService;

class BackupManagementCommand extends Command
{
    protected $signature = 'tenant:backup-manager';
    protected $description = 'Interactive tenant backup and restore management';

    protected TenantBackupService $backupService;

    public function __construct(TenantBackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    public function handle(): int
    {
        $this->displayHeader();
        
        while (true) {
            $action = $this->showMainMenu();
            
            if ($action === 'exit') {
                $this->info('👋 Goodbye!');
                break;
            }
            
            $this->handleAction($action);
            $this->newLine();
            
            if (!$this->confirm('Continue with another operation?', true)) {
                break;
            }
        }
        
        return 0;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════════╗');
        $this->line('║                    🗄️  TENANT BACKUP MANAGER                 ║');
        $this->line('║                                                               ║');
        $this->line('║    Comprehensive backup and restore system for tenant        ║');
        $this->line('║    databases with isolated storage and full MySQL control    ║');
        $this->line('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function showMainMenu(): string
    {
        return $this->choice(
            '🔧 What would you like to do?',
            [
                'backup' => '📦 Create Backup',
                'restore' => '🔄 Restore from Backup', 
                'list' => '📋 List Backups',
                'cleanup' => '🧹 Cleanup Old Backups',
                'settings' => '⚙️  Backup Settings',
                'exit' => '🚪 Exit'
            ],
            'backup'
        );
    }

    private function handleAction(string $action): void
    {
        switch ($action) {
            case 'backup':
                $this->handleBackupFlow();
                break;
            case 'restore':
                $this->handleRestoreFlow();
                break;
            case 'list':
                $this->handleListFlow();
                break;
            case 'cleanup':
                $this->handleCleanupFlow();
                break;
            case 'settings':
                $this->handleSettingsFlow();
                break;
        }
    }

    private function handleBackupFlow(): void
    {
        $this->info('📦 Backup Creation Wizard');
        $this->newLine();

        // Select backup scope
        $scope = $this->choice(
            'Backup scope:',
            [
                'single' => 'Single Tenant',
                'multiple' => 'Multiple Tenants',
                'all' => 'All Tenants'
            ],
            'single'
        );

        $tenants = $this->selectTenants($scope);
        if (empty($tenants)) {
            $this->error('No tenants selected.');
            return;
        }

        // Backup options
        $options = $this->getBackupOptions();

        // Confirm and execute
        $this->displayBackupSummary($tenants, $options);
        
        if ($this->confirm('Proceed with backup?', true)) {
            $this->executeBackups($tenants, $options);
        }
    }

    private function handleRestoreFlow(): void
    {
        $this->info('🔄 Restore Wizard');
        $this->newLine();

        // Select tenant
        $tenant = $this->selectSingleTenant();
        if (!$tenant) {
            return;
        }

        // List available backups
        $backups = $this->backupService->listTenantBackups($tenant);
        if (empty($backups)) {
            $this->error("No backups found for tenant: {$tenant->id}");
            return;
        }

        $this->displayBackupsTable($backups);
        
        $backupIndex = $this->ask('Select backup number (0 = most recent):');
        if (!is_numeric($backupIndex) || !isset($backups[$backupIndex])) {
            $this->error('Invalid backup selection.');
            return;
        }

        $selectedBackup = $backups[$backupIndex];
        
        // Confirm restore
        $this->displayRestoreSummary($tenant, $selectedBackup);
        
        if ($this->confirmDangerousAction("restore tenant '{$tenant->id}' database")) {
            $this->executeRestore($tenant, $selectedBackup);
        }
    }

    private function handleListFlow(): void
    {
        $this->info('📋 Backup Listings');
        $this->newLine();

        $scope = $this->choice(
            'List backups for:',
            [
                'single' => 'Single Tenant',
                'all' => 'All Tenants',
                'summary' => 'Summary Statistics'
            ],
            'single'
        );

        switch ($scope) {
            case 'single':
                $tenant = $this->selectSingleTenant();
                if ($tenant) {
                    $this->displaySingleTenantBackups($tenant);
                }
                break;
            case 'all':
                $this->displayAllTenantBackups();
                break;
            case 'summary':
                $this->displayBackupSummary();
                break;
        }
    }

    private function handleCleanupFlow(): void
    {
        $this->info('🧹 Backup Cleanup Wizard');
        $this->newLine();

        $days = $this->ask('Delete backups older than how many days?', '30');
        
        if (!is_numeric($days) || $days < 1) {
            $this->error('Invalid number of days.');
            return;
        }

        $this->warn("⚠️  This will permanently delete all backups older than {$days} days!");
        
        if ($this->confirmDangerousAction("delete old backups")) {
            $deleted = $this->backupService->cleanupOldBackups((int) $days);
            $this->info("✅ Deleted {$deleted} old backup files.");
        }
    }

    private function handleSettingsFlow(): void
    {
        $this->info('⚙️  Backup Settings');
        $this->newLine();

        $this->displayCurrentSettings();
        
        $this->newLine();
        $this->line('💡 To modify settings, update your .env file or config/artflow-tenancy.php');
        $this->line('   Key environment variables:');
        $this->line('   • TENANT_BACKUP_ENABLED=true');
        $this->line('   • TENANT_BACKUP_COMPRESS=true');
        $this->line('   • TENANT_BACKUP_RETENTION_DAYS=30');
        $this->line('   • TENANT_BACKUP_MYSQLDUMP_PATH=mysqldump');
        $this->line('   • TENANT_BACKUP_MYSQL_PATH=mysql');
    }

    private function selectTenants(string $scope): array
    {
        $allTenants = Tenant::all();
        
        if ($allTenants->isEmpty()) {
            $this->error('No tenants found.');
            return [];
        }

        switch ($scope) {
            case 'single':
                $tenant = $this->selectSingleTenant();
                return $tenant ? [$tenant] : [];
                
            case 'multiple':
                return $this->selectMultipleTenants($allTenants);
                
            case 'all':
                if ($this->confirm("Backup all {$allTenants->count()} tenants?", false)) {
                    return $allTenants->toArray();
                }
                return [];
                
            default:
                return [];
        }
    }

    private function selectSingleTenant(): ?Tenant
    {
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return null;
        }

        $choices = [];
        foreach ($tenants as $tenant) {
            $choices[$tenant->id] = "{$tenant->id} - {$tenant->name}";
        }

        $selectedId = $this->choice('Select tenant:', $choices);
        return Tenant::find($selectedId);
    }

    private function selectMultipleTenants($tenants): array
    {
        $selected = [];
        $choices = [];
        
        foreach ($tenants as $tenant) {
            $choices[$tenant->id] = "{$tenant->id} - {$tenant->name}";
        }

        $this->line('Select multiple tenants (type "done" when finished):');
        
        while (true) {
            $remaining = array_diff_key($choices, array_flip(array_column($selected, 'id')));
            
            if (empty($remaining)) {
                $this->line('All tenants selected.');
                break;
            }

            $remaining['done'] = '✅ Done selecting';
            
            $choice = $this->choice('Select tenant:', $remaining);
            
            if ($choice === 'done') {
                break;
            }
            
            $selected[] = Tenant::find($choice);
            $this->info("Added: {$choice}");
        }

        return $selected;
    }

    private function getBackupOptions(): array
    {
        $this->line('🔧 Backup Options:');
        
        return [
            'compress' => $this->confirm('Compress backups?', true),
            'structure_only' => $this->confirm('Structure only (no data)?', false),
            'include_routines' => $this->confirm('Include stored procedures/functions?', true),
            'include_triggers' => $this->confirm('Include triggers?', true),
            'include_events' => $this->confirm('Include events?', true),
        ];
    }

    private function displayBackupSummary(array $tenants, array $options): void
    {
        $this->newLine();
        $this->info('📋 Backup Summary:');
        $this->line("Tenants to backup: " . count($tenants));
        
        foreach ($tenants as $tenant) {
            $this->line("  • {$tenant->id} - {$tenant->name}");
        }
        
        $this->newLine();
        $this->line('Options:');
        foreach ($options as $key => $value) {
            $status = $value ? '✅ Yes' : '❌ No';
            $this->line("  • " . ucfirst(str_replace('_', ' ', $key)) . ": {$status}");
        }
    }

    private function displayRestoreSummary(Tenant $tenant, array $backup): void
    {
        $this->newLine();
        $this->error('⚠️  DESTRUCTIVE OPERATION WARNING ⚠️');
        $this->line("This will COMPLETELY REPLACE the database for tenant: {$tenant->id}");
        $this->line("Tenant Name: {$tenant->name}");
        $this->line("Backup File: {$backup['filename']}");
        $this->line("Backup Date: {$backup['created_at']->format('Y-m-d H:i:s')}");
        $this->line("Backup Size: {$backup['size_human']}");
        $this->line("Backup Type: {$backup['type']}");
        $this->newLine();
    }

    private function displayBackupsTable(array $backups): void
    {
        $this->newLine();
        $this->table(
            ['#', 'Filename', 'Size', 'Created', 'Type'],
            array_map(function ($backup, $index) {
                return [
                    $index,
                    $backup['filename'],
                    $backup['size_human'],
                    $backup['created_at']->format('Y-m-d H:i:s'),
                    $backup['type']
                ];
            }, $backups, array_keys($backups))
        );
    }

    private function displaySingleTenantBackups(Tenant $tenant): void
    {
        $backups = $this->backupService->listTenantBackups($tenant);
        
        if (empty($backups)) {
            $this->warn("No backups found for tenant: {$tenant->id}");
            return;
        }

        $this->info("Backups for tenant: {$tenant->id} ({$tenant->name})");
        $this->displayBackupsTable($backups);
        
        // Show storage statistics
        $totalSize = array_sum(array_column($backups, 'size'));
        $this->newLine();
        $this->line("Total backups: " . count($backups));
        $this->line("Total size: " . $this->formatBytes($totalSize));
    }

    private function displayAllTenantBackups(): void
    {
        $allBackups = $this->backupService->listAllBackups();
        
        if (empty($allBackups)) {
            $this->warn('No backups found for any tenant.');
            return;
        }

        foreach ($allBackups as $tenantId => $backups) {
            $tenant = Tenant::find($tenantId);
            $tenantName = $tenant ? $tenant->name : 'Unknown';
            
            $this->newLine();
            $this->info("📦 {$tenantId} - {$tenantName} ({count($backups)} backups)");
            
            $this->table(
                ['#', 'Filename', 'Size', 'Created', 'Type'],
                array_map(function ($backup, $index) {
                    return [
                        $index,
                        $backup['filename'],
                        $backup['size_human'],
                        $backup['created_at']->format('Y-m-d H:i:s'),
                        $backup['type']
                    ];
                }, array_slice($backups, 0, 3), array_keys(array_slice($backups, 0, 3))) // Show only first 3
            );
            
            if (count($backups) > 3) {
                $this->line("... and " . (count($backups) - 3) . " more backups");
            }
        }
    }

    private function displayCurrentSettings(): void
    {
        $settings = [
            'Backup Enabled' => config('artflow-tenancy.backup.enabled', true) ? '✅ Yes' : '❌ No',
            'Default Compression' => config('artflow-tenancy.backup.compress_by_default', true) ? '✅ Yes' : '❌ No',
            'Retention Days' => config('artflow-tenancy.backup.retention_days', 30),
            'Backup Disk' => config('artflow-tenancy.backup.disk', 'tenant-backups'),
            'MySQL Dump Path' => config('artflow-tenancy.backup.mysqldump_path', 'mysqldump'),
            'MySQL Path' => config('artflow-tenancy.backup.mysql_path', 'mysql'),
            'Include Routines' => config('artflow-tenancy.backup.include_routines', true) ? '✅ Yes' : '❌ No',
            'Include Triggers' => config('artflow-tenancy.backup.include_triggers', true) ? '✅ Yes' : '❌ No',
            'Include Events' => config('artflow-tenancy.backup.include_events', true) ? '✅ Yes' : '❌ No',
        ];

        $this->table(['Setting', 'Value'], array_map(function ($key, $value) {
            return [$key, $value];
        }, array_keys($settings), $settings));
    }

    private function executeBackups(array $tenants, array $options): void
    {
        $this->newLine();
        $this->info("🚀 Starting backup process for " . count($tenants) . " tenant(s)...");
        
        $successful = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->line("Backing up {$tenant->id}...");
                $result = $this->backupService->createBackup($tenant, $options);
                $this->info("✅ {$tenant->id}: {$result['filename']} ({$result['size_human']})");
                $successful++;
            } catch (\Exception $e) {
                $this->error("❌ {$tenant->id}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("📊 Backup completed!");
        $this->line("Successful: {$successful}");
        if ($failed > 0) {
            $this->line("Failed: {$failed}");
        }
    }

    private function executeRestore(Tenant $tenant, array $backup): void
    {
        $this->newLine();
        $this->info("🔄 Starting restore process...");
        
        try {
            $result = $this->backupService->restoreBackup($tenant, $backup);
            $this->newLine();
            $this->info("✅ Restore completed successfully!");
            $this->line("Tables restored: {$result['tables_count']}");
            $this->line("Records restored: {$result['records_count']}");
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ Restore failed: " . $e->getMessage());
        }
    }

    private function confirmDangerousAction(string $action): bool
    {
        $this->warn("This is a DESTRUCTIVE operation that cannot be undone!");
        $this->line("Type 'CONFIRM' to {$action}:");
        
        $confirmation = $this->ask('Confirmation');
        return $confirmation === 'CONFIRM';
    }

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
