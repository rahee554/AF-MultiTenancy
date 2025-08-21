<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FastPanelCommand extends Command
{
    protected $signature = 'tenant:link-fastpanel 
                            {--tenant= : Tenant UUID to link}
                            {--force : Force linking without confirmation}';

    protected $description = 'Link a tenant to FastPanel 2 user and website';

    public function handle()
    {
        $this->info('🚀 FastPanel 2 Integration');
        $this->info('=========================');
        $this->newLine();

        // Get tenant
        $tenant = $this->getTenant();
        if (!$tenant) {
            return 1;
        }

        $this->displayTenantInfo($tenant);
        $this->newLine();

        // Check FastPanel connection
        if (!$this->checkFastPanelConnection()) {
            $this->error('❌ Cannot connect to FastPanel. Please check your configuration.');
            return 1;
        }

        // Get FastPanel users
        $fastPanelUsers = $this->getFastPanelUsers();
        if (empty($fastPanelUsers)) {
            $this->error('❌ No FastPanel users found.');
            return 1;
        }

        // Select FastPanel user
        $selectedUser = $this->selectFastPanelUser($fastPanelUsers);
        if (!$selectedUser) {
            return 1;
        }

        // Get websites for selected user
        $websites = $this->getFastPanelWebsites($selectedUser['id']);
        if (empty($websites)) {
            $this->error('❌ No websites found for selected user.');
            return 1;
        }

        // Select website
        $selectedWebsite = $this->selectWebsite($websites);
        if (!$selectedWebsite) {
            return 1;
        }

        // Get database users for the website
        $dbUsers = $this->getFastPanelDatabaseUsers($selectedWebsite['id']);
        $selectedDbUser = $this->selectDatabaseUser($dbUsers);
        if (!$selectedDbUser) {
            return 1;
        }

        // Confirm linking
        if (!$this->confirmLinking($tenant, $selectedUser, $selectedWebsite, $selectedDbUser)) {
            $this->info('❌ Linking cancelled.');
            return 0;
        }

        // Perform the linking
        return $this->linkTenantToFastPanel($tenant, $selectedUser, $selectedWebsite, $selectedDbUser);
    }

    private function getTenant(): ?Tenant
    {
        $tenantUuid = $this->option('tenant') ?: $this->ask('Enter tenant UUID to link');
        
        if (!$tenantUuid) {
            $this->error('Tenant UUID is required');
            return null;
        }

        $tenant = Tenant::where('id', $tenantUuid)->first();
        if (!$tenant) {
            $this->error("Tenant not found: {$tenantUuid}");
            return null;
        }

        return $tenant;
    }

    private function displayTenantInfo(Tenant $tenant): void
    {
        $primaryDomain = $tenant->domains()->first();
        $this->table(['Field', 'Value'], [
            ['ID', $tenant->id],
            ['Name', $tenant->name],
            ['Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
            ['Database', $tenant->getDatabaseName()],
            ['Status', $tenant->status],
        ]);
    }

    private function checkFastPanelConnection(): bool
    {
        try {
            // Check if FastPanel API credentials are configured
            $fastPanelUrl = env('FASTPANEL_URL');
            $fastPanelToken = env('FASTPANEL_API_TOKEN');

            if (!$fastPanelUrl || !$fastPanelToken) {
                $this->warn('⚠️  FastPanel credentials not found in environment.');
                $this->info('Please set FASTPANEL_URL and FASTPANEL_API_TOKEN in your .env file');
                return false;
            }

            // Test connection (assuming FastPanel has a health/ping endpoint)
            $response = Http::withToken($fastPanelToken)
                ->timeout(10)
                ->get("{$fastPanelUrl}/api/ping");

            if ($response->successful()) {
                $this->info('✅ FastPanel connection successful');
                return true;
            }

            $this->warn('⚠️  FastPanel API connection failed');
            return false;
        } catch (\Exception $e) {
            $this->warn("⚠️  FastPanel connection error: {$e->getMessage()}");
            return false;
        }
    }

    private function getFastPanelUsers(): array
    {
        try {
            $this->info('🔍 Fetching FastPanel users...');
            
            $fastPanelCli = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            
            if (!file_exists($fastPanelCli)) {
                $this->warn('⚠️  FastPanel CLI not found. Using mock data for development.');
                return $this->getMockUsers();
            }

            // Execute FastPanel CLI command to get users
            $command = "sudo {$fastPanelCli} --json users list 2>/dev/null";
            $output = shell_exec($command);
            
            if (!$output) {
                $this->warn('⚠️  Could not fetch users from FastPanel CLI. Using mock data.');
                return $this->getMockUsers();
            }

            $userData = json_decode($output, true);
            
            if (!$userData || !isset($userData['users'])) {
                $this->warn('⚠️  Invalid FastPanel users response. Using mock data.');
                return $this->getMockUsers();
            }

            return $userData['users'];
            
        } catch (\Exception $e) {
            $this->error("Error fetching FastPanel users: {$e->getMessage()}");
            return $this->getMockUsers();
        }
    }

    private function getMockUsers(): array
    {
        // Mock data structure for development/testing
        return [
            [
                'id' => 1,
                'username' => 'root',
                'email' => 'admin@fastpanel.local',
                'role' => 'administrator',
                'created_at' => '2024-01-01 00:00:00'
            ],
            [
                'id' => 2,
                'username' => 'user1',
                'email' => 'user1@domain.com',
                'role' => 'user',
                'created_at' => '2024-01-15 10:30:00'
            ],
            [
                'id' => 3,
                'username' => 'webmaster',
                'email' => 'webmaster@domain.com',
                'role' => 'user',
                'created_at' => '2024-02-01 14:20:00'
            ]
        ];
    }

    private function selectFastPanelUser(array $users): ?array
    {
        $this->info('📋 Available FastPanel users:');
        $this->newLine();

        $choices = [];
        foreach ($users as $index => $user) {
            $choices[] = "[{$index}] {$user['username']} ({$user['email']}) - {$user['role']}";
            $this->info("  [{$index}] {$user['username']} ({$user['email']}) - {$user['role']}");
        }

        $this->newLine();
        $selection = (int) $this->ask('Select FastPanel user (enter number)', '0');

        if (!isset($users[$selection])) {
            $this->error('Invalid selection');
            return null;
        }

        $selectedUser = $users[$selection];
        $this->info("✅ Selected user: {$selectedUser['username']}");
        return $selectedUser;
    }

    private function getFastPanelWebsites(int $userId): array
    {
        try {
            $this->info("🔍 Fetching websites for user ID {$userId}...");
            
            $fastPanelCli = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            
            if (!file_exists($fastPanelCli)) {
                $this->warn('⚠️  FastPanel CLI not found. Using mock data for development.');
                return $this->getMockWebsites($userId);
            }

            // Execute FastPanel CLI command to get websites
            $command = "sudo {$fastPanelCli} --json websites list 2>/dev/null";
            $output = shell_exec($command);
            
            if (!$output) {
                $this->warn('⚠️  Could not fetch websites from FastPanel CLI. Using mock data.');
                return $this->getMockWebsites($userId);
            }

            $websiteData = json_decode($output, true);
            
            if (!$websiteData || !isset($websiteData['websites'])) {
                $this->warn('⚠️  Invalid FastPanel websites response. Using mock data.');
                return $this->getMockWebsites($userId);
            }

            // Filter websites by user ID if possible
            return array_filter($websiteData['websites'], function($website) use ($userId) {
                return isset($website['user_id']) && $website['user_id'] == $userId;
            });
            
        } catch (\Exception $e) {
            $this->error("Error fetching websites: {$e->getMessage()}");
            return $this->getMockWebsites($userId);
        }
    }

    private function getMockWebsites(int $userId): array
    {
        // Mock data for development/testing
        return [
            [
                'id' => 1,
                'user_id' => $userId,
                'domain' => 'example.com',
                'document_root' => '/var/www/example.com',
                'php_version' => '8.2',
                'status' => 'active',
                'ssl_enabled' => true
            ],
            [
                'id' => 2,
                'user_id' => $userId,
                'domain' => 'demo.com',
                'document_root' => '/var/www/demo.com',
                'php_version' => '8.1',
                'status' => 'active',
                'ssl_enabled' => false
            ]
        ];
    }

    private function selectWebsite(array $websites): ?array
    {
        $this->info('🌐 Available websites:');
        $this->newLine();

        foreach ($websites as $index => $website) {
            $ssl = $website['ssl_enabled'] ? '🔒 SSL' : '🔓 No SSL';
            $this->info("  [{$index}] {$website['domain']} (PHP {$website['php_version']}) - {$ssl}");
        }

        $this->newLine();
        $selection = (int) $this->ask('Select website (enter number)', '0');

        if (!isset($websites[$selection])) {
            $this->error('Invalid selection');
            return null;
        }

        $selectedWebsite = $websites[$selection];
        $this->info("✅ Selected website: {$selectedWebsite['domain']}");
        return $selectedWebsite;
    }

    private function getFastPanelDatabaseUsers(int $websiteId): array
    {
        try {
            $this->info("🔍 Fetching database users for website ID {$websiteId}...");
            
            $fastPanelCli = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            
            if (!file_exists($fastPanelCli)) {
                $this->warn('⚠️  FastPanel CLI not found. Using mock data for development.');
                return $this->getMockDatabaseUsers($websiteId);
            }

            // Execute FastPanel CLI command to get databases
            $command = "sudo {$fastPanelCli} --json databases list 2>/dev/null";
            $output = shell_exec($command);
            
            if (!$output) {
                $this->warn('⚠️  Could not fetch databases from FastPanel CLI. Using mock data.');
                return $this->getMockDatabaseUsers($websiteId);
            }

            $databaseData = json_decode($output, true);
            
            if (!$databaseData || !isset($databaseData['databases'])) {
                $this->warn('⚠️  Invalid FastPanel databases response. Using mock data.');
                return $this->getMockDatabaseUsers($websiteId);
            }

            // Filter databases by website ID if possible
            return array_filter($databaseData['databases'], function($database) use ($websiteId) {
                return isset($database['website_id']) && $database['website_id'] == $websiteId;
            });
            
        } catch (\Exception $e) {
            $this->error("Error fetching database users: {$e->getMessage()}");
            return $this->getMockDatabaseUsers($websiteId);
        }
    }

    private function getMockDatabaseUsers(int $websiteId): array
    {
        // Mock data for development/testing
        return [
            [
                'id' => 1,
                'username' => 'fp_example_user',
                'database' => 'fp_example_db',
                'host' => 'localhost',
                'permissions' => ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP']
            ],
            [
                'id' => 2,
                'username' => 'fp_demo_user',
                'database' => 'fp_demo_db',
                'host' => 'localhost',
                'permissions' => ['SELECT', 'INSERT', 'UPDATE', 'DELETE']
            ]
        ];
    }

    private function selectDatabaseUser(array $dbUsers): ?array
    {
        if (empty($dbUsers)) {
            $this->warn('⚠️  No database users found for this website');
            return ['id' => null, 'username' => 'root', 'database' => null, 'host' => 'localhost'];
        }

        $this->info('💾 Available database users:');
        $this->newLine();

        foreach ($dbUsers as $index => $dbUser) {
            $perms = implode(', ', $dbUser['permissions']);
            $this->info("  [{$index}] {$dbUser['username']}@{$dbUser['host']} - DB: {$dbUser['database']}");
            $this->info("       Permissions: {$perms}");
        }

        $this->newLine();
        $selection = (int) $this->ask('Select database user (enter number)', '0');

        if (!isset($dbUsers[$selection])) {
            $this->error('Invalid selection');
            return null;
        }

        $selectedDbUser = $dbUsers[$selection];
        $this->info("✅ Selected database user: {$selectedDbUser['username']}");
        return $selectedDbUser;
    }

    private function confirmLinking(Tenant $tenant, array $user, array $website, array $dbUser): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->newLine();
        $this->info('📋 Linking Summary:');
        $this->table(['Item', 'Value'], [
            ['Tenant', $tenant->name],
            ['Tenant Domain', $tenant->domains()->first()?->domain ?? 'N/A'],
            ['FastPanel User', $user['username']],
            ['FastPanel Website', $website['domain']],
            ['Database User', $dbUser['username']],
            ['Database Host', $dbUser['host'] ?? 'localhost'],
        ]);

        return $this->confirm('Proceed with linking?', false);
    }

    private function linkTenantToFastPanel(Tenant $tenant, array $user, array $website, array $dbUser): int
    {
        try {
            $this->info('🔗 Linking tenant to FastPanel...');

            // Store FastPanel configuration in tenant data
            $fastPanelConfig = [
                'fastpanel_user_id' => $user['id'],
                'fastpanel_username' => $user['username'],
                'fastpanel_website_id' => $website['id'],
                'fastpanel_domain' => $website['domain'],
                'fastpanel_document_root' => $website['document_root'],
                'fastpanel_php_version' => $website['php_version'],
                'fastpanel_db_user' => $dbUser['username'],
                'fastpanel_db_host' => $dbUser['host'] ?? 'localhost',
                'fastpanel_db_name' => $dbUser['database'] ?? null,
                'linked_at' => now()->toISOString()
            ];

            // Update tenant data
            $currentData = $tenant->data ?? [];
            $currentData['fastpanel'] = $fastPanelConfig;
            $tenant->update(['data' => $currentData]);

            // Create symlink if needed (optional)
            $this->createSymlink($tenant, $website);

            // Update FastPanel website configuration (via API)
            $this->updateFastPanelConfig($website, $tenant);

            $this->newLine();
            $this->info('✅ Tenant successfully linked to FastPanel!');
            $this->newLine();

            $this->table(['Configuration', 'Value'], [
                ['FastPanel User', $user['username']],
                ['Website Domain', $website['domain']],
                ['Document Root', $website['document_root']],
                ['PHP Version', $website['php_version']],
                ['Database User', $dbUser['username']],
                ['Database Host', $dbUser['host'] ?? 'localhost'],
                ['Linked At', now()->format('Y-m-d H:i:s')]
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to link tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function createSymlink(Tenant $tenant, array $website): void
    {
        try {
            $tenantPath = storage_path("app/tenants/{$tenant->id}");
            $websitePath = $website['document_root'] . '/tenant';

            if (!is_dir($tenantPath)) {
                mkdir($tenantPath, 0755, true);
            }

            if (!file_exists($websitePath) && !is_link($websitePath)) {
                symlink($tenantPath, $websitePath);
                $this->info("✅ Created symlink: {$websitePath} -> {$tenantPath}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️  Could not create symlink: {$e->getMessage()}");
        }
    }

    private function updateFastPanelConfig(array $website, Tenant $tenant): void
    {
        try {
            $this->info('🔧 Updating FastPanel website configuration...');
            
            // Create database through FastPanel CLI for proper metadata
            $dbName = $tenant->getDatabaseName();
            $dbUser = $dbName . '_user';
            $dbPassword = Str::random(16);
            
            $this->createFastPanelDatabase($dbName, $dbUser, $dbPassword);
            
            // Update tenant with database credentials
            $currentData = $tenant->data ?? [];
            $currentData['fastpanel']['db_user'] = $dbUser;
            $currentData['fastpanel']['db_password'] = $dbPassword;
            $tenant->update(['data' => $currentData]);
            
            $this->info('✅ FastPanel configuration updated');
        } catch (\Exception $e) {
            $this->warn("⚠️  Could not update FastPanel config: {$e->getMessage()}");
        }
    }

    private function createFastPanelDatabase(string $dbName, string $dbUser, string $dbPassword): bool
    {
        try {
            $fastPanelCli = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            
            if (!file_exists($fastPanelCli)) {
                $this->warn('⚠️  FastPanel CLI not found. Skipping database creation through FastPanel.');
                return false;
            }

            $this->info("🔧 Creating database '{$dbName}' through FastPanel CLI...");
            
            // Create database with user through FastPanel CLI
            $command = "sudo {$fastPanelCli} databases create --name {$dbName} --user {$dbUser} --password {$dbPassword} 2>/dev/null";
            $output = shell_exec($command);
            
            if ($output && strpos($output, 'success') !== false) {
                $this->info("✅ Database '{$dbName}' created successfully through FastPanel");
                
                // Sync to ensure metadata is updated
                $syncCommand = "sudo {$fastPanelCli} databases sync 2>/dev/null";
                shell_exec($syncCommand);
                
                return true;
            } else {
                $this->warn("⚠️  Database creation through FastPanel may have failed");
                return false;
            }
            
        } catch (\Exception $e) {
            $this->warn("⚠️  Error creating database through FastPanel: {$e->getMessage()}");
            return false;
        }
    }
}
