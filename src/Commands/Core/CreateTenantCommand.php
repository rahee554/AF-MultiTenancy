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

        // Step -1: Check if current user has system privileges (sudo/root) BEFORE database checks
        if (!$this->checkSystemPrivileges()) {
            return 1;
        }

        // Step 0: Check database privileges BEFORE asking for tenant details
        $privilegedUser = null;
        $currentUser = $this->getCurrentDatabaseUser();
        $hasCurrent = $this->hasCreateDatabasePrivilege($currentUser);

        $envRootUser = $this->getEnvRootUser();
        $hasEnvRoot = false;
        if ($envRootUser) {
            // Double-check privilege for ENV root user
            $hasEnvRoot = $this->hasCreateDatabasePrivilege($envRootUser);
        }

        if ($hasCurrent) {
            $this->info("✅ Current user '{$currentUser['user']}@{$currentUser['host']}' has CREATE DATABASE privilege.");
            $privilegedUser = $currentUser;
        } elseif ($hasEnvRoot) {
            $this->info("✅ ENV root user '{$envRootUser['user']}@{$envRootUser['host']}' has CREATE DATABASE privilege.");
            $privilegedUser = $envRootUser;
        } else {
            $this->error('❌ Neither the current database user nor the ENV root user has CREATE DATABASE privilege.');
            $this->comment('💡 Please add root credentials to .env (DB_ROOT_USERNAME, DB_ROOT_PASSWORD) or grant CREATE privilege to the current user.');
            $this->comment('   Example: GRANT CREATE ON *.* TO \'' . $currentUser['user'] . '\'@' . $currentUser['host'] . '\'; FLUSH PRIVILEGES;');
            return 1;
        }

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

        // Handle homepage folder validation
        $homepageFolder = null;
        if ($hasHomepage) {
            $homepageFolder = $this->handleHomepageSelection($domain);
        }

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
            'homepage_folder' => $homepageFolder,
            'status' => $status,
            'notes' => $notes
        ];
    }

    private function handleHomepageSelection(string $domain): string
    {
        // Check if domain-based folder exists
        $domainFolder = $this->getDomainFolderName($domain);
        $homepagePath = resource_path("views/tenants/{$domainFolder}");
        $homeBladeFile = "{$homepagePath}/home.blade.php";

        $this->newLine();

        if (file_exists($homepagePath) && is_dir($homepagePath)) {
            // Check if home.blade.php exists inside the folder
            if (file_exists($homeBladeFile)) {
                $this->info("✅ Found existing homepage folder: views/tenants/{$domainFolder}");
                $this->line("   📄 Contains: home.blade.php");

                $choice = $this->choice(
                    '🔧 What would you like to do?',
                    [
                        'use_existing' => '♻️  Use existing homepage folder',
                        'create_new' => '🆕 Create new homepage folder (will rename existing)',
                        'cancel' => '❌ Cancel homepage creation'
                    ],
                    'use_existing'
                );

                if ($choice === 'use_existing') {
                    $this->info("   ✅ Using existing homepage folder: {$domainFolder}");
                    return $domainFolder;
                } elseif ($choice === 'cancel') {
                    $this->warn('   ⚠️  Skipping homepage creation');
                    return 'default'; // Use default folder
                }
                // If create_new, continue to create new folder with timestamp
            } else {
                // Folder exists but no home.blade.php
                $this->warn("⚠️  Folder 'views/tenants/{$domainFolder}' exists but missing home.blade.php");

                if ($this->confirm('Would you like to create home.blade.php in existing folder?', true)) {
                    $this->createHomeBladefile($homeBladeFile, $domain);
                    $this->info("   ✅ Created home.blade.php in existing folder");
                    return $domainFolder;
                }
            }
        }

        // Create new folder
        $finalFolder = $domainFolder;

        // If folder exists but user chose create_new, add timestamp
        if (file_exists($homepagePath)) {
            $finalFolder = $domainFolder . '_' . time();
            $this->info("   📁 Creating new folder: views/tenants/{$finalFolder}");
        } else {
            $this->info("   📁 Creating homepage folder: views/tenants/{$finalFolder}");
        }

        return $finalFolder;
    }

    private function getDomainFolderName(string $domain): string
    {
        // Use exact domain name as folder name
        // tenancy1.local -> tenancy1.local
        // subdomain.example.com -> subdomain.example.com
        return strtolower($domain);
    }

    private function createHomeBladefile(string $filePath, string $domain): void
    {
        $content = <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - {{DOMAIN}}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            text-align: center;
            color: white;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.25rem;
            opacity: 0.9;
        }
        .domain {
            font-weight: bold;
            color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 Welcome to Your Homepage</h1>
        <p>Domain: <span class="domain">{{DOMAIN}}</span></p>
        <p>Tenant homepage is ready!</p>
    </div>
</body>
</html>
BLADE;

        file_put_contents($filePath, str_replace('{{DOMAIN}}', $domain, $content));
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

            // Step 4: Optionally select site - temporary skip for debugging
            $siteId = null; // $this->selectSite($selectedPanelUser['id']);

            // Step 5: Generate database details
            $dbDetails = $this->generateDatabaseDetails($tenantData['name'], $tenantData['database']);

            // If no custom database name, let TenantService auto-generate
            if (!$dbDetails['name']) {
                $this->info("📊 Database name will be auto-generated by TenantService");
            } else {
                $this->info("📊 Database details generated:");
                $this->line("  Name: {$dbDetails['name']}");
            }

            // Step 6: Create database via FastPanel CLI (only if database name is provided)
            if ($dbDetails['name']) {
                $this->info("🔧 Creating database '{$dbDetails['name']}' via FastPanel...");
                if (!$this->createFastPanelDatabase($dbDetails, $selectedPanelUser)) {
                    $this->error('❌ Failed to create database via FastPanel.');
                    return 1;
                }

                // Step 7: Sync FastPanel metadata
                $this->syncFastPanelMetadata();

                // Step 8: Handle FastPanel database user assignment
                $this->handleFastPanelDatabaseAssignment($dbDetails['name'], $selectedPanelUser['id'], $siteId);
                $skipDatabaseCreation = true; // We manually created it via FastPanel
            } else {
                $this->info("🔧 Database will be auto-generated and created by TenantService...");
                $skipDatabaseCreation = false; // Let TenantService auto-generate and create
            }

            // Step 9: Create tenant record
            // If we have root credentials and need to create database, temporarily use them
            if (!$skipDatabaseCreation && isset($dbUser['password'])) {
                $this->useRootConnectionForTenantCreation($dbUser);
            }

            $tenant = $this->createTenantRecord($tenantData, $dbDetails, $skipDatabaseCreation);

            // If database was auto-created by TenantService, get the actual database name
            if (!$dbDetails['name']) {
                $dbDetails['name'] = $tenant->database;
                $this->info("✅ Database auto-generated and created by TenantService: {$dbDetails['name']}");

                // Optionally sync auto-created database to FastPanel
                if ($this->confirm('Sync auto-created database to FastPanel?', true)) {
                    $this->syncFastPanelMetadata();
                    $this->handleFastPanelDatabaseAssignment($dbDetails['name'], $selectedPanelUser['id'], $siteId);
                }
            }

            // Step 10: Complete setup
            $homepageFolder = $tenantData['homepage_folder'] ?? null;
            $this->completeTenantSetup($tenant, $dbDetails, $homepageFolder);

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

            // Step 3: Create database and user (only if custom database name provided)
            if ($dbDetails['name']) {
                // For custom database names, we need to create username/password
                $this->addDatabaseCredentials($dbDetails);
                $this->createLocalDatabase($dbDetails, $dbUser);
                $skipDatabaseCreation = true; // We manually created it
            } else {
                $this->info("🔧 Database will be auto-generated and created...");
                $skipDatabaseCreation = false; // TenantService will auto-generate and create
            }

            // Step 4: Create tenant record
            // If we have root credentials and need to create database, temporarily use them
            if (!$skipDatabaseCreation && isset($dbUser['password'])) {
                $this->useRootConnectionForTenantCreation($dbUser);
            }

            $tenant = $this->createTenantRecord($tenantData, $dbDetails, $skipDatabaseCreation);

            // If database was auto-created, get the actual name
            if (!$dbDetails['name']) {
                $dbDetails['name'] = $tenant->database;
                $this->info("✅ Database auto-created by stancl/tenancy: {$dbDetails['name']}");
            }

            // Step 5: Complete setup
            $homepageFolder = $tenantData['homepage_folder'] ?? null;
            $this->completeTenantSetup($tenant, $dbDetails, $homepageFolder);

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

        // First priority: Check ENV root credentials and use automatically if available
        $envRootUser = $this->getEnvRootUser();
        if ($envRootUser) {
            $this->info('🔐 Using ENV root credentials automatically: ' . $envRootUser['user'] . '@' . $envRootUser['host']);
            return $envRootUser;
        }

        // Second priority: Check if current user has CREATE privileges
        $currentUser = $this->getCurrentDatabaseUser();
        if ($this->hasCreateDatabasePrivilege($currentUser)) {
            $this->info("✅ Current user '{$currentUser['user']}@{$currentUser['host']}' has CREATE DATABASE privilege.");
            if ($this->confirm('Use current database connection?', true)) {
                return $currentUser;
            }
        } else {
            $this->warn("⚠️  Current user '{$currentUser['user']}@{$currentUser['host']}' lacks CREATE DATABASE privilege.");
        }

        // Last resort: Get all privileged users and let user select
        $privilegedUsers = $this->getPrivilegedUsers();

        if (empty($privilegedUsers)) {
            $this->error('❌ No users with CREATE DATABASE privilege found.');
            $this->comment('💡 Available options:');
            $this->comment('   1. Add root credentials to .env: DB_ROOT_USERNAME=root DB_ROOT_PASSWORD=your_password');
            $this->comment('   2. Grant CREATE privilege to current user: GRANT CREATE ON *.* TO \'al_emaan_pk\'@\'localhost\';');
            $this->comment('   3. Create a dedicated tenant_admin user with CREATE privileges');
            return null;
        }

        $this->newLine();
        $this->info('👥 Users with CREATE DATABASE privilege:');
        $this->newLine();

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

            $grants = DB::select("SHOW GRANTS FOR '{$user['user']}'@'{$user['host']}'");

            foreach ($grants as $grant) {
                $grantColumn = 'Grants for ' . $user['user'] . '@' . $user['host'];
                $grantLine = $grant->$grantColumn ?? '';

                // Check for ALL PRIVILEGES or specific CREATE privileges on *.*
                if (
                    preg_match('/ALL\s+PRIVILEGES\s+ON\s+\*\.\*/i', $grantLine) ||
                    preg_match('/GRANT\s+.*CREATE.*ON\s+\*\.\*/i', $grantLine) ||
                    preg_match('/GRANT\s+ALL.*ON\s+\*\.\*/i', $grantLine)
                ) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            // If we can't check, return false to force user selection
            $this->warn("Could not verify privileges for {$user['user']}: {$e->getMessage()}");
            return false;
        }
    }

    private function getPrivilegedUsers(): array
    {
        try {
            $this->info('🔍 Scanning for users with CREATE DATABASE privilege...');

            $privilegedUsers = [];

            // Try to scan mysql.user table first
            try {
                $users = DB::select("SELECT User, Host FROM mysql.user WHERE User != '' AND User != 'mysql.session' AND User != 'mysql.sys' ORDER BY User");

                foreach ($users as $user) {
                    try {
                        // Special case for root
                        if ($user->User === 'root') {
                            $privilegedUsers[] = [
                                'user' => $user->User,
                                'host' => $user->Host,
                                'grants' => 'ALL PRIVILEGES ON *.* (root user)',
                                'type' => 'root'
                            ];
                            continue;
                        }

                        // Special case for debian-sys-maint (often has full privileges)
                        if ($user->User === 'debian-sys-maint') {
                            $privilegedUsers[] = [
                                'user' => $user->User,
                                'host' => $user->Host,
                                'grants' => 'ALL PRIVILEGES ON *.* (system user)',
                                'type' => 'system'
                            ];
                            continue;
                        }

                        $grants = DB::select("SHOW GRANTS FOR '{$user->User}'@'{$user->Host}'");

                        foreach ($grants as $grant) {
                            $grantColumns = get_object_vars($grant);
                            $grantLine = reset($grantColumns); // Get the first (and usually only) column value

                            if (
                                preg_match('/ALL\s+PRIVILEGES\s+ON\s+\*\.\*/i', $grantLine) ||
                                preg_match('/GRANT\s+.*CREATE.*ON\s+\*\.\*/i', $grantLine) ||
                                preg_match('/GRANT\s+ALL.*ON\s+\*\.\*/i', $grantLine)
                            ) {
                                $privilegedUsers[] = [
                                    'user' => $user->User,
                                    'host' => $user->Host,
                                    'grants' => $grantLine,
                                    'type' => 'user'
                                ];
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        // Skip users we can't check (might be system users or no access)
                        continue;
                    }
                }
            } catch (Exception $e) {
                $this->warn("Cannot scan mysql.user table: {$e->getMessage()}");
                $this->comment("💡 This is normal if current user lacks mysql.user access");

                // Fallback: provide common privileged user options
                $this->info("🔧 Providing common privileged user options...");
                $privilegedUsers = [
                    [
                        'user' => 'root',
                        'host' => 'localhost',
                        'grants' => 'ALL PRIVILEGES ON *.* (assumed)',
                        'type' => 'root'
                    ],
                    [
                        'user' => 'debian-sys-maint',
                        'host' => 'localhost',
                        'grants' => 'ALL PRIVILEGES ON *.* (assumed)',
                        'type' => 'system'
                    ]
                ];
            }

            // Sort by type: root first, then system, then regular users
            usort($privilegedUsers, function ($a, $b) {
                $order = ['root' => 0, 'system' => 1, 'user' => 2];
                return ($order[$a['type']] ?? 3) <=> ($order[$b['type']] ?? 3);
            });

            return $privilegedUsers;
        } catch (Exception $e) {
            $this->warn("Could not scan users: {$e->getMessage()}");

            // Ultimate fallback: return common options
            return [
                [
                    'user' => 'root',
                    'host' => 'localhost',
                    'grants' => 'ALL PRIVILEGES ON *.* (assumed)',
                    'type' => 'root'
                ]
            ];
        }
    }

    private function selectPrivilegedUser(array $privilegedUsers): ?array
    {
        $choices = ['manual' => '✏️  Enter credentials manually'];

        foreach ($privilegedUsers as $index => $user) {
            $typeIcon = match ($user['type']) {
                'env_root' => '🔑🌟 [ENV]',
                'root' => '🔑',
                'system' => '⚙️',
                default => '👤'
            };

            $passwordHint = match ($user['type']) {
                'env_root' => '',
                'root' => ' {Password Required}',
                'system' => ' {Password Required}',
                default => ' {Password Required}'
            };

            $label = "{$typeIcon} {$user['user']}@{$user['host']}{$passwordHint}";
            $choices[$index] = $label;

            // Truncate grants for display
            $grantsDisplay = strlen($user['grants']) > 60 ? substr($user['grants'], 0, 60) . '...' : $user['grants'];
            $this->line("  <info>{$index}</info>: {$label}");
            $this->line("      <comment>{$grantsDisplay}</comment>");
        }

        $this->newLine();
        $this->comment('💡 🌟 = ENV credentials (recommended for production)');
        $this->newLine();

        $selected = $this->choice('Select database user for tenant creation', $choices);

        if ($selected === 'manual') {
            return $this->getManualDatabaseCredentials();
        }

        $selectedUser = $privilegedUsers[$selected];

        // Check if this is current user
        $currentUser = $this->getCurrentDatabaseUser();
        if ($selectedUser['user'] === $currentUser['user'] && $selectedUser['host'] === $currentUser['host']) {
            return [
                'user' => $selectedUser['user'],
                'host' => $selectedUser['host'],
                'connection' => 'mysql',
                'type' => $selectedUser['type']
            ];
        }

        // For ENV root user, use stored password
        if ($selectedUser['type'] === 'env_root' && isset($selectedUser['password'])) {
            $this->info("🔐 Using ENV root credentials: {$selectedUser['user']}@{$selectedUser['host']}");
            return [
                'user' => $selectedUser['user'],
                'host' => $selectedUser['host'],
                'password' => $selectedUser['password'],
                'connection' => 'temp_admin',
                'type' => $selectedUser['type']
            ];
        }

        // Ask for password for other users
        $this->info("🔐 Selected: {$selectedUser['user']}@{$selectedUser['host']}");

        if ($selectedUser['type'] === 'root') {
            $this->comment('💡 Tip: For security, enter the actual MySQL root password.');
            $this->comment('💡 Alternative: Consider granting CREATE privilege to current user instead:');
            $this->comment("     GRANT CREATE ON *.* TO 'al_emaan_pk'@'localhost'; FLUSH PRIVILEGES;");
        }

        $password = $this->secret("Enter password for {$selectedUser['user']}@{$selectedUser['host']}");

        // Test the connection before returning
        if (!$this->testDatabaseConnection($selectedUser['user'], $selectedUser['host'], $password ?: null)) {
            $this->error('❌ Failed to connect with provided credentials');

            if ($selectedUser['type'] === 'root') {
                $this->comment('💡 Root authentication often uses socket auth which requires different setup.');
                $this->comment('💡 Recommended alternatives:');
                $this->comment('   1. Add root credentials to .env file (DB_ROOT_USERNAME, DB_ROOT_PASSWORD)');
                $this->comment('   2. Use manual credentials for a user with CREATE privileges');
                $this->comment("   3. Grant privileges: GRANT CREATE ON *.* TO 'al_emaan_pk'@'localhost';");
            }

            return null;
        }

        return [
            'user' => $selectedUser['user'],
            'host' => $selectedUser['host'],
            'password' => $password ?: null,
            'connection' => 'temp_admin',
            'type' => $selectedUser['type']
        ];
    }

    private function getManualDatabaseCredentials(): ?array
    {
        $this->info('✏️  Enter database credentials manually:');

        $user = $this->ask('Database username');
        $password = $this->secret('Database password');
        $host = $this->ask('Database host', 'localhost');

        // Test the connection
        if (!$this->testDatabaseConnection($user, $host, $password)) {
            $this->error('❌ Failed to connect with provided credentials');
            return null;
        }

        return [
            'user' => $user,
            'host' => $host,
            'password' => $password,
            'connection' => 'temp_admin',
            'type' => 'manual'
        ];
    }

    private function testDatabaseConnection(string $user, string $host, ?string $password): bool
    {
        try {
            $testConfig = [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => config('database.connections.mysql.database'),
                'username' => $user,
                'password' => $password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];

            config(['database.connections.test_connection' => $testConfig]);

            // Try to connect and run a simple query
            $result = DB::connection('test_connection')->select('SELECT 1 as test');

            return !empty($result);
        } catch (Exception $e) {
            // For root user, try alternative methods if password auth fails
            if ($user === 'root' && $password === null) {
                $this->comment("💡 Password auth failed for root. For security, this command requires explicit credentials.");
                $this->comment("💡 Alternative: Grant CREATE privilege to al_emaan_pk user instead.");
                return false;
            }

            $this->warn("Connection test failed: {$e->getMessage()}");
            return false;
        }
    }

    private function getEnvRootUser(): ?array
    {
        $rootUser = env('DB_ROOT_USERNAME');
        $rootPassword = env('DB_ROOT_PASSWORD');

        if ($rootUser && $rootPassword) {
            // Test the connection first
            if ($this->testDatabaseConnection($rootUser, 'localhost', $rootPassword)) {
                return [
                    'user' => $rootUser,
                    'host' => 'localhost',
                    'password' => $rootPassword,
                    'grants' => 'ALL PRIVILEGES ON *.* (from ENV)',
                    'type' => 'env_root'
                ];
            } else {
                $this->warn("ENV root credentials found but connection test failed");
            }
        }

        return null;
    }

    private function checkSystemPrivileges(): bool
    {
        $this->info('🔍 Checking system privileges...');

        // On Windows, system privilege checks don't apply (no sudo/root concept)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->info("✅ Running on Windows - skipping Unix-style privilege checks");
            return true;
        }

        // Get current system user
        $currentSystemUser = $this->getCurrentSystemUser();

        // Check if current user is root
        if ($currentSystemUser === 'root') {
            $this->info("✅ Running as root user - full system privileges available");
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
        $this->line('   • Create databases and users');
        $this->line('   • Configure FastPanel (if used)');
        $this->line('   • Manage system resources');
        $this->newLine();

        if (!empty($sudoUsers)) {
            $this->comment('💡 Users with sudo privileges found:');
            foreach ($sudoUsers as $user) {
                $this->line("   • {$user}");
            }
            $this->newLine();

            // Suggest switching to a privileged user
            $recommendedUser = $sudoUsers[0]; // First user from the list
            $this->comment('📋 To run this command with proper privileges:');
            $this->line("   su {$recommendedUser}");
            $this->line("   # Or using sudo:");
            $this->line("   sudo -u {$recommendedUser} php artisan tenant:create");
        } else {
            $this->comment('💡 No sudo users detected. Common privileged users:');
            $this->line('   • root');
            $this->line('   • xuser (if configured with sudo)');
            $this->line('   • fastuser (if configured with sudo)');
            $this->newLine();
            $this->comment('📋 To run this command:');
            $this->line('   su root');
            $this->line('   # Or if you have a user with sudo:');
            $this->line('   su xuser');
        }

        $this->newLine();
        $this->comment('🔧 To grant sudo privileges to current user:');
        $this->line("   echo '{$currentSystemUser} ALL=(ALL) NOPASSWD:ALL' | sudo tee /etc/sudoers.d/90-{$currentSystemUser}");

        return false;
    }

    private function getCurrentSystemUser(): string
    {
        // Try multiple methods to get current user
        // Check if POSIX functions are available (Linux/Mac only)
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $user = posix_getpwuid(posix_geteuid());
            if ($user && isset($user['name'])) {
                return $user['name'];
            }
        }

        // Fallback to environment variables (works on Windows and Unix)
        return $_SERVER['USER'] ?? $_SERVER['USERNAME'] ?? get_current_user() ?? 'unknown';
    }

    private function hasSudoPrivileges(): bool
    {
        try {
            // Test if current user can run sudo commands
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
            // Check /etc/sudoers.d/ directory for user-specific sudo files
            if (is_dir('/etc/sudoers.d')) {
                $sudoFiles = glob('/etc/sudoers.d/*');
                foreach ($sudoFiles as $file) {
                    if (is_file($file) && is_readable($file)) {
                        $filename = basename($file);
                        // Common pattern: /etc/sudoers.d/90-username
                        if (preg_match('/^\d*-?(.+)$/', $filename, $matches)) {
                            $potentialUser = $matches[1];
                            if ($this->isValidSystemUser($potentialUser)) {
                                $sudoUsers[] = $potentialUser;
                            }
                        }
                    }
                }
            }

            // Also check for common privileged users
            $commonUsers = ['root', 'xuser', 'fastuser', 'admin', 'ubuntu'];
            foreach ($commonUsers as $user) {
                if ($this->isValidSystemUser($user) && !in_array($user, $sudoUsers)) {
                    // Check if user is in sudo group
                    $groups = shell_exec("groups {$user} 2>/dev/null");
                    if ($groups && (strpos($groups, 'sudo') !== false || strpos($groups, 'wheel') !== false)) {
                        $sudoUsers[] = $user;
                    }
                }
            }

            // Remove duplicates and sort
            $sudoUsers = array_unique($sudoUsers);
            sort($sudoUsers);

        } catch (Exception $e) {
            // If we can't determine sudo users, return common defaults
            return ['root', 'xuser'];
        }

        return $sudoUsers;
    }

    private function isValidSystemUser(string $username): bool
    {
        try {
            // Check if POSIX functions are available (Linux/Mac only)
            if (function_exists('posix_getpwnam')) {
                $user = posix_getpwnam($username);
                return $user !== false;
            }

            // On Windows, we can't validate system users with posix functions
            // So we return true if username looks reasonable (not empty and not system reserved)
            $reservedNames = ['con', 'prn', 'aux', 'nul', 'com1', 'lpt1'];
            return !empty($username) && !in_array(strtolower($username), $reservedNames);
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkFastPanelAvailability(): bool
    {
        try {
            // Check if FastPanel binary exists
            if (!file_exists('/usr/local/fastpanel2/fastpanel')) {
                $this->comment('💡 FastPanel binary not found at /usr/local/fastpanel2/fastpanel');
                return false;
            }

            // Test if we can run FastPanel commands (try users list as it's simple and safe)
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel users list --help 2>/dev/null');
            if (!$result->successful()) {
                // Try a different approach - check if we can at least get help
                $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --help 2>/dev/null | head -1');
                if (!$result->successful()) {
                    $this->comment('💡 FastPanel CLI not accessible via sudo');
                    return false;
                }
            }

            $this->info('✅ FastPanel CLI is available');
            return true;
        } catch (Exception $e) {
            $this->comment("💡 FastPanel availability check failed: {$e->getMessage()}");
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
            $email = $user['restore_email'] ?? $user['email'] ?? 'no-email';
            $roles = implode(', ', $user['roles'] ?? []);
            $label = "{$user['username']} (ID: {$user['id']}) - {$email} [{$roles}]";
            $choices[] = $label;
            $this->line("  {$user['id']}: {$label}");
        }

        $this->newLine();
        $selectedIndex = $this->choice('Select FastPanel user for database ownership', $choices);

        // Find the selected user by matching the label
        $selectedUser = null;
        foreach ($panelUsers as $index => $user) {
            $email = $user['restore_email'] ?? $user['email'] ?? 'no-email';
            $roles = implode(', ', $user['roles'] ?? []);
            $label = "{$user['username']} (ID: {$user['id']}) - {$email} [{$roles}]";
            if ($label === $selectedIndex) {
                $selectedUser = $user;
                break;
            }
        }

        if (!$selectedUser) {
            throw new Exception("Could not find selected user");
        }

        $email = $selectedUser['restore_email'] ?? $selectedUser['email'] ?? 'no-email';
        $this->info("👤 Selected: {$selectedUser['username']} (ID: {$selectedUser['id']}) - {$email}");

        return $selectedUser;
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

            return $selectedId === 'skip' ? null : (int) $selectedId;
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
        // Generate database name - use stancl/tenancy default behavior when no custom name
        if ($customDb && !empty(trim($customDb))) {
            // Custom database name provided
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $customDb);
            $normalized = preg_replace('/_+/', '_', trim($normalized, '_'));
            $baseName = strtolower($normalized);

            // Apply TENANT_DB_PREFIX from environment/config
            $prefix = config('tenancy.database.prefix', env('TENANT_DB_PREFIX', 'tenant_'));
            // Ensure prefix ends with an underscore when using config value as well
            if ($prefix !== '' && !str_ends_with($prefix, '_')) {
                $prefix .= '_';
            }

            // Check if prefix is already applied
            if (!str_starts_with($baseName, $prefix)) {
                $dbName = $prefix . $baseName;
            } else {
                $dbName = $baseName;
            }
        } else {
            // Use stancl/tenancy default: tenant_ + UUID (without hyphens)
            // This will be auto-generated by stancl/tenancy, we just need to return null
            $dbName = null; // Let stancl/tenancy handle auto-generation
        }

        return [
            'name' => $dbName,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];
    }

    private function createFastPanelDatabase(array $dbDetails, array $panelUser): bool
    {
        $serverId = 1; // Default server ID

        // Ask user about MySQL user creation
        $this->info('🔗 Database MySQL User Options:');
        $createUser = $this->choice('How would you like to handle MySQL database user?', [
            '⏭️  No MySQL user needed (database only)',
            '👤 Use existing MySQL user',
            '✨ Create new MySQL user for this database'
        ], 1); // Default to existing (index 1)

        if ($createUser === '⏭️  No MySQL user needed (database only)') {
            // Create database only, no MySQL user
            return $this->createFastPanelDatabaseOnly($dbDetails['name'], $serverId);
        } elseif ($createUser === '👤 Use existing MySQL user') {
            // Let user select existing MySQL user
            $existingUser = $this->selectExistingMySQLUser();
            if (!$existingUser) {
                $this->warn('⚠️  No MySQL user selected, creating database only');
                return $this->createFastPanelDatabaseOnly($dbDetails['name'], $serverId);
            }
            return $this->createFastPanelDatabaseWithUser($dbDetails['name'], $serverId, $existingUser['login'], null);
        } else {
            // Create new MySQL user
            $newUser = $this->getNewMySQLUserDetails($dbDetails['name']);
            if (!$newUser) {
                $this->warn('⚠️  User creation cancelled, creating database only');
                return $this->createFastPanelDatabaseOnly($dbDetails['name'], $serverId);
            }
            return $this->createFastPanelDatabaseWithUser($dbDetails['name'], $serverId, $newUser['username'], $newUser['password']);
        }
    }

    private function createFastPanelDatabaseOnly(string $dbName, int $serverId): bool
    {
        $command = [
            'sudo',
            '/usr/local/fastpanel2/fastpanel',
            'databases',
            'create',
            '--server=' . $serverId,
            '--name=' . $dbName,
        ];

        try {
            $result = Process::run(implode(' ', array_map('escapeshellarg', $command)));

            if (!$result->successful()) {
                $this->error("FastPanel command failed: {$result->errorOutput()}");
                return false;
            }

            $this->info('✅ Database created successfully via FastPanel (database only)');
            return true;
        } catch (Exception $e) {
            $this->error("Failed to create database: {$e->getMessage()}");
            return false;
        }
    }

    private function createFastPanelDatabaseWithUser(string $dbName, int $serverId, string $username, ?string $password): bool
    {
        $command = [
            'sudo',
            '/usr/local/fastpanel2/fastpanel',
            'databases',
            'create',
            '--server=' . $serverId,
            '--name=' . $dbName,
            '--username=' . $username,
        ];

        if ($password) {
            $command[] = '--password=' . $password;
        }

        try {
            $result = Process::run(implode(' ', array_map('escapeshellarg', $command)));

            if (!$result->successful()) {
                $this->error("FastPanel command failed: {$result->errorOutput()}");
                return false;
            }

            $this->info("✅ Database created successfully via FastPanel with MySQL user: {$username}");
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
            // Build command arguments
            $arguments = [
                'database' => $dbName,
                '--assign-user' => $panelUserId,
                '--create-mapping' => true,
            ];

            if ($siteId) {
                $arguments['--link-site'] = $siteId;
            }

            // Call our sync database command
            $exitCode = $this->call('fastpanel:sync-database', $arguments);

            if ($exitCode === 0) {
                $this->info('✅ Database ownership and site linkage completed');
            } else {
                throw new Exception('Sync command returned non-zero exit code');
            }
        } catch (Exception $e) {
            $this->warn("Warning: Could not assign ownership automatically: {$e->getMessage()}");
            $this->comment("💡 You can manually assign ownership later using:");
            $this->comment("   php artisan fastpanel:sync-database {$dbName} --assign-user={$panelUserId}");

            if ($siteId) {
                $this->comment("   php artisan fastpanel:sync-database {$dbName} --link-site={$siteId}");
            }
        }
    }

    private function handleFastPanelDatabaseAssignment(string $dbName, int $panelUserId, ?int $siteId): void
    {
        $this->info('🔗 Setting up FastPanel database assignment...');

        try {
            // Step 1: Get available database users from FastPanel
            $databaseUsers = $this->getFastPanelDatabaseUsers();

            if (empty($databaseUsers)) {
                $this->warn('⚠️  No database users found in FastPanel');
                return;
            }

            // Step 2: Select database user to assign to this database
            $selectedDbUser = $this->selectDatabaseUserForAssignment($databaseUsers, $dbName);

            if (!$selectedDbUser) {
                $this->warn('⚠️  No database user selected for assignment');
                return;
            }

            // Step 3: Assign database to the selected user and panel owner
            $this->assignDatabaseToUserAndOwner($dbName, $selectedDbUser, $panelUserId, $siteId);

        } catch (Exception $e) {
            $this->warn("Warning: Could not complete FastPanel database assignment: {$e->getMessage()}");
            $this->comment("💡 You can manually assign later using:");
            $this->comment("   php artisan fastpanel:sync-database {$dbName} --assign-user={$panelUserId}");
        }
    }

    private function getFastPanelDatabaseUsers(): array
    {
        try {
            // Query FastPanel SQLite database for database users
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';

            $query = "SELECT id, login, owner_id, created_at FROM database_user ORDER BY login";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$query}\"");

            if (!$result->successful()) {
                return [];
            }

            $users = [];
            $lines = array_filter(explode("\n", trim($result->output())));

            foreach ($lines as $line) {
                $parts = explode('|', $line);
                if (count($parts) >= 4) {
                    $users[] = [
                        'id' => (int) $parts[0],
                        'login' => $parts[1],
                        'owner_id' => (int) $parts[2],
                        'created_at' => $parts[3]
                    ];
                }
            }

            return $users;
        } catch (Exception $e) {
            $this->warn("Could not fetch FastPanel database users: {$e->getMessage()}");
            return [];
        }
    }

    private function selectDatabaseUserForAssignment(array $databaseUsers, string $dbName): ?array
    {
        $this->info('👤 Available FastPanel Database Users:');
        $this->newLine();

        $choices = ['skip' => '⏭️  Skip database user assignment'];

        foreach ($databaseUsers as $index => $user) {
            $label = "{$user['login']} (ID: {$user['id']}) - Owner: {$user['owner_id']}";
            $choices[$index] = $label;
            $this->line("  <info>{$index}</info>: {$label}");
        }

        $this->newLine();
        $this->comment("💡 Select a database user to assign to database '{$dbName}'");
        $this->comment("💡 This creates the connection between the database and the MySQL login");
        $this->newLine();

        $selected = $this->choice('Select database user for assignment', $choices, 'skip');

        if ($selected === 'skip') {
            return null;
        }

        $selectedUser = $databaseUsers[$selected];
        $this->info("👤 Selected database user: {$selectedUser['login']} (ID: {$selectedUser['id']})");

        return $selectedUser;
    }

    private function assignDatabaseToUserAndOwner(string $dbName, array $dbUser, int $panelUserId, ?int $siteId): void
    {
        try {
            $this->info("🔗 Assigning database '{$dbName}' to user '{$dbUser['login']}'...");

            // Get the database info from FastPanel
            $dbInfo = $this->getFastPanelDatabaseByName($dbName);
            if (!$dbInfo) {
                $this->warn("Could not find database '{$dbName}' in FastPanel");
                return;
            }

            // Create the database-user mapping directly
            $this->createDatabaseUserMapping($dbName, $dbUser['id']);

            // Update database ownership if needed
            $this->updateDatabaseOwnership($dbInfo['id'], $panelUserId);

            // Grant MySQL privileges to the database user
            $this->grantMySQLPrivileges($dbName, $dbUser['login']);

            // Link to site if provided
            if ($siteId) {
                $this->linkDatabaseToSite($dbInfo['id'], $siteId);
            }

            $this->info('✅ Database assignment completed successfully');

        } catch (Exception $e) {
            $this->warn("Could not complete assignment: {$e->getMessage()}");
            $this->comment("💡 Database has been created successfully in FastPanel");
            $this->comment("💡 You can assign ownership manually through the FastPanel interface");
        }
    }

    private function createDatabaseUserMapping(string $dbName, int $dbUserId): void
    {
        try {
            // Get the database ID from FastPanel
            $dbInfo = $this->getFastPanelDatabaseByName($dbName);
            if (!$dbInfo) {
                $this->warn("Could not find database '{$dbName}' in FastPanel");
                return;
            }

            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';

            // Check if mapping already exists
            $checkQuery = "SELECT id FROM datbases_users WHERE user_id = {$dbUserId} AND database_id = {$dbInfo['id']}";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$checkQuery}\"");

            if ($result->successful() && !empty(trim($result->output()))) {
                $this->info("✅ Database user mapping already exists");
                return;
            }

            // Create the mapping
            $insertQuery = "INSERT INTO datbases_users (user_id, database_id) VALUES ({$dbUserId}, {$dbInfo['id']});";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$insertQuery}\"");

            if ($result->successful()) {
                $this->info("✅ Database user mapping created successfully");
            } else {
                $this->warn("Could not create database user mapping: {$result->errorOutput()}");
            }
        } catch (Exception $e) {
            $this->warn("Could not create database user mapping: {$e->getMessage()}");
        }
    }

    private function getFastPanelDatabaseByName(string $dbName): ?array
    {
        try {
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';

            $query = "SELECT id, name, owner_id, site_id FROM db WHERE name = '{$dbName}' LIMIT 1";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$query}\"");

            if ($result->successful() && !empty(trim($result->output()))) {
                $parts = explode('|', trim($result->output()));
                if (count($parts) >= 3) {
                    return [
                        'id' => (int) $parts[0],
                        'name' => $parts[1],
                        'owner_id' => (int) $parts[2],
                        'site_id' => isset($parts[3]) ? (int) $parts[3] : null
                    ];
                }
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function updateDatabaseOwnership(int $dbId, int $panelUserId): void
    {
        try {
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';

            $updateQuery = "UPDATE db SET owner_id = {$panelUserId} WHERE id = {$dbId};";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$updateQuery}\"");

            if ($result->successful()) {
                $this->info("✅ Database ownership updated to user ID: {$panelUserId}");
            } else {
                $this->warn("Could not update database ownership: {$result->errorOutput()}");
            }
        } catch (Exception $e) {
            $this->warn("Could not update database ownership: {$e->getMessage()}");
        }
    }

    private function linkDatabaseToSite(int $dbId, int $siteId): void
    {
        try {
            $sqliteDb = '/usr/local/fastpanel2/app/db/fastpanel2.db';

            $updateQuery = "UPDATE db SET site_id = {$siteId} WHERE id = {$dbId};";
            $result = Process::run("sudo sqlite3 {$sqliteDb} \"{$updateQuery}\"");

            if ($result->successful()) {
                $this->info("✅ Database linked to site ID: {$siteId}");
            } else {
                $this->warn("Could not link database to site: {$result->errorOutput()}");
            }
        } catch (Exception $e) {
            $this->warn("Could not link database to site: {$e->getMessage()}");
        }
    }

    private function createLocalDatabase(array $dbDetails, array $dbUser): void
    {
        $this->info("🔧 Creating database '{$dbDetails['name']}' and user '{$dbDetails['username']}'...");

        try {
            // Use temporary connection if different user
            if (isset($dbUser['password']) && $dbUser['connection'] === 'temp_admin') {
                $this->createTemporaryConnection($dbUser);
                $connection = 'temp_admin';
            } else {
                $connection = 'mysql';
            }

            // Check if database already exists
            $existingDbs = DB::connection($connection)->select("SHOW DATABASES LIKE '{$dbDetails['name']}'");
            if (!empty($existingDbs)) {
                if (!$this->confirm("Database '{$dbDetails['name']}' already exists. Drop and recreate?", false)) {
                    throw new Exception("Database '{$dbDetails['name']}' already exists");
                }

                $this->info("🗑️  Dropping existing database '{$dbDetails['name']}'...");
                DB::connection($connection)->unprepared("DROP DATABASE `{$dbDetails['name']}`;");
            }

            // Create database
            $this->info("📊 Creating database '{$dbDetails['name']}'...");
            $createDbSql = "CREATE DATABASE `{$dbDetails['name']}` CHARACTER SET {$dbDetails['charset']} COLLATE {$dbDetails['collation']};";
            DB::connection($connection)->unprepared($createDbSql);

            // Create user
            $this->info("👤 Creating user '{$dbDetails['username']}'...");
            $createUserSql = "CREATE USER IF NOT EXISTS '{$dbDetails['username']}'@'localhost' IDENTIFIED BY '{$dbDetails['password']}';";
            DB::connection($connection)->unprepared($createUserSql);

            // Grant privileges
            $this->info("🔐 Granting privileges...");
            $grantSql = "GRANT ALL PRIVILEGES ON `{$dbDetails['name']}`.* TO '{$dbDetails['username']}'@'localhost';";
            DB::connection($connection)->unprepared($grantSql);

            $flushSql = "FLUSH PRIVILEGES;";
            DB::connection($connection)->unprepared($flushSql);

            $this->info('✅ Database and user created successfully');

            // Test the new connection
            $this->info('🔍 Testing new database connection...');
            if ($this->testTenantDatabaseConnection($dbDetails)) {
                $this->info('✅ Tenant database connection test successful');
            } else {
                $this->warn('⚠️  Tenant database connection test failed, but creation succeeded');
            }

        } catch (Exception $e) {
            // Try to clean up on failure
            try {
                if (isset($connection)) {
                    DB::connection($connection)->unprepared("DROP DATABASE IF EXISTS `{$dbDetails['name']}`;");
                    DB::connection($connection)->unprepared("DROP USER IF EXISTS '{$dbDetails['username']}'@'localhost';");
                    DB::connection($connection)->unprepared("FLUSH PRIVILEGES;");
                }
            } catch (Exception $cleanupException) {
                $this->warn("Could not clean up after failure: {$cleanupException->getMessage()}");
            }

            throw new Exception("Failed to create database: {$e->getMessage()}");
        }
    }

    private function createTemporaryConnection(array $dbUser): void
    {
        config([
            'database.connections.temp_admin' => [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => config('database.connections.mysql.database'),
                'username' => $dbUser['user'],
                'password' => $dbUser['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);
    }

    private function testTenantDatabaseConnection(array $dbDetails): bool
    {
        try {
            $tenantConfig = [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => $dbDetails['name'],
                'username' => $dbDetails['username'],
                'password' => $dbDetails['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];

            config(['database.connections.test_tenant' => $tenantConfig]);

            // Try to connect and run a simple query
            $result = DB::connection('test_tenant')->select('SELECT 1 as test');

            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    private function createTenantRecord(array $tenantData, array $dbDetails, bool $skipDatabaseCreation = false): Tenant
    {
        return $this->tenantService->createTenant(
            $tenantData['name'],
            $tenantData['domain'],
            $tenantData['status'],
            $dbDetails['name'],
            $tenantData['notes'],
            $tenantData['homepage'],
            $skipDatabaseCreation
        );
    }

    private function completeTenantSetup(Tenant $tenant, array $dbDetails, ?string $homepageFolder = null): void
    {
        $this->info('🔄 Setting up tenant...');

        // Store database credentials securely only if they exist
        $updateData = [];
        if (isset($dbDetails['username'])) {
            $updateData['database_username'] = encrypt($dbDetails['username']);
        }
        if (isset($dbDetails['password'])) {
            $updateData['database_password'] = encrypt($dbDetails['password']);
        }

        if (!empty($updateData)) {
            $tenant->update($updateData);
        }

        // Create homepage folder and file if needed
        if ($homepageFolder && $homepageFolder !== 'default') {
            $this->createHomepageStructure($homepageFolder, $tenant->domains()->first()->domain ?? 'tenant');
        }

        // Run migrations if requested
        if ($this->confirm('Run migrations for this tenant?', true)) {
            $this->info('🔄 Running tenant migrations...');
            try {
                $this->call('tenant:db', [
                    'operation' => 'migrate',
                    '--tenant' => $tenant->id,
                    '--force' => true
                ]);
            } catch (Exception $e) {
                $this->warn("Migration failed: {$e->getMessage()}");
                $this->comment('💡 You can run migrations later with: php artisan tenant:db migrate --tenant=' . $tenant->id);
            }
        }

        // Run seeds if requested
        if ($this->confirm('Run seeders for this tenant?', false)) {
            $this->info('🌱 Running tenant seeders...');
            try {
                $this->call('tenant:db', [
                    'operation' => 'seed',
                    '--tenant' => $tenant->id,
                    '--force' => true
                ]);
            } catch (Exception $e) {
                $this->warn("Seeding failed: {$e->getMessage()}");
                $this->comment('💡 You can run seeders later with: php artisan tenant:db seed --tenant=' . $tenant->id);
            }
        }
    }

    private function createHomepageStructure(string $folder, string $domain): void
    {
        $homepagePath = resource_path("views/tenants/{$folder}");
        $homeBladeFile = "{$homepagePath}/home.blade.php";

        // Create directory if it doesn't exist
        if (!file_exists($homepagePath)) {
            mkdir($homepagePath, 0755, true);
            $this->info("   📁 Created homepage directory: views/tenants/{$folder}");
        }

        // Create home.blade.php if it doesn't exist
        if (!file_exists($homeBladeFile)) {
            $this->createHomeBladefile($homeBladeFile, $domain);
            $this->info("   📄 Created home.blade.php file");
        }

        $this->info("   ✅ Homepage structure ready at: resources/views/tenants/{$folder}");
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
            ['💾 Database', $dbDetails['name'] ?? 'Auto-generated by stancl/tenancy'],
            ['👥 Panel Owner', $panelUser['username'] . ' (ID: ' . $panelUser['id'] . ')'],
            ['🏠 Homepage', $tenant->hasHomepage() ? 'Enabled' : 'Disabled'],
            ['📊 Status', $tenant->status],
            ['🆔 UUID', $tenant->id],
            ['📅 Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ];

        if (isset($dbDetails['username'])) {
            $rows[] = ['👤 DB Username', $dbDetails['username']];
        }

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
        $rows = [
            ['🏢 Tenant Name', $tenant->name],
            ['🌐 Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
            ['💾 Database', $dbDetails['name']],
            ['🏠 Homepage', $tenant->hasHomepage() ? 'Enabled' : 'Disabled'],
            ['📊 Status', $tenant->status],
            ['🆔 UUID', $tenant->id],
            ['📅 Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ];

        // Only show username if it exists (for custom databases)
        if (isset($dbDetails['username'])) {
            $rows[] = ['👤 DB Username', $dbDetails['username']];
        }

        $this->table(['Field', 'Value'], $rows);

        $this->newLine();
        $this->comment('💡 Database credentials are encrypted and stored in tenant record');
        $this->comment('🔧 Use php artisan tenant:db for database operations');
    }

    private function selectExistingMySQLUser(): ?array
    {
        try {
            // Get existing database users from FastPanel
            $databaseUsers = $this->getFastPanelDatabaseUsers();

            if (empty($databaseUsers)) {
                $this->warn('⚠️  No existing MySQL users found in FastPanel');
                return null;
            }

            $this->info('👤 Available MySQL Database Users:');
            $this->newLine();

            $choices = ['skip' => '⏭️  Cancel - Don\'t use existing user'];

            foreach ($databaseUsers as $index => $user) {
                $label = "{$user['login']} (ID: {$user['id']}) - Owner: {$user['owner_id']}";
                $choices[$index] = $label;
                $this->line("  <info>{$index}</info>: {$label}");
            }

            $this->newLine();
            $selected = $this->choice('Select existing MySQL user', $choices, 'skip');

            if ($selected === 'skip') {
                return null;
            }

            return $databaseUsers[$selected];
        } catch (Exception $e) {
            $this->warn("Could not fetch existing MySQL users: {$e->getMessage()}");
            return null;
        }
    }

    private function getNewMySQLUserDetails(string $dbName): ?array
    {
        $this->info('✨ Creating new MySQL database user:');
        $this->newLine();

        // Suggest username based on database name (but user can change it)
        $suggestedUsername = str_replace('tenant_', '', $dbName) . '_user';
        if (strlen($suggestedUsername) > 16) {
            $suggestedUsername = substr($suggestedUsername, 0, 16);
        }

        $username = $this->ask('MySQL username (max 16 chars)', $suggestedUsername);

        if (strlen($username) > 16) {
            $this->warn('⚠️  Username too long, truncating to 16 characters');
            $username = substr($username, 0, 16);
        }

        $password = $this->secret('MySQL password (leave empty for auto-generated)');

        if (empty($password)) {
            $password = Str::random(16);
            $this->info("Generated password: {$password}");
            $this->comment('💡 Save this password securely!');
        }

        return [
            'username' => $username,
            'password' => $password
        ];
    }

    private function addDatabaseCredentials(array &$dbDetails): void
    {
        // Generate username and password for custom databases
        $baseUsername = $dbDetails['name'];
        if (strlen($baseUsername) > 12) {
            $baseUsername = substr($baseUsername, 0, 12);
        }
        $dbUsername = $baseUsername . '_u';

        // Ensure username is within MySQL 16-character limit
        if (strlen($dbUsername) > 16) {
            $dbUsername = substr($dbUsername, 0, 16);
        }

        $dbDetails['username'] = $dbUsername;
        $dbDetails['password'] = Str::random(16);
    }

    private function useRootConnectionForTenantCreation(array $dbUser): void
    {
        // Temporarily update the default database connection to use root credentials
        $rootConfig = [
            'driver' => 'mysql',
            'host' => $dbUser['host'],
            'port' => config('database.connections.mysql.port', 3306),
            'database' => config('database.connections.mysql.database'),
            'username' => $dbUser['user'],
            'password' => $dbUser['password'],
            'charset' => config('database.connections.mysql.charset', 'utf8mb4'),
            'collation' => config('database.connections.mysql.collation', 'utf8mb4_unicode_ci'),
        ];

        // Update the default connection
        config(['database.connections.mysql' => $rootConfig]);

        // Clear any cached connections
        DB::purge('mysql');

        $this->info("🔧 Temporarily using root credentials for database creation");
    }

    private function grantMySQLPrivileges(string $dbName, string $username): void
    {
        try {
            $this->info("🔑 Granting MySQL privileges to '{$username}' for database '{$dbName}'...");

            // Use root credentials to grant privileges
            $envRoot = $this->getEnvRootUser();
            if (!$envRoot) {
                $this->warn("⚠️  No root credentials available, skipping privilege grant");
                return;
            }

            // Configure root connection temporarily
            $rootConfig = [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port', 3306),
                'database' => null, // Don't specify database for grants
                'username' => $envRoot['user'],
                'password' => $envRoot['password'],
                'charset' => config('database.connections.mysql.charset', 'utf8mb4'),
                'collation' => config('database.connections.mysql.collation', 'utf8mb4_unicode_ci'),
            ];

            config(['database.connections.root_connection' => $rootConfig]);

            // Grant privileges to the user for this specific database
            $grantSql = "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$username}'@'localhost'";
            DB::connection('root_connection')->unprepared($grantSql);

            // Flush privileges to make changes take effect
            DB::connection('root_connection')->unprepared('FLUSH PRIVILEGES');

            $this->info("✅ MySQL privileges granted successfully");

        } catch (Exception $e) {
            $this->warn("⚠️  Could not grant MySQL privileges: {$e->getMessage()}");
            $this->comment("💡 You may need to grant privileges manually:");
            $this->comment("   GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$username}'@'localhost';");
            $this->comment("   FLUSH PRIVILEGES;");
        }
    }
}
