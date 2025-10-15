<?php

namespace ArtflowStudio\Tenancy\Commands\Core;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Exception;

class DeleteTenantCommand extends Command
{
    protected $signature = 'tenant:delete
                            {tenant? : Tenant id, name or domain}
                            {--force : Skip confirmation}
                            {--fastpanel : Force FastPanel deletion if available}
                            {--keep-db : Keep the tenant database (prevents deletion)}';

    protected $description = 'Delete a tenant and its database; uses FastPanel CLI when available';

    public function handle(): int
    {
        // Check if current user has system privileges (sudo/root) BEFORE proceeding
        if (!$this->checkSystemPrivileges()) {
            return 1;
        }

        $tenant = $this->findTenant();
        if (! $tenant) {
            return 1;
        }

        $primaryDomain = $tenant->domains()->first();
        $domainName = $primaryDomain ? $primaryDomain->domain : 'No domain';
        $this->info("Tenant: {$tenant->name} ({$domainName})");

        $keepDbRequested = (bool) $this->option('keep-db');
        $deleteDbRequested = !$keepDbRequested; // Delete DB by default unless --keep-db is passed

        if (! $this->option('force')) {
            $prompt = $deleteDbRequested
                ? 'Delete this tenant and its database? This action is irreversible.'
                : 'Delete this tenant record only? The tenant database will be preserved. This action is irreversible.';

            if (! $this->confirm($prompt, false)) {
                $this->info('Deletion cancelled.');
                return 0;
            }
        }

        // Determine database name
        try {
            $dbName = $tenant->database()->getName();
        } catch (Exception $e) {
            $dbName = $tenant->getDatabaseName() ?? null;
        }

        // Get database user before deletion (for cleanup later)
        $dbUser = null;
        if ($deleteDbRequested && $dbName) {
            try {
                $dbUser = $this->getDatabaseUser($dbName);
            } catch (Exception $e) {
                // Continue without user info if we can't get it
            }
        }

        // Database deletion by default unless --keep-db is passed
        if ($deleteDbRequested) {
            $fastpanelAvailable = $this->checkFastPanelAvailability();
            $useFastpanel = $this->option('fastpanel') || $fastpanelAvailable;

            // If FastPanel is available, try to delete via FastPanel CLI
            if ($useFastpanel && $fastpanelAvailable && $dbName) {
                $this->info("Attempting FastPanel deletion for database '{$dbName}'...");
                try {
                    $deleted = $this->runFastPanelDeleteDatabase($dbName);
                    if ($deleted) {
                        $this->info('✅ FastPanel: database deleted');
                        
                        // Sync FastPanel metadata after deletion
                        $this->syncFastPanelAfterDeletion();
                        
                        // Check and potentially delete MySQL user
                        if ($dbUser) {
                            $this->handleMySQLUserCleanup($dbUser);
                        }
                    } else {
                        $this->warn('FastPanel deletion returned non-success; falling back to local deletion');
                        $this->dropDatabaseLocal($dbName);
                        
                        // Still check for user cleanup after local deletion
                        if ($dbUser) {
                            $this->handleMySQLUserCleanup($dbUser);
                        }
                    }
                } catch (Exception $e) {
                    $this->warn('FastPanel deletion failed: ' . $e->getMessage());
                    $this->info('Falling back to local database deletion...');
                    try {
                        if ($dbName) {
                            $this->dropDatabaseLocal($dbName);
                            
                            // Check for user cleanup after local deletion
                            if ($dbUser) {
                                $this->handleMySQLUserCleanup($dbUser);
                            }
                        }
                    } catch (Exception $ex) {
                        $this->error('Failed to drop database locally: ' . $ex->getMessage());
                    }
                }
            } else {
                // Local deletion
                if ($dbName) {
                    try {
                        $this->info("Dropping local database '{$dbName}'...");
                        $this->dropDatabaseLocal($dbName);
                        
                        // Check for user cleanup after local deletion
                        if ($dbUser) {
                            $this->handleMySQLUserCleanup($dbUser);
                        }
                    } catch (Exception $e) {
                        $this->warn('Local database deletion failed: ' . $e->getMessage());
                    }
                } else {
                    $this->warn('No database name found for tenant; skipping DB deletion');
                }
            }
        } else {
            if ($dbName) {
                $this->info("Database '{$dbName}' preserved (--keep-db was passed)");
            } else {
                $this->info('No database associated with tenant or name not resolvable.');
            }
        }

        // CRITICAL FIX: Clear all tenant-related cache and sessions BEFORE deleting
        $this->clearTenantCacheAndSessions($tenant);

        // Delete tenant record (domains, related models should cascade if set)
        try {
            $tenant->delete();
            $this->info('✅ Tenant deleted successfully from central database');
        } catch (Exception $e) {
            $this->error('Failed to delete tenant record: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Clear all cache and session data related to this tenant
     * This prevents stale authentication data issues
     */
    private function clearTenantCacheAndSessions(Tenant $tenant): void
    {
        $this->info('🧹 Clearing tenant cache and sessions...');
        
        try {
            $tenantId = $tenant->id;
            $domains = $tenant->domains;
            
            // 1. Clear tenant-specific cache
            if (config('cache.default') === 'redis') {
                try {
                    $redis = \Illuminate\Support\Facades\Redis::connection();
                    // Clear all tenant-prefixed keys
                    $pattern = "tenant_{$tenantId}_*";
                    $keys = $redis->keys($pattern);
                    if (!empty($keys)) {
                        $redis->del($keys);
                        $this->line("   ✓ Cleared Redis cache keys: " . count($keys));
                    }
                } catch (Exception $e) {
                    $this->warn("   ⚠ Redis cache clear failed: " . $e->getMessage());
                }
            } else {
                // Database cache driver
                try {
                    DB::table('cache')->where('key', 'like', "tenant_{$tenantId}_%")->delete();
                    DB::table('cache')->where('key', 'like', "laravel_cache:tenant_{$tenantId}_%")->delete();
                    $this->line("   ✓ Cleared database cache");
                } catch (Exception $e) {
                    $this->warn("   ⚠ Database cache clear failed: " . $e->getMessage());
                }
            }
            
            // 2. Clear sessions related to this tenant
            try {
                // Clear database sessions if using database driver
                if (config('session.driver') === 'database') {
                    // Delete sessions that contain this tenant's data
                    $deleted = DB::table(config('session.table', 'sessions'))
                        ->where('payload', 'like', "%{$tenantId}%")
                        ->delete();
                    if ($deleted > 0) {
                        $this->line("   ✓ Cleared {$deleted} tenant session(s)");
                    }
                    
                    // Also clear sessions by domain if we have domains
                    foreach ($domains as $domain) {
                        $deletedByDomain = DB::table(config('session.table', 'sessions'))
                            ->where('payload', 'like', "%{$domain->domain}%")
                            ->delete();
                        if ($deletedByDomain > 0) {
                            $this->line("   ✓ Cleared {$deletedByDomain} session(s) for domain: {$domain->domain}");
                        }
                    }
                }
                
                // Clear file sessions if using file driver
                if (config('session.driver') === 'file') {
                    $sessionPath = storage_path('framework/sessions');
                    if (is_dir($sessionPath)) {
                        $files = glob($sessionPath . '/*');
                        $cleared = 0;
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                $content = file_get_contents($file);
                                if (strpos($content, $tenantId) !== false) {
                                    unlink($file);
                                    $cleared++;
                                }
                            }
                        }
                        if ($cleared > 0) {
                            $this->line("   ✓ Cleared {$cleared} file session(s)");
                        }
                    }
                }
            } catch (Exception $e) {
                $this->warn("   ⚠ Session clear failed: " . $e->getMessage());
            }
            
            // 3. Clear Laravel application cache
            try {
                \Illuminate\Support\Facades\Artisan::call('cache:clear');
                $this->line("   ✓ Application cache cleared");
            } catch (Exception $e) {
                $this->warn("   ⚠ Application cache clear failed: " . $e->getMessage());
            }
            
            // 4. Clear tenant context cache
            try {
                $cacheService = app(\ArtflowStudio\Tenancy\Services\TenantContextCache::class);
                foreach ($domains as $domain) {
                    $cacheService->forget($domain->domain);
                }
                $this->line("   ✓ Tenant context cache cleared");
            } catch (Exception $e) {
                $this->warn("   ⚠ Tenant context cache clear failed: " . $e->getMessage());
            }
            
            $this->info('✅ Cache and session cleanup completed');
            
        } catch (Exception $e) {
            $this->warn("⚠ Overall cache/session cleanup encountered errors: " . $e->getMessage());
            $this->line("   This may cause stale session issues. Users should clear their browser cookies.");
        }
    }

    private function findTenant(): ?Tenant
    {
        $given = $this->argument('tenant');
        if ($given) {
            $tenant = Tenant::where('id', $given)
                ->orWhere('name', $given)
                ->orWhereHas('domains', function ($q) use ($given) {
                    $q->where('domain', $given);
                })->first();
            if (! $tenant) {
                $this->error('Tenant not found: ' . $given);
            }
            return $tenant;
        }

        $tenants = Tenant::orderBy('created_at', 'desc')->get();
        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return null;
        }

        $choices = $tenants->mapWithKeys(fn($t) => [$t->id => $t->name])->toArray();
        $selected = $this->choice('Select tenant to delete', array_values($choices), array_values($choices)[0]);

        // choice returns label, need to map back to id
        $selectedTenant = $tenants->firstWhere('name', $selected) ?? $tenants->firstWhere('id', $selected);
        return $selectedTenant;
    }

