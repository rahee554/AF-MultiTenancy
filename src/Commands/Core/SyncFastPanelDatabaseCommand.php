<?php

namespace ArtflowStudio\Tenancy\Commands\Core;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Exception;

class SyncFastPanelDatabaseCommand extends Command
{
    protected $signature = 'fastpanel:sync-database 
                            {database : Database name to sync}
                            {--assign-user= : Assign database to panel user ID}
                            {--link-site= : Link database to site ID}
                            {--create-mapping : Create database user mapping}';

    protected $description = 'Sync database with FastPanel and assign ownership';

    public function handle(): int
    {
        $databaseName = $this->argument('database');
        $assignUserId = $this->option('assign-user');
        $linkSiteId = $this->option('link-site');
        $createMapping = $this->option('create-mapping');

        $this->info("🔄 Syncing database '{$databaseName}' with FastPanel...");

        try {
            // Step 1: Run FastPanel database sync
            $this->syncFastPanelDatabases();

            // Step 2: Get database info from FastPanel
            $dbInfo = $this->getFastPanelDatabaseInfo($databaseName);
            if (!$dbInfo) {
                $this->error("❌ Database '{$databaseName}' not found in FastPanel after sync");
                return 1;
            }

            $this->info("✅ Database found in FastPanel (ID: {$dbInfo['id']})");

            // Step 3: Assign user if requested
            if ($assignUserId) {
                $this->assignDatabaseUser($dbInfo['id'], $assignUserId, $databaseName);
            }

            // Step 4: Link to site if requested
            if ($linkSiteId) {
                $this->linkDatabaseToSite($dbInfo['id'], $linkSiteId);
            }

            // Step 5: Create database user mapping if requested
            if ($createMapping) {
                $this->createDatabaseUserMapping($dbInfo['id'], $databaseName);
            }

            $this->displayDatabaseInfo($dbInfo);
            
            return 0;
        } catch (Exception $e) {
            $this->error("❌ Error syncing database: {$e->getMessage()}");
            return 1;
        }
    }

