<?php

namespace ArtflowStudio\Tenancy\Commands\FastPanel;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Exception;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create-fastpanel 
                            {tenant_name : The name of the tenant}
                            {tenant_domain : The domain for the tenant}
                            {--mode=fastpanel : Creation mode (fastpanel|localhost)}
                            {--panel-user= : FastPanel user ID to assign database ownership}
                            {--site-id= : FastPanel site ID to link database}
                            {--db-name= : Custom database name (will be prefixed)}
                            {--db-username= : Custom database username}
                            {--db-password= : Custom database password}
                            {--server-id=1 : FastPanel database server ID}
                            {--homepage : Enable homepage for tenant}
                            {--status=active : Tenant status}
                            {--notes= : Tenant notes}
                            {--force : Force creation without confirmation}';

    protected $description = 'Create tenant with FastPanel integration or localhost mode';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle(): int
    {
        $tenantName = $this->argument('tenant_name');
        $tenantDomain = $this->argument('tenant_domain');
        $mode = $this->option('mode');

        $this->info("🚀 Creating tenant: {$tenantName}");
        $this->info("🌐 Domain: {$tenantDomain}");
        $this->info("⚙️  Mode: {$mode}");
        $this->newLine();

        if ($mode === 'fastpanel') {
            return $this->createWithFastPanel($tenantName, $tenantDomain);
        } else {
            return $this->createWithLocalhost($tenantName, $tenantDomain);
        }
    }

    private function createWithFastPanel(string $tenantName, string $tenantDomain): int
    {
        try {
            // 1. Check FastPanel availability
            if (!$this->checkFastPanelAvailability()) {
                $this->error('❌ FastPanel CLI not available. Use --mode=localhost instead.');
                return 1;
            }

            // 2. List and select FastPanel users
            $panelUsers = $this->getFastPanelUsers();
            if (empty($panelUsers)) {
                $this->error('❌ No FastPanel users found.');
                return 1;
            }

            $selectedPanelUser = $this->selectPanelUser($panelUsers);
            
            // 3. Optionally list and select sites
            $siteId = $this->option('site-id');
            if (!$siteId) {
                $sites = $this->getFastPanelSites($selectedPanelUser['id']);
                if (!empty($sites)) {
                    $selectedSite = $this->selectSite($sites);
                    $siteId = $selectedSite['id'] ?? null;
                }
            }

            // 4. Generate database details
            $dbDetails = $this->generateDatabaseDetails($tenantName);
            
            // 5. Create database via FastPanel CLI
            $panelDbResult = $this->createFastPanelDatabase($dbDetails, $selectedPanelUser, $siteId);
            if (!$panelDbResult) {
                $this->error('❌ Failed to create database via FastPanel.');
                return 1;
            }

            // 6. Create tenant record in Laravel
            $tenant = $this->createTenantRecord($tenantName, $tenantDomain, $dbDetails);
            
            // 7. Run migrations and setup
            $this->completeTenantSetup($tenant, $dbDetails);

            $this->displaySuccessMessage($tenant, $dbDetails, $selectedPanelUser, $siteId);
            return 0;

        } catch (Exception $e) {
            $this->error("❌ Error creating tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function createWithLocalhost(string $tenantName, string $tenantDomain): int
    {
        try {
            $this->info('🏠 Creating tenant in localhost mode...');
            
            // Check database privileges
            if (!$this->checkDatabasePrivileges()) {
                $this->error('❌ Insufficient database privileges.');
                return 1;
            }

            // Generate database details
            $dbDetails = $this->generateDatabaseDetails($tenantName);
            
            // Create database and user
            $this->createLocalDatabase($dbDetails);
            
            // Create tenant record
            $tenant = $this->createTenantRecord($tenantName, $tenantDomain, $dbDetails);
            
            // Setup tenant
            $this->completeTenantSetup($tenant, $dbDetails);

            $this->displayLocalSuccessMessage($tenant, $dbDetails);
            return 0;

        } catch (Exception $e) {
            $this->error("❌ Error creating tenant: {$e->getMessage()}");
            return 1;
        }
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
        $panelUserId = $this->option('panel-user');
        
        if ($panelUserId) {
            $selected = collect($panelUsers)->firstWhere('id', (int)$panelUserId);
            if ($selected) {
                $this->info("👤 Selected panel user: {$selected['username']} (ID: {$selected['id']})");
                return $selected;
            }
        }

        // Interactive selection
        $this->info('👥 Available FastPanel users:');
        $choices = [];
        foreach ($panelUsers as $user) {
            $label = "{$user['username']} (ID: {$user['id']}) - {$user['email']}";
            $choices[$user['id']] = $label;
            $this->line("  {$user['id']}: {$label}");
        }

        $selectedId = $this->choice('Select FastPanel user for database ownership', $choices);
        return collect($panelUsers)->firstWhere('id', (int)$selectedId);
    }

    private function getFastPanelSites(int $userId): array
    {
        try {
            $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json sites list');
            if (!$result->successful()) {
                return [];
            }

            $sites = json_decode($result->output(), true);
            if (!is_array($sites)) {
                return [];
            }

            // Filter sites by user if possible
            return collect($sites)->where('owner_id', $userId)->all();
        } catch (Exception $e) {
            return [];
        }
    }

    private function selectSite(array $sites): ?array
    {
        if (empty($sites)) {
            return null;
        }

        $this->info('🌐 Available sites:');
        $choices = ['skip' => 'Skip - No site assignment'];
        
        foreach ($sites as $site) {
            $label = "{$site['domain']} (ID: {$site['id']})";
            $choices[$site['id']] = $label;
            $this->line("  {$site['id']}: {$label}");
        }

        $selectedId = $this->choice('Select site to link database (optional)', $choices);
        
        if ($selectedId === 'skip') {
            return null;
        }

        return collect($sites)->firstWhere('id', (int)$selectedId);
    }

    private function generateDatabaseDetails(string $tenantName): array
    {
        $prefix = env('TENANT_DB_PREFIX', 'tenant_');
        
        // Generate database name
        $dbName = $this->option('db-name');
        if (!$dbName) {
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $tenantName);
            $normalized = preg_replace('/_+/', '_', trim($normalized, '_'));
            $dbName = strtolower($prefix . $normalized);
        } else {
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $dbName);
            $normalized = preg_replace('/_+/', '_', trim($normalized, '_'));
            if (!str_starts_with($normalized, $prefix)) {
                $normalized = $prefix . $normalized;
            }
            $dbName = strtolower($normalized);
        }

        // Generate username
        $dbUsername = $this->option('db-username') ?: $dbName . '_user';
        
        // Generate password
        $dbPassword = $this->option('db-password') ?: Str::random(16);

        return [
            'name' => $dbName,
            'username' => $dbUsername,
            'password' => $dbPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];
    }

    private function createFastPanelDatabase(array $dbDetails, array $panelUser, ?int $siteId): bool
    {
        $serverId = $this->option('server-id');
        
        $command = [
            'sudo', '/usr/local/fastpanel2/fastpanel', 'databases', 'create',
            '--server=' . $serverId,
            '--name=' . $dbDetails['name'],
            '--username=' . $dbDetails['username'],
            '--password=' . $dbDetails['password'],
        ];

        $this->info('🔧 Creating database via FastPanel CLI...');
        
        try {
            $result = Process::run(implode(' ', array_map('escapeshellarg', $command)));
            
            if (!$result->successful()) {
                $this->error("FastPanel command failed: {$result->errorOutput()}");
                return false;
            }

            $this->info('✅ Database created successfully via FastPanel');

            // Sync to ensure metadata is updated
            Process::run('sudo /usr/local/fastpanel2/fastpanel databases sync');
            
            return true;
        } catch (Exception $e) {
            $this->error("Failed to create database: {$e->getMessage()}");
            return false;
        }
    }

    private function createLocalDatabase(array $dbDetails): void
    {
        $this->info('🔧 Creating database and user on localhost...');

        // Check if we can connect as root or need a privileged user
        $adminUser = $this->getDatabaseAdminUser();
        
        $createDbSql = "CREATE DATABASE `{$dbDetails['name']}` CHARACTER SET {$dbDetails['charset']} COLLATE {$dbDetails['collation']};";
        $createUserSql = "CREATE USER IF NOT EXISTS '{$dbDetails['username']}'@'localhost' IDENTIFIED BY '{$dbDetails['password']}';";
        $grantSql = "GRANT ALL PRIVILEGES ON `{$dbDetails['name']}`.* TO '{$dbDetails['username']}'@'localhost';";
        $flushSql = "FLUSH PRIVILEGES;";

        try {
            DB::connection($adminUser['connection'])->unprepared($createDbSql);
            DB::connection($adminUser['connection'])->unprepared($createUserSql);
            DB::connection($adminUser['connection'])->unprepared($grantSql);
            DB::connection($adminUser['connection'])->unprepared($flushSql);
            
            $this->info('✅ Database and user created successfully');
        } catch (Exception $e) {
            throw new Exception("Failed to create database: {$e->getMessage()}");
        }
    }

    private function getDatabaseAdminUser(): array
    {
        // Try to use tenant admin if configured
        if (config('database.connections.tenant_admin')) {
            return ['connection' => 'tenant_admin'];
        }

        // Fall back to mysql connection (should have sufficient privileges)
        return ['connection' => 'mysql'];
    }

    private function createTenantRecord(string $tenantName, string $tenantDomain, array $dbDetails): Tenant
    {
        $hasHomepage = $this->option('homepage');
        $status = $this->option('status');
        $notes = $this->option('notes');

        return $this->tenantService->createTenant(
            $tenantName,
            $tenantDomain,
            $status,
            $dbDetails['name'],
            $notes,
            $hasHomepage
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

    private function checkDatabasePrivileges(): bool
    {
        try {
            // Test CREATE DATABASE privilege
            $testDbName = 'test_create_privilege_' . time();
            DB::statement("CREATE DATABASE `{$testDbName}`");
            DB::statement("DROP DATABASE `{$testDbName}`");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function displaySuccessMessage(Tenant $tenant, array $dbDetails, array $panelUser, ?int $siteId): void
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
            ['👥 Panel User', $panelUser['username'] . ' (ID: ' . $panelUser['id'] . ')'],
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
    }

    private function displayLocalSuccessMessage(Tenant $tenant, array $dbDetails): void
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