    private function checkFastPanelAvailability(): bool
    {
        try {
            if (! file_exists('/usr/local/fastpanel2/fastpanel')) {
                return false;
            }
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel users list --help 2>/dev/null');
            return $result->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    private function runFastPanelDeleteDatabase(string $dbName, int $serverId = 1): bool
    {
        // First check if database exists in FastPanel
        $dbExists = $this->checkFastPanelDatabaseExists($dbName);
        if (!$dbExists) {
            $this->info("Database '{$dbName}' not found in FastPanel - may already be deleted");
            return true; // Consider this successful
        }

        // Since FastPanel CLI might not support direct database deletion,
        // we'll delete the MySQL database directly and then sync FastPanel
        $this->info("Deleting database '{$dbName}' from MySQL and syncing with FastPanel...");
        
        try {
            // Delete the database from MySQL first
            $this->dropDatabaseLocal($dbName);
            $this->info("✅ Database '{$dbName}' deleted from MySQL");
            
            // Sync FastPanel to update its metadata
            $syncResult = Process::run('sudo /usr/local/fastpanel2/fastpanel databases sync --json');
            if ($syncResult->successful()) {
                $this->info("✅ FastPanel metadata synced");
                
                // Verify deletion by checking if database still exists in FastPanel
                $stillExists = $this->checkFastPanelDatabaseExists($dbName);
                if (!$stillExists) {
                    $this->info("✅ Database successfully removed from FastPanel");
                    return true;
                } else {
                    $this->warn("⚠️  Database still appears in FastPanel after sync");
                    return false;
                }
            } else {
                $this->warn("FastPanel sync failed: " . $syncResult->output());
                return false;
            }
            
        } catch (Exception $e) {
            $this->warn("Database deletion failed: " . $e->getMessage());
            return false;
        }
    }

    private function checkFastPanelDatabaseExists(string $dbName): bool
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel databases list --json');
            if ($result->successful()) {
                $databases = json_decode($result->output(), true);
                if (is_array($databases)) {
                    foreach ($databases as $db) {
                        if (isset($db['name']) && $db['name'] === $dbName) {
                            return true;
                        }
                    }
                }
            }
            return false;
        } catch (Exception $e) {
            $this->warn("Could not check FastPanel database existence: " . $e->getMessage());
            return false;
        }
    }

