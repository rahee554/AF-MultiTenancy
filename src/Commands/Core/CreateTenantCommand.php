<?php

namespace ArtflowStudio\Tenancy\Commands\Core;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Exception;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create 
                            {--name= : Tenant name}
                            {--domain= : Tenant domain}
                            {--database= : Custom database name}
                            {--status=active : Tenant status}
                            {--homepage : Enable homepage for tenant}
                            {--notes= : Tenant notes}
                            {--force : Force creation without confirmation}';

    protected $description = 'Create a new tenant with localhost or FastPanel integration';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle(): int
    {
        $this->info('🚀 Tenant Creation Wizard');
        $this->newLine();

        // Step 1: Get basic tenant information
        $tenantData = $this->collectTenantData();
        
        // Step 2: Select creation mode
        $mode = $this->selectCreationMode();
        
        // Step 3: Handle creation based on mode
        if ($mode === 'fastpanel') {
            return $this->createWithFastPanel($tenantData);
        } else {
            return $this->createWithLocalhost($tenantData);
        }
    }

    private function collectTenantData(): array
    {
        $name = $this->option('name') ?: $this->ask('🏢 Tenant name');
        $domain = $this->option('domain') ?: $this->ask('🌐 Tenant domain');
        
        $customDb = $this->option('database');
        if (!$customDb) {
            $customDb = $this->ask('💾 Database name (leave empty for auto-generated)', null);
        }
        
        $hasHomepage = $this->option('homepage') || $this->confirm('🏠 Does this tenant have a homepage?', false);
        $status = $this->option('status') ?: 'active';
        $notes = $this->option('notes') ?: $this->ask('📝 Tenant notes (optional)', '');

        if (!$name || !$domain) {
            $this->error('❌ Name and domain are required');
            exit(1);
        }

        return [
            'name' => $name,
            'domain' => $domain,
            'database' => $customDb,
            'homepage' => $hasHomepage,
            'status' => $status,
            'notes' => $notes
        ];
    }

    private function selectCreationMode(): string
    {
        $this->info('🔧 Select creation mode:');
        $this->newLine();

        $modes = [
            'localhost' => '🏠 Localhost (Development) - Create database locally',
            'fastpanel' => '🖥️  FastPanel (Production) - Integrate with FastPanel server'
        ];

        foreach ($modes as $key => $description) {
            $this->line("  {$description}");
        }
        $this->newLine();

        return $this->choice('Select creation mode', array_keys($modes), 'localhost');
    }

    private function createWithFastPanel(array $tenantData): int
    {
        try {
            $this->info('🖥️  Creating tenant with FastPanel integration...');
            $this->newLine();

            // Step 1: Check FastPanel availability
            if (!$this->checkFastPanelAvailability()) {
                $this->error('❌ FastPanel CLI not available. Falling back to localhost mode.');
                return $this->createWithLocalhost($tenantData);
            }

            // Step 2: Check database privileges first
            $dbUser = $this->checkAndSelectDatabaseUser();
            if (!$dbUser) {
                $this->error('❌ No suitable database user found or selected.');
                return 1;
            }

            // Step 3: Get FastPanel users and select owner
            $panelUsers = $this->getFastPanelUsers();
            if (empty($panelUsers)) {
                $this->error('❌ No FastPanel users found.');
                return 1;
            }

            $selectedPanelUser = $this->selectPanelUser($panelUsers);
            
            // Step 4: Optionally select site
            $siteId = $this->selectSite($selectedPanelUser['id']);

            // Step 5: Generate database details
            $dbDetails = $this->generateDatabaseDetails($tenantData['name'], $tenantData['database']);
            
            // Step 6: Create database via FastPanel CLI
            $this->info("🔧 Creating database '{$dbDetails['name']}' via FastPanel...");
            if (!$this->createFastPanelDatabase($dbDetails, $selectedPanelUser)) {
                $this->error('❌ Failed to create database via FastPanel.');
                return 1;
            }

            // Step 7: Sync FastPanel metadata
            $this->syncFastPanelMetadata();

            // Step 8: Assign database user and link to site
            $this->assignDatabaseUserAndSite($dbDetails['name'], $selectedPanelUser['id'], $siteId);

            // Step 9: Create tenant record
            $tenant = $this->createTenantRecord($tenantData, $dbDetails);
            
            // Step 10: Complete setup
            $this->completeTenantSetup($tenant, $dbDetails);

            $this->displayFastPanelSuccessMessage($tenant, $dbDetails, $selectedPanelUser, $siteId);
            return 0;

        } catch (Exception $e) {
            $this->error("❌ Error creating tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function createWithLocalhost(array $tenantData): int
    {
        try {
            $this->info('🏠 Creating tenant in localhost mode...');
            $this->newLine();

            // Step 1: Check database privileges
            $dbUser = $this->checkAndSelectDatabaseUser();
            if (!$dbUser) {
                $this->error('❌ No suitable database user found or selected.');
                return 1;
            }

            // Step 2: Generate database details
            $dbDetails = $this->generateDatabaseDetails($tenantData['name'], $tenantData['database']);
            
            // Step 3: Create database and user
            $this->createLocalDatabase($dbDetails, $dbUser);
            
            // Step 4: Create tenant record
            $tenant = $this->createTenantRecord($tenantData, $dbDetails);
            
            // Step 5: Complete setup
            $this->completeTenantSetup($tenant, $dbDetails);

            $this->displayLocalhostSuccessMessage($tenant, $dbDetails);
            return 0;

        } catch (Exception $e) {
            $this->error("❌ Error creating tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function checkAndSelectDatabaseUser(): ?array
    {
        $this->info('🔍 Checking database privileges...');

        // First, try to use current connection
        $currentUser = $this->getCurrentDatabaseUser();
        if ($this->hasCreateDatabasePrivilege($currentUser)) {
            $this->info("✅ Current user '{$currentUser['user']}' has CREATE DATABASE privilege.");
            return $currentUser;
        }

        $this->warn("⚠️  Current user '{$currentUser['user']}' lacks CREATE DATABASE privilege.");
        $this->newLine();

        // Get all privileged users
        $privilegedUsers = $this->getPrivilegedUsers();
        
        if (empty($privilegedUsers)) {
            $this->error('❌ No users with CREATE DATABASE privilege found.');
            return null;
        }

        return $this->selectPrivilegedUser($privilegedUsers);
    }

    private function getCurrentDatabaseUser(): array
    {
        try {
            $result = DB::select('SELECT USER() as user, CONNECTION_ID() as id');
            $userHost = $result[0]->user;
            [$user, $host] = explode('@', $userHost);
            
            return [
                'user' => $user,
                'host' => $host,
                'connection' => 'mysql'
            ];
        } catch (Exception $e) {
            return [
                'user' => config('database.connections.mysql.username'),
                'host' => 'localhost',
                'connection' => 'mysql'
            ];
        }
    }

    private function hasCreateDatabasePrivilege(array $user): bool
    {
        try {
            // Special case for root user - usually has all privileges
            if ($user['user'] === 'root') {
                return true;
            }

            $grants = DB::select("SHOW GRANTS FOR ?@?", [$user['user'], $user['host']]);
            
            foreach ($grants as $grant) {
                $grantColumn = 'Grants for ' . $user['user'] . '@' . $user['host'];
                $grantLine = $grant->$grantColumn ?? '';
                
                // Check for ALL PRIVILEGES or specific CREATE privileges on *.*
                if (preg_match('/ALL\s+PRIVILEGES\s+ON\s+\*\.\*/i', $grantLine) ||
                    preg_match('/CREATE.*ON\s+\*\.\*/i', $grantLine)) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            // If we can't check, assume we have privileges and let MySQL handle the error
            return true;
        }
    }

    private function getPrivilegedUsers(): array
    {
        try {
            $this->info('🔍 Scanning for users with CREATE DATABASE privilege...');
            
            $users = DB::select("SELECT User, Host FROM mysql.user WHERE User != '' ORDER BY User");
            $privilegedUsers = [];

            foreach ($users as $user) {
                try {
                    // Special case for root
                    if ($user->User === 'root') {
                        $privilegedUsers[] = [
                            'user' => $user->User,
                            'host' => $user->Host,
                            'grants' => 'ALL PRIVILEGES ON *.* (root user)'
                        ];
                        continue;
                    }

                    $grants = DB::select("SHOW GRANTS FOR ?@?", [$user->User, $user->Host]);
                    
                    foreach ($grants as $grant) {
                        $grantColumns = get_object_vars($grant);
                        $grantLine = reset($grantColumns); // Get the first (and usually only) column value
                        
                        if (preg_match('/ALL\s+PRIVILEGES\s+ON\s+\*\.\*/i', $grantLine) ||
                            preg_match('/CREATE.*ON\s+\*\.\*/i', $grantLine)) {
                            $privilegedUsers[] = [
                                'user' => $user->User,
                                'host' => $user->Host,
                                'grants' => $grantLine
                            ];
                            break;
                        }
                    }
                } catch (Exception $e) {
                    // Skip users we can't check
                    continue;
                }
            }

            return $privilegedUsers;
        } catch (Exception $e) {
            $this->warn("Could not scan users: {$e->getMessage()}");
            return [];
        }
    }

    private function selectPrivilegedUser(array $privilegedUsers): ?array
    {
        $this->info('👥 Found users with CREATE DATABASE privilege:');
        $this->newLine();

        $choices = ['manual' => '✏️  Enter credentials manually'];
        
        foreach ($privilegedUsers as $index => $user) {
            $label = "{$user['user']}@{$user['host']}";
            $choices[$index] = $label;
            $this->line("  {$index}: {$label}");
        }

        $this->newLine();
        $selected = $this->choice('Select database user', $choices);

        if ($selected === 'manual') {
            return $this->getManualDatabaseCredentials();
        }

        $selectedUser = $privilegedUsers[$selected];
        
        // Try to get password or assume current connection works
        if ($selectedUser['user'] === $this->getCurrentDatabaseUser()['user']) {
            return [
                'user' => $selectedUser['user'],
                'host' => $selectedUser['host'],
                'connection' => 'mysql'
            ];
        }

        // Ask for password for different user
        $password = $this->secret("Enter password for {$selectedUser['user']}@{$selectedUser['host']}");
        
        return [
            'user' => $selectedUser['user'],
            'host' => $selectedUser['host'],
            'password' => $password,
            'connection' => 'temp_admin'
        ];
    }

    private function getManualDatabaseCredentials(): array
    {
        $this->info('✏️  Enter database credentials manually:');
        
        $user = $this->ask('Database username');
        $password = $this->secret('Database password');
        $host = $this->ask('Database host', 'localhost');

        return [
            'user' => $user,
            'host' => $host,
            'password' => $password,
            'connection' => 'temp_admin'
        ];
    }

    private function checkFastPanelAvailability(): bool
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --version');
            return $result->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    private function getFastPanelUsers(): array
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json users list');
            if (!$result->successful()) {
                return [];
            }

            $users = json_decode($result->output(), true);
            return is_array($users) ? $users : [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function selectPanelUser(array $panelUsers): array
    {
        $this->info('👥 Available FastPanel users:');
        $this->newLine();

        $choices = [];
        foreach ($panelUsers as $user) {
            $label = "{$user['username']} (ID: {$user['id']}) - {$user['email']}";
            $choices[$user['id']] = $label;
            $this->line("  {$user['id']}: {$label}");
        }

        $this->newLine();
        $selectedId = $this->choice('Select FastPanel user for database ownership', $choices);
        
        $selected = collect($panelUsers)->firstWhere('id', (int)$selectedId);
        $this->info("👤 Selected: {$selected['username']} (ID: {$selected['id']})");
        
        return $selected;
    }

    private function selectSite(int $userId): ?int
    {
        try {
            $sites = $this->getFastPanelSites();
            
            if (empty($sites)) {
                $this->comment('ℹ️  No sites available for linking.');
                return null;
            }

            // Filter sites by user if possible
            $userSites = collect($sites)->where('owner_id', $userId)->all();
            
            if (empty($userSites)) {
                $this->comment('ℹ️  No sites found for this user.');
                if ($this->confirm('Would you like to see all sites?', false)) {
                    $userSites = $sites;
                } else {
                    return null;
                }
            }

            $this->info('🌐 Available sites:');
            $this->newLine();

            $choices = ['skip' => '⏭️  Skip - No site assignment'];
            
            foreach ($userSites as $site) {
                $label = "{$site['domain']} (ID: {$site['id']})";
                $choices[$site['id']] = $label;
                $this->line("  {$site['id']}: {$label}");
            }

            $this->newLine();
            $selectedId = $this->choice('Select site to link database (optional)', $choices, 'skip');
            
            return $selectedId === 'skip' ? null : (int)$selectedId;
        } catch (Exception $e) {
            $this->warn("Could not fetch sites: {$e->getMessage()}");
            return null;
        }
    }

    private function getFastPanelSites(): array
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json sites list');
            if (!$result->successful()) {
                return [];
            }

            $sites = json_decode($result->output(), true);
            return is_array($sites) ? $sites : [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function generateDatabaseDetails(string $tenantName, ?string $customDb): array
    {
        $prefix = env('TENANT_DB_PREFIX', 'tenant_');
        
        // Generate database name
        if ($customDb && strtolower($customDb) !== 'null') {
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $customDb);
            $normalized = preg_replace('/_+/', '_', trim($normalized, '_'));
            if (!str_starts_with($normalized, $prefix)) {
                $normalized = $prefix . $normalized;
            }
            $dbName = strtolower($normalized);
        } else {
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $tenantName);
            $normalized = preg_replace('/_+/', '_', trim($normalized, '_'));
            $dbName = strtolower($prefix . $normalized);
        }

        // Generate username and password
        $dbUsername = $dbName . '_user';
        $dbPassword = Str::random(16);

        return [
            'name' => $dbName,
            'username' => $dbUsername,
            'password' => $dbPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];
    }

    private function createFastPanelDatabase(array $dbDetails, array $panelUser): bool
    {
        $serverId = 1; // Default server ID
        
        $command = [
            'sudo', '/usr/local/fastpanel2/fastpanel', 'databases', 'create',
            '--server=' . $serverId,
            '--name=' . $dbDetails['name'],
            '--username=' . $dbDetails['username'],
            '--password=' . $dbDetails['password'],
        ];

        try {
            $result = Process::run(implode(' ', array_map('escapeshellarg', $command)));
            
            if (!$result->successful()) {
                $this->error("FastPanel command failed: {$result->errorOutput()}");
                return false;
            }

            $this->info('✅ Database created successfully via FastPanel');
            return true;
        } catch (Exception $e) {
            $this->error("Failed to create database: {$e->getMessage()}");
            return false;
        }
    }

    private function syncFastPanelMetadata(): void
    {
        $this->info('🔄 Syncing FastPanel metadata...');
        try {
            Process::run('sudo /usr/local/fastpanel2/fastpanel databases sync');
            $this->info('✅ FastPanel metadata synced');
        } catch (Exception $e) {
            $this->warn("Warning: Could not sync FastPanel metadata: {$e->getMessage()}");
        }
    }

    private function assignDatabaseUserAndSite(string $dbName, int $panelUserId, ?int $siteId): void
    {
        $this->info('🔗 Assigning database ownership and site linkage...');
        
        try {
            // Call our sync database command to handle the assignment
            $syncCommand = [
                'fastpanel:sync-database',
                $dbName,
                '--assign-user=' . $panelUserId,
            ];
            
            if ($siteId) {
                $syncCommand[] = '--link-site=' . $siteId;
            }
            
            $this->call($syncCommand[0], array_slice($syncCommand, 1));
            $this->info('✅ Database ownership and site linkage completed');
        } catch (Exception $e) {
            $this->warn("Warning: Could not assign ownership automatically: {$e->getMessage()}");
            $this->comment("💡 You can manually assign ownership later using:");
            $this->comment("   php artisan fastpanel:sync-database {$dbName} --assign-user={$panelUserId}");
        }
    }

    private function createLocalDatabase(array $dbDetails, array $dbUser): void
    {
        $this->info("🔧 Creating database '{$dbDetails['name']}' and user '{$dbDetails['username']}'...");

        try {
            // Use temporary connection if different user
            if (isset($dbUser['password'])) {
                $this->createTemporaryConnection($dbUser);
                $connection = 'temp_admin';
            } else {
                $connection = 'mysql';
            }

            $createDbSql = "CREATE DATABASE `{$dbDetails['name']}` CHARACTER SET {$dbDetails['charset']} COLLATE {$dbDetails['collation']};";
            $createUserSql = "CREATE USER IF NOT EXISTS '{$dbDetails['username']}'@'localhost' IDENTIFIED BY '{$dbDetails['password']}';";
            $grantSql = "GRANT ALL PRIVILEGES ON `{$dbDetails['name']}`.* TO '{$dbDetails['username']}'@'localhost';";
            $flushSql = "FLUSH PRIVILEGES;";

            DB::connection($connection)->unprepared($createDbSql);
            DB::connection($connection)->unprepared($createUserSql);
            DB::connection($connection)->unprepared($grantSql);
            DB::connection($connection)->unprepared($flushSql);
            
            $this->info('✅ Database and user created successfully');
        } catch (Exception $e) {
            throw new Exception("Failed to create database: {$e->getMessage()}");
        }
    }

    private function createTemporaryConnection(array $dbUser): void
    {
        config(['database.connections.temp_admin' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => config('database.connections.mysql.database'),
            'username' => $dbUser['user'],
            'password' => $dbUser['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }

    private function createTenantRecord(array $tenantData, array $dbDetails): Tenant
    {
        return $this->tenantService->createTenant(
            $tenantData['name'],
            $tenantData['domain'],
            $tenantData['status'],
            $dbDetails['name'],
            $tenantData['notes'],
            $tenantData['homepage']
        );
    }

    private function completeTenantSetup(Tenant $tenant, array $dbDetails): void
    {
        $this->info('🔄 Setting up tenant...');

        // Store database credentials securely
        $tenant->update([
            'database_username' => encrypt($dbDetails['username']),
            'database_password' => encrypt($dbDetails['password']),
        ]);

        // Run migrations if requested
        if ($this->confirm('Run migrations for this tenant?', true)) {
            $this->info('🔄 Running tenant migrations...');
            $this->call('tenant:db', [
                'operation' => 'migrate',
                '--tenant' => $tenant->id,
                '--force' => true
            ]);
        }

        // Run seeds if requested
        if ($this->confirm('Run seeders for this tenant?', false)) {
            $this->info('🌱 Running tenant seeders...');
            $this->call('tenant:db', [
                'operation' => 'seed',
                '--tenant' => $tenant->id,
                '--force' => true
            ]);
        }
    }

    private function displayFastPanelSuccessMessage(Tenant $tenant, array $dbDetails, array $panelUser, ?int $siteId): void
    {
        $this->newLine();
        $this->info('🎉 Tenant created successfully with FastPanel integration!');
        $this->newLine();

        $primaryDomain = $tenant->domains()->first();
        $rows = [
            ['🏢 Tenant Name', $tenant->name],
            ['🌐 Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
            ['💾 Database', $dbDetails['name']],
            ['👤 DB Username', $dbDetails['username']],
            ['👥 Panel Owner', $panelUser['username'] . ' (ID: ' . $panelUser['id'] . ')'],
            ['🏠 Homepage', $tenant->hasHomepage() ? 'Enabled' : 'Disabled'],
            ['📊 Status', $tenant->status],
            ['🆔 UUID', $tenant->id],
            ['📅 Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ];

        if ($siteId) {
            $rows[] = ['🌐 Linked Site ID', $siteId];
        }

        $this->table(['Field', 'Value'], $rows);

        $this->newLine();
        $this->comment('💡 Database credentials are encrypted and stored in tenant record');
        $this->comment('🔧 Use php artisan tenant:db for database operations');
        $this->comment('📊 Check FastPanel dashboard for database management');
    }

    private function displayLocalhostSuccessMessage(Tenant $tenant, array $dbDetails): void
    {
        $this->newLine();
        $this->info('🎉 Tenant created successfully in localhost mode!');
        $this->newLine();

        $primaryDomain = $tenant->domains()->first();
        $this->table(['Field', 'Value'], [
            ['🏢 Tenant Name', $tenant->name],
            ['🌐 Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
            ['💾 Database', $dbDetails['name']],
            ['👤 DB Username', $dbDetails['username']],
            ['🏠 Homepage', $tenant->hasHomepage() ? 'Enabled' : 'Disabled'],
            ['📊 Status', $tenant->status],
            ['🆔 UUID', $tenant->id],
            ['📅 Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ]);

        $this->newLine();
        $this->comment('💡 Database credentials are encrypted and stored in tenant record');
        $this->comment('🔧 Use php artisan tenant:db for database operations');
    }
}
