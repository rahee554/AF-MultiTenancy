<?php

namespace ArtflowStudio\Tenancy\Commands\FastPanel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use Exception;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'fastpanel:sync-database 
                            {database_name : Name of the database to sync}
                            {--assign-user= : FastPanel user ID to assign ownership}
                            {--link-site= : Site ID to link database}
                            {--dry-run : Show what would be done without executing}';

    protected $description = 'Sync manually created database with FastPanel metadata and assign ownership';

    public function handle(): int
    {
        try {
            $databaseName = $this->argument('database_name');
            $assignUser = $this->option('assign-user');
            $linkSite = $this->option('link-site');
            $dryRun = $this->option('dry-run');

            $this->info("🔄 Syncing database: {$databaseName}");
            $this->newLine();

            // 1. Check if database exists in MySQL
            if (!$this->checkDatabaseExists($databaseName)) {
                $this->error("❌ Database '{$databaseName}' does not exist in MySQL");
                return 1;
            }

            // 2. Check current FastPanel status
            $currentStatus = $this->checkFastPanelStatus($databaseName);
            $this->displayCurrentStatus($currentStatus);

            // 3. Sync with FastPanel
            if (!$dryRun) {
                $this->syncWithFastPanel();
                $this->info('✅ FastPanel database sync completed');
            } else {
                $this->comment('🔍 DRY RUN: Would sync databases with FastPanel');
            }

            // 4. Assign user ownership if requested
            if ($assignUser) {
                if (!$dryRun) {
                    $this->assignUserOwnership($databaseName, $assignUser);
                } else {
                    $this->comment("🔍 DRY RUN: Would assign user {$assignUser} to database {$databaseName}");
                }
            }

            // 5. Link to site if requested
            if ($linkSite) {
                if (!$dryRun) {
                    $this->linkToSite($databaseName, $linkSite);
                } else {
                    $this->comment("🔍 DRY RUN: Would link database {$databaseName} to site {$linkSite}");
                }
            }

            // 6. Show final status
            if (!$dryRun) {
                $this->newLine();
                $finalStatus = $this->checkFastPanelStatus($databaseName);
                $this->displayFinalStatus($finalStatus);
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function checkDatabaseExists(string $databaseName): bool
    {
        try {
            $result = DB::select("SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME = ?", [$databaseName]);
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkFastPanelStatus(string $databaseName): array
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json databases list');
            if (!$result->successful()) {
                return ['exists' => false, 'error' => 'Failed to get database list'];
            }

            $databases = json_decode($result->output(), true);
            if (!is_array($databases)) {
                return ['exists' => false, 'error' => 'Invalid JSON response'];
            }

            $database = collect($databases)->firstWhere('name', $databaseName);
            
            if (!$database) {
                return ['exists' => false];
            }

            return [
                'exists' => true,
                'id' => $database['id'],
                'name' => $database['name'],
                'owner' => $database['owner'] ?? null,
                'site' => $database['site'] ?? null,
                'server' => $database['server'] ?? null,
                'created_at' => $database['created_at'] ?? null,
            ];
        } catch (Exception $e) {
            return ['exists' => false, 'error' => $e->getMessage()];
        }
    }

    private function displayCurrentStatus(array $status): void
    {
        $this->info('📊 Current Status:');
        
        if (!$status['exists']) {
            $this->line('   ❌ Not found in FastPanel metadata');
            if (isset($status['error'])) {
                $this->line("   🔍 Error: {$status['error']}");
            }
            return;
        }

        $this->line('   ✅ Found in FastPanel metadata');
        $this->line("   🆔 Database ID: {$status['id']}");
        
        if ($status['owner']) {
            $this->line("   👤 Owner: {$status['owner']['username']} (ID: {$status['owner']['id']})");
        } else {
            $this->line('   👤 Owner: Unassigned');
        }

        if ($status['site']) {
            $this->line("   🌐 Site: {$status['site']['domain']} (ID: {$status['site']['id']})");
        } else {
            $this->line('   🌐 Site: Not linked');
        }

        if ($status['server']) {
            $this->line("   🖥️  Server: {$status['server']['name']} (ID: {$status['server']['id']})");
        }
    }

    private function syncWithFastPanel(): void
    {
        $result = Process::run('sudo /usr/local/fastpanel2/fastpanel databases sync');
        if (!$result->successful()) {
            throw new Exception('Failed to sync databases: ' . $result->errorOutput());
        }
    }

    private function assignUserOwnership(string $databaseName, string $userId): void
    {
        // First verify user exists
        $users = $this->getFastPanelUsers();
        $user = collect($users)->firstWhere('id', (int)$userId);
        
        if (!$user) {
            throw new Exception("User with ID {$userId} not found");
        }

        $this->info("👤 Assigning database to user: {$user['username']} (ID: {$userId})");

        // Get database ID from FastPanel
        $status = $this->checkFastPanelStatus($databaseName);
        if (!$status['exists']) {
            throw new Exception("Database not found in FastPanel after sync");
        }

        // Use SQLite to update ownership (FastPanel internal DB)
        $this->updateFastPanelOwnership($status['id'], $userId);
        $this->info('✅ User ownership assigned');
    }

    private function linkToSite(string $databaseName, string $siteId): void
    {
        // Verify site exists
        $sites = $this->getFastPanelSites();
        $site = collect($sites)->firstWhere('id', (int)$siteId);
        
        if (!$site) {
            throw new Exception("Site with ID {$siteId} not found");
        }

        $this->info("🌐 Linking database to site: {$site['domain']} (ID: {$siteId})");

        // Get database ID from FastPanel
        $status = $this->checkFastPanelStatus($databaseName);
        if (!$status['exists']) {
            throw new Exception("Database not found in FastPanel after sync");
        }

        // Update site linkage
        $this->updateFastPanelSiteLink($status['id'], $siteId);
        $this->info('✅ Site linkage updated');
    }

    private function getFastPanelUsers(): array
    {
        $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json users list');
        if (!$result->successful()) {
            throw new Exception('Failed to get users');
        }

        $users = json_decode($result->output(), true);
        return is_array($users) ? $users : [];
    }

    private function getFastPanelSites(): array
    {
        $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json sites list');
        if (!$result->successful()) {
            throw new Exception('Failed to get sites');
        }

        $sites = json_decode($result->output(), true);
        return is_array($sites) ? $sites : [];
    }

    private function updateFastPanelOwnership(int $databaseId, string $userId): void
    {
        // Backup and update FastPanel SQLite DB
        $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';
        
        // Create backup
        $backupFile = "/root/fastpanel2.db.backup." . time();
        Process::run("sudo cp {$sqliteDb} {$backupFile}");
        
        // Update ownership in db table
        $updateOwnerSql = "UPDATE db SET owner_id = {$userId} WHERE id = {$databaseId};";
        Process::run("sudo sqlite3 {$sqliteDb} \"{$updateOwnerSql}\"");
        
        $this->comment("📁 Backup created: {$backupFile}");
    }

    private function updateFastPanelSiteLink(int $databaseId, string $siteId): void
    {
        // Update site linkage in FastPanel SQLite DB
        $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';
        
        $updateSiteSql = "UPDATE db SET site_id = {$siteId} WHERE id = {$databaseId};";
        Process::run("sudo sqlite3 {$sqliteDb} \"{$updateSiteSql}\"");
    }

    private function displayFinalStatus(array $status): void
    {
        $this->info('🎉 Final Status:');
        
        if ($status['exists']) {
            $this->line('   ✅ Database synced with FastPanel');
            
            if ($status['owner']) {
                $this->line("   👤 Owner: {$status['owner']['username']} (ID: {$status['owner']['id']})");
            } else {
                $this->line('   👤 Owner: Still unassigned');
            }

            if ($status['site']) {
                $this->line("   🌐 Site: {$status['site']['domain']} (ID: {$status['site']['id']})");
            } else {
                $this->line('   🌐 Site: Not linked');
            }
        } else {
            $this->warn('   ⚠️  Database still not found in FastPanel');
        }
    }
}