    private function checkFastPanelDatabaseStatus(string $dbName): void
    {
        try {
            $this->info("🔍 Checking FastPanel database status for: {$dbName}");
            
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel databases list --json');
            if ($result->successful()) {
                $databases = json_decode($result->output(), true);
                if (is_array($databases)) {
                    $found = false;
                    foreach ($databases as $db) {
                        if (isset($db['name']) && $db['name'] === $dbName) {
                            $found = true;
                            $this->warn("Database still exists in FastPanel:");
                            $this->table(['Field', 'Value'], [
                                ['ID', $db['id'] ?? 'N/A'],
                                ['Name', $db['name'] ?? 'N/A'],
                                ['Server', $db['server_id'] ?? 'N/A'],
                                ['User', $db['user_id'] ?? 'N/A'],
                                ['Status', $db['status'] ?? 'N/A']
                            ]);
                            break;
                        }
                    }
                    if (!$found) {
                        $this->info("✅ Database not found in FastPanel list - deletion may have succeeded");
                    }
                }
            }
        } catch (Exception $e) {
            $this->warn("Could not check database status: " . $e->getMessage());
        }
    }

    private function dropDatabaseLocal(string $dbName): void
    {
        // Try to use root credentials from env first
        $rootUser = env('DB_ROOT_USERNAME');
        $rootPass = env('DB_ROOT_PASSWORD');

        if ($rootUser && $rootPass) {
            $config = [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host', '127.0.0.1'),
                'port' => config('database.connections.mysql.port', 3306),
                'database' => config('database.connections.mysql.database'),
                'username' => $rootUser,
                'password' => $rootPass,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];
            config(['database.connections._fastpanel_root' => $config]);
            DB::purge('_fastpanel_root');
            DB::connection('_fastpanel_root')->unprepared("DROP DATABASE IF EXISTS `{$dbName}`;");
            return;
        }

        // Fallback to default connection (may fail if user lacks privileges)
        DB::connection()->unprepared("DROP DATABASE IF EXISTS `{$dbName}`;");
    }