    private function syncFastPanelDatabases(): void
    {
        $this->info('🔄 Running FastPanel database sync...');
        
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel databases sync');
            
            if ($result->successful()) {
                $this->info('✅ FastPanel database sync completed');
            } else {
                $this->warn("⚠️  FastPanel sync warning: {$result->errorOutput()}");
            }
        } catch (Exception $e) {
            throw new Exception("FastPanel sync failed: {$e->getMessage()}");
        }
    }

    private function getFastPanelDatabaseInfo(string $databaseName): ?array
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json databases list');
            
            if (!$result->successful()) {
                throw new Exception("Failed to list databases: {$result->errorOutput()}");
            }

            $databases = json_decode($result->output(), true);
            if (!is_array($databases)) {
                throw new Exception("Invalid JSON response from FastPanel");
            }

            foreach ($databases as $db) {
                if ($db['name'] === $databaseName) {
                    return $db;
                }
            }

            return null;
        } catch (Exception $e) {
            throw new Exception("Failed to get database info: {$e->getMessage()}");
        }
    }

    private function assignDatabaseUser(int $dbId, int $userId, string $databaseName): void
    {
        $this->info("🔗 Assigning database to user ID {$userId}...");

        try {
            // First, get the database_user entry for the MySQL login
            $dbUserInfo = $this->findOrCreateDatabaseUser($databaseName);
            
            if (!$dbUserInfo) {
                $this->warn("⚠️  Could not find or create database_user entry for '{$databaseName}'");
                return;
            }

            // Create mapping in datbases_users table
            $this->createDatabaseMapping($dbUserInfo['id'], $dbId);
            
            // Update db table owner_id
            $this->updateDatabaseOwner($dbId, $userId);
            
            $this->info("✅ Database assigned to user successfully");
        } catch (Exception $e) {
            throw new Exception("Failed to assign database user: {$e->getMessage()}");
        }
    }

    private function findOrCreateDatabaseUser(string $databaseName): ?array
    {
        try {
            // Query FastPanel SQLite database for existing database_user
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';
            
            $query = "SELECT id, login, owner_id FROM database_user WHERE login = '{$databaseName}' OR login LIKE '%{$databaseName}%' ORDER BY id LIMIT 1";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$query}\"");
            
            if ($result->successful() && !empty(trim($result->output()))) {
                $row = explode('|', trim($result->output()));
                return [
                    'id' => (int)$row[0],
                    'login' => $row[1],
                    'owner_id' => (int)$row[2]
                ];
            }

            // If not found, we might need to create it or it might be created by sync
            $this->comment("💡 Database user entry not found for '{$databaseName}'. This might be normal after database creation.");
            return null;
        } catch (Exception $e) {
            $this->warn("Could not query database_user table: {$e->getMessage()}");
            return null;
        }
    }

    private function createDatabaseMapping(int $userId, int $databaseId): void
    {
        try {
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';
            
            // Check if mapping already exists
            $checkQuery = "SELECT id FROM datbases_users WHERE user_id = {$userId} AND database_id = {$databaseId}";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$checkQuery}\"");
            
            if ($result->successful() && !empty(trim($result->output()))) {
                $this->info("✅ Database mapping already exists");
                return;
            }

            // Create the mapping
            $insertQuery = "INSERT INTO datbases_users (user_id, database_id) VALUES ({$userId}, {$databaseId});";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$insertQuery}\"");
            
            if (!$result->successful()) {
                throw new Exception("Failed to create database mapping: {$result->errorOutput()}");
            }

            $this->info("✅ Database user mapping created");
        } catch (Exception $e) {
            throw new Exception("Failed to create database mapping: {$e->getMessage()}");
        }
    }

    private function updateDatabaseOwner(int $dbId, int $ownerId): void
    {
        try {
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';
            
            $updateQuery = "UPDATE db SET owner_id = {$ownerId} WHERE id = {$dbId};";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$updateQuery}\"");
            
            if (!$result->successful()) {
                throw new Exception("Failed to update database owner: {$result->errorOutput()}");
            }

            $this->info("✅ Database owner updated");
        } catch (Exception $e) {
            throw new Exception("Failed to update database owner: {$e->getMessage()}");
        }
    }

    private function linkDatabaseToSite(int $dbId, int $siteId): void
    {
        $this->info("🌐 Linking database to site ID {$siteId}...");

        try {
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';
            
            $updateQuery = "UPDATE db SET site_id = {$siteId} WHERE id = {$dbId};";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$updateQuery}\"");
            
            if (!$result->successful()) {
                throw new Exception("Failed to link database to site: {$result->errorOutput()}");
            }

            $this->info("✅ Database linked to site successfully");
        } catch (Exception $e) {
            throw new Exception("Failed to link database to site: {$e->getMessage()}");
        }
    }

    private function createDatabaseUserMapping(int $dbId, string $databaseName): void
    {
        $this->info("👤 Creating database user mapping...");

        try {
            // This method would create the database_user entry if it doesn't exist
            // and then create the mapping in datbases_users
            $dbUserInfo = $this->findOrCreateDatabaseUser($databaseName);
            
            if ($dbUserInfo) {
                $this->createDatabaseMapping($dbUserInfo['id'], $dbId);
            } else {
                $this->comment("💡 Skipping database user mapping - user entry not found");
            }
        } catch (Exception $e) {
            $this->warn("Warning: Could not create database user mapping: {$e->getMessage()}");
        }
    }

    private function displayDatabaseInfo(array $dbInfo): void
    {
        $this->newLine();
        $this->info('📊 FastPanel Database Information:');
        $this->newLine();

        $rows = [
            ['ID', $dbInfo['id']],
            ['Name', $dbInfo['name']],
            ['Owner ID', $dbInfo['owner_id'] ?? 'Not assigned'],
            ['Owner', $dbInfo['owner']['username'] ?? 'Not assigned'],
            ['Site ID', $dbInfo['site_id'] ?? 'Not linked'],
            ['Site Domain', $dbInfo['site']['domain'] ?? 'Not linked'],
            ['Server', $dbInfo['server']['name'] ?? 'Unknown'],
            ['Size', $this->formatBytes($dbInfo['size'] ?? 0)],
            ['Created', $dbInfo['created_at'] ?? 'Unknown'],
        ];

        $this->table(['Field', 'Value'], $rows);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