    private function getDatabaseUser(string $dbName): ?array
    {
        try {
            // Try to get database user info from MySQL grants
            $grants = DB::select("SELECT DISTINCT grantee FROM information_schema.schema_privileges WHERE table_schema = ?", [$dbName]);
            
            if (!empty($grants)) {
                $grantee = $grants[0]->grantee;
                // Parse grantee format: 'username'@'host'
                $matches = [];
                if (preg_match("/'([^']+)'@'([^']+)'/", $grantee, $matches)) {
                    return [
                        'username' => $matches[1],
                        'host' => $matches[2],
                        'grantee' => $grantee
                    ];
                }
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function syncFastPanelAfterDeletion(): void
    {
        try {
            $this->info('🔄 Syncing FastPanel metadata after deletion...');
            
            // First sync the databases
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel databases sync --json');
            if ($result->successful()) {
                $this->info('✅ FastPanel database metadata synced');
                
                // Wait a moment for sync to complete
                sleep(1);
                
                // List current FastPanel databases to verify deletion
                $this->listFastPanelDatabases();
            } else {
                $this->warn('FastPanel sync failed: ' . $result->output());
            }
        } catch (Exception $e) {
            $this->warn('FastPanel sync error: ' . $e->getMessage());
        }
    }

    private function listFastPanelDatabases(): void
    {
        try {
            $this->info('📊 Current FastPanel databases:');
            
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel databases list --json');
            if ($result->successful()) {
                $output = $result->output();
                $databases = json_decode($output, true);
                
                if (is_array($databases) && !empty($databases)) {
                    $this->table(
                        ['ID', 'Name', 'Server', 'User', 'Created'],
                        array_map(function($db) {
                            return [
                                $db['id'] ?? 'N/A',
                                $db['name'] ?? 'N/A',
                                $db['server_id'] ?? 'N/A',
                                $db['user_id'] ?? 'N/A',
                                isset($db['created_at']) ? date('Y-m-d H:i', strtotime($db['created_at'])) : 'N/A'
                            ];
                        }, $databases)
                    );
                } else {
                    $this->info('No databases found in FastPanel');
                }
            } else {
                $this->warn('Failed to list FastPanel databases: ' . $result->output());
            }
        } catch (Exception $e) {
            $this->warn('Error listing FastPanel databases: ' . $e->getMessage());
        }
    }

    private function handleMySQLUserCleanup(array $dbUser): void
    {
        try {
            $username = $dbUser['username'];
            $host = $dbUser['host'];
            
            // Check if user has any other databases
            $otherDatabases = $this->getUserOtherDatabases($username, $host);
            
            if (empty($otherDatabases)) {
                $this->info("🗑️  MySQL user '{$username}@{$host}' has no remaining databases.");
                
                if ($this->confirm("Delete MySQL user '{$username}@{$host}'?", false)) {
                    $this->deleteMySQLUser($username, $host);
                    $this->info("✅ MySQL user '{$username}@{$host}' deleted");
                } else {
                    $this->info("MySQL user '{$username}@{$host}' preserved");
                }
            } else {
                $this->info("MySQL user '{$username}@{$host}' has " . count($otherDatabases) . " other database(s): " . implode(', ', $otherDatabases));
            }
        } catch (Exception $e) {
            $this->warn('MySQL user cleanup failed: ' . $e->getMessage());
        }
    }

    private function getUserOtherDatabases(string $username, string $host): array
    {
        try {
            $grantee = "'{$username}'@'{$host}'";
            $databases = DB::select("SELECT DISTINCT table_schema FROM information_schema.schema_privileges WHERE grantee = ? AND table_schema NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')", [$grantee]);
            
            return array_column($databases, 'table_schema');
        } catch (Exception $e) {
            return [];
        }
    }

    private function deleteMySQLUser(string $username, string $host): void
    {
        // Try to use root credentials from env first
        $rootUser = env('DB_ROOT_USERNAME');
        $rootPass = env('DB_ROOT_PASSWORD');

        if ($rootUser && $rootPass) {
            $config = [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host', '127.0.0.1'),
                'port' => config('database.connections.mysql.port', 3306),
                'database' => config('database.connections.mysql.database'),
                'username' => $rootUser,
                'password' => $rootPass,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];
            config(['database.connections._fastpanel_root' => $config]);
            DB::purge('_fastpanel_root');
            DB::connection('_fastpanel_root')->unprepared("DROP USER IF EXISTS '{$username}'@'{$host}';");
            DB::connection('_fastpanel_root')->unprepared("FLUSH PRIVILEGES;");
            return;
        }

        // Fallback to default connection (may fail if user lacks privileges)
        DB::connection()->unprepared("DROP USER IF EXISTS '{$username}'@'{$host}';");
        DB::connection()->unprepared("FLUSH PRIVILEGES;");
    }

    private function checkSystemPrivileges(): bool
    {
        $this->info('🔍 Checking system privileges...');
        
        // Get current system user
        $currentSystemUser = $this->getCurrentSystemUser();
        
        // Check if current user is root
        if ($currentSystemUser === 'root') {
            $this->info("✅ Running as root user");
            return true;
        }
        
        // Check if current user has sudo privileges
        if ($this->hasSudoPrivileges()) {
            $this->info("✅ User '{$currentSystemUser}' has sudo privileges");
            return true;
        }
        
        // Check if we can identify users with sudo privileges
        $sudoUsers = $this->getSudoUsers();
        
        $this->error("❌ Current user '{$currentSystemUser}' does not have sufficient system privileges!");
        $this->newLine();
        
        $this->warn('⚠️  This command requires system privileges to:');
        $this->line('   • Delete databases and users');
        $this->line('   • Configure FastPanel (if used)');
        $this->line('   • Manage system resources');
        $this->newLine();
        
        if (!empty($sudoUsers)) {
            $this->comment('💡 Available options:');
            $this->line("   1. Switch to a privileged user:");
            foreach (array_slice($sudoUsers, 0, 3) as $user) {
                $this->line("      su {$user}");
            }
            $recommendedUser = $sudoUsers[0] ?? 'ubuntu';
            $this->newLine();
            $this->comment('💡 Then run the command as:');
            $this->line("   sudo -u {$recommendedUser} php artisan tenant:delete");
        } else {
            $this->comment('💡 Available options:');
            $this->line("   1. Switch to root user: su root");
            $this->line("   2. Use sudo if available: sudo php artisan tenant:delete");
            $this->line("   3. Contact system administrator");
            $this->newLine();
            $this->comment('💡 Or run as privileged user:');
            $this->line("   sudo -u ubuntu php artisan tenant:delete");
        }
        
        $this->newLine();
        $this->comment('🔧 To grant sudo privileges to current user:');
        $this->line("   echo '{$currentSystemUser} ALL=(ALL) NOPASSWD:ALL' | sudo tee /etc/sudoers.d/90-{$currentSystemUser}");
        
        return false;
    }
    
    private function getCurrentSystemUser(): string
    {
        // Try multiple methods to get current user
        $user = posix_getpwuid(posix_geteuid());
        if ($user && isset($user['name'])) {
            return $user['name'];
        }
        
        // Fallback to environment variables
        return $_SERVER['USER'] ?? $_SERVER['USERNAME'] ?? get_current_user() ?? 'unknown';
    }
    
    private function hasSudoPrivileges(): bool
    {
        try {
            $result = shell_exec('sudo -n true 2>/dev/null; echo $?');
            return trim($result) === '0';
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getSudoUsers(): array
    {
        $sudoUsers = [];
        
        try {
            // Check /etc/sudoers.d/ directory for user configurations
            if (is_dir('/etc/sudoers.d/')) {
                $files = scandir('/etc/sudoers.d/');
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    
                    $filePath = "/etc/sudoers.d/{$file}";
                    if (is_file($filePath) && is_readable($filePath)) {
                        $content = file_get_contents($filePath);
                        if (preg_match('/^(\w+)\s+ALL=/', $content, $matches)) {
                            $username = $matches[1];
                            if ($this->isValidSystemUser($username)) {
                                $sudoUsers[] = $username;
                            }
                        }
                    }
                }
            }
            
            // Add common privileged users if they exist
            $commonUsers = ['ubuntu', 'admin', 'root', 'www-data'];
            foreach ($commonUsers as $user) {
                if ($this->isValidSystemUser($user) && !in_array($user, $sudoUsers)) {
                    $sudoUsers[] = $user;
                }
            }
            
            // Remove duplicates and current user if present
            $sudoUsers = array_unique($sudoUsers);
            $currentUser = $this->getCurrentSystemUser();
            $sudoUsers = array_filter($sudoUsers, function($user) use ($currentUser) {
                return $user !== $currentUser;
            });
            
        } catch (Exception $e) {
            // Return common fallback users
            return ['ubuntu', 'root'];
        }
        
        return $sudoUsers;
    }
    
    private function isValidSystemUser(string $username): bool
    {
        try {
            $user = posix_getpwnam($username);
            return $user !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}
