<?php

namespace ArtflowStudio\Tenancy\Commands\Tenancy;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantCommand extends Command
{
    protected $signature = 'tenant:manage 
                            {action? : The action to perform (create, list, delete, activate, deactivate, enable-homepage, disable-homepage, status, health)}
                            {--tenant= : Tenant UUID for actions on specific tenant}
                            {--name= : Tenant name}
                            {--domain= : Tenant domain}
                            {--database= : Custom database name}
                            {--status=active : Tenant status}
                            {--homepage : Enable homepage for tenant}
                            {--notes= : Tenant notes}
                            {--force : Force action without confirmation}';

    protected $description = 'Comprehensive tenant management command - for database operations use tenant:db';    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $actions = [
            'create' => 'Create a new tenant',
            'list' => 'List all tenants',
            'delete' => 'Delete a tenant',
            'activate' => 'Activate a tenant',
            'deactivate' => 'Deactivate a tenant',
            'enable-homepage' => 'Enable homepage for a tenant',
            'disable-homepage' => 'Disable homepage for a tenant',
            'status' => 'Show tenant status',
            'health' => 'Check system health'
        ];

        if (!$action) {
            $this->info('🚀 Tenant Management System');
            $this->info('Available actions:');
            $this->newLine();
            foreach ($actions as $cmd => $desc) {
                $this->info("  <fg=green>{$cmd}</fg=green> - {$desc}");
            }
            $this->newLine();
            $this->comment('💡 For database operations (migrate, seed, rollback), use: php artisan tenant:db');
            $this->newLine();
            $action = $this->choice('Please select an action', array_keys($actions));
        }

        // Check for database operations first and redirect
        if (in_array($action, ['migrate', 'migrate-all', 'seed', 'seed-all'])) {
            return $this->redirectToDatabaseCommand($action);
        }

        if (!array_key_exists($action, $actions)) {
            return $this->showHelp();
        }

        return match ($action) {
            'create' => $this->createTenantDeprecated(),
            'list' => $this->listTenants(),
            'delete' => $this->deleteTenant(),
            'deactivate' => $this->deactivateTenant(),
            'activate' => $this->activateTenant(),
            'enable-homepage' => $this->enableHomepage(),
            'disable-homepage' => $this->disableHomepage(),
            'status' => $this->showTenantStatus(),
            'health' => $this->checkSystemHealth(),
        };
    }

    private function createTenantDeprecated(): int
    {
        $this->newLine();
        $this->warn('⚠️  DEPRECATION WARNING');
        $this->line('The "tenant:manage create" command is deprecated.');
        $this->newLine();
        $this->info('🚀 Please use the new enhanced tenant creation command:');
        $this->line('   <fg=green>php artisan tenant:create</fg=green>');
        $this->newLine();
        $this->comment('The new command provides:');
        $this->line('   • Interactive mode selection (localhost/FastPanel)');
        $this->line('   • Automatic privilege checking');
        $this->line('   • FastPanel user and site selection');
        $this->line('   • Better database user management');
        $this->line('   • Enhanced error handling');
        $this->newLine();

        if ($this->confirm('Continue with the old command anyway?', false)) {
            return $this->createTenantLegacy();
        } else {
            $this->info('👍 Redirecting to the new command...');
            $this->newLine();
            return $this->call('tenant:create', [
                '--name' => $this->option('name'),
                '--domain' => $this->option('domain'),
                '--database' => $this->option('database'),
                '--status' => $this->option('status'),
                '--homepage' => $this->option('homepage'),
                '--notes' => $this->option('notes'),
                '--force' => $this->option('force'),
            ]);
        }
    }

    private function createTenantLegacy(): int
    {
        $name = $this->option('name') ?: $this->ask('Tenant name');
        $domain = $this->option('domain') ?: $this->ask('Tenant domain');
        
        // Ask for database name
        $customDb = $this->option('database');
        if (!$customDb) {
            $customDb = $this->ask('Database name (leave empty for auto-generated)', null);
        }
        
        // Ask for homepage
        $hasHomepage = $this->option('homepage') || $this->confirm('Does this tenant have a homepage?', false);
        
        $status = $this->option('status') ?: 'active';
        $notes = $this->option('notes');

        if (!$name || !$domain) {
            $this->error('Name and domain are required');
            return 1;
        }

        // Check database privileges before proceeding
        if (!$this->checkDatabasePrivileges()) {
            $this->error('❌ Database privilege check failed. Cannot create tenant.');
            return 1;
        }

        // Normalize and prefix custom DB name
        if ($customDb && strtolower($customDb) !== 'null') {
            $prefix = env('TENANT_DB_PREFIX', 'tenant_');
            // Replace hyphens, spaces, and other non-alphanumeric chars with underscores
            $normalized = preg_replace('/[^A-Za-z0-9_]/', '_', $customDb);
            // Collapse multiple underscores into one
            $normalized = preg_replace('/_+/', '_', $normalized);
            // Trim leading/trailing underscores
            $normalized = trim($normalized, '_');
            // Add prefix if not already present
            if (!str_starts_with($normalized, $prefix)) {
                $normalized = $prefix . $normalized;
            }
            $customDb = strtolower($normalized);
        } else {
            $customDb = null; // Will auto-generate
        }

        try {
            $tenant = $this->tenantService->createTenant($name, $domain, $status, $customDb, $notes, $hasHomepage);

            $this->info("✅ Tenant created successfully!");
            $this->newLine();
            
            // Beautiful summary table
            $primaryDomain = $tenant->domains()->first();
            $this->table([
                'Field', 'Value'
            ], [
                ['🏢 Tenant Name', $tenant->name],
                ['🌐 Domain', $primaryDomain ? $primaryDomain->domain : 'No domain'],
                ['💾 Database', $tenant->getDatabaseName()],
                ['🏠 Homepage', $tenant->hasHomepage() ? 'Enabled' : 'Disabled'],
                ['📊 Status', $tenant->status],
                ['🆔 UUID', $tenant->id],
                ['📅 Created', $tenant->created_at->format('Y-m-d H:i:s')],
            ]);

            // Optional migrations and seeding
            if ($this->confirm('Run migrations for this tenant?', true)) {
                $this->info('🔄 Running migrations via tenant:db...');
                $migrateExitCode = Artisan::call('tenant:db', [
                    'operation' => 'migrate',
                    '--tenant' => $tenant->id
                ]);
                
                if ($migrateExitCode === 0) {
                    $this->info("✅ Migrations completed");
                } else {
                    $this->warn("⚠️ Migrations had issues (exit code: {$migrateExitCode})");
                }
            }

            if ($this->confirm('Run seeders for this tenant?', false)) {
                $this->info('🌱 Running seeders via tenant:db...');
                $seedExitCode = Artisan::call('tenant:db', [
                    'operation' => 'seed',
                    '--tenant' => $tenant->id
                ]);
                
                if ($seedExitCode === 0) {
                    $this->info("✅ Seeders completed");
                } else {
                    $this->warn("⚠️ Seeders had issues (exit code: {$seedExitCode})");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function listTenants(): int
    {
        $tenants = Tenant::orderBy('created_at', 'desc')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return 0;
        }

        $headers = ['ID', 'Name', 'Domain', 'Database', 'Homepage', 'Status', 'Created'];
        $rows = $tenants->map(function ($tenant) {
            $primaryDomain = $tenant->domains()->first();
            return [
                $tenant->id,
                $tenant->name,
                $primaryDomain ? $primaryDomain->domain : 'No domain',
                $tenant->getDatabaseName(),
                $tenant->hasHomepage() ? '✅ Yes' : '❌ No',
                $tenant->status,
                $tenant->created_at->format('Y-m-d H:i'),
            ];
        });

        $this->table($headers, $rows);
        $this->info("Total tenants: {$tenants->count()}");
        return 0;
    }

    private function deleteTenant(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $primaryDomain = $tenant->domains()->first();
        $domainName = $primaryDomain ? $primaryDomain->domain : 'No domain';
        $this->info("Tenant: {$tenant->name} ({$domainName})");

        if (!$this->option('force') && !$this->confirm('Delete this tenant and its database?', false)) {
            $this->info('Deletion cancelled.');
            return 0;
        }

        try {
            $this->tenantService->deleteTenant($tenant);
            $this->info('✅ Tenant deleted successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to delete tenant: {$e->getMessage()}");
            return 1;
        }
    }

    private function activateTenant(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $this->tenantService->activateTenant($tenant);
        $this->info("✅ Tenant '{$tenant->name}' activated!");
        return 0;
    }

    private function deactivateTenant(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $this->tenantService->deactivateTenant($tenant);
        $this->info("✅ Tenant '{$tenant->name}' deactivated!");
        return 0;
    }

    private function enableHomepage(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        try {
            $tenant->enableHomepage();
            $this->info("✅ Homepage enabled for tenant '{$tenant->name}'!");
            $this->info("   Tenant will now show homepage at root URL");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to enable homepage: {$e->getMessage()}");
            return 1;
        }
    }

    private function disableHomepage(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        try {
            $tenant->disableHomepage();
            $this->info("✅ Homepage disabled for tenant '{$tenant->name}'!");
            $this->info("   Tenant will now redirect to /login from root URL");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to disable homepage: {$e->getMessage()}");
            return 1;
        }
    }

    private function showTenantStatus(): int
    {
        $tenant = $this->findTenant();
        if (!$tenant) return 1;

        $status = $this->tenantService->getTenantStatus($tenant);

        $this->info("Tenant Status Report");
        $this->info("==================");

        $this->displayTenantInfo($tenant);

        $this->info("\nDatabase Status:");
        $this->info($status['database_exists'] ? "✅ Database exists" : "❌ Database missing");
        
        if ($status['database_exists']) {
            $this->info("📊 Migrations: {$status['migration_count']}");
            $this->info("📋 Tables: {$status['table_count']}");
            $this->info("💾 Size: {$status['database_size']} MB");
        }

        if (isset($status['error'])) {
            $this->error("⚠️  Error: {$status['error']}");
        }

        return 0;
    }

    private function findTenant(): ?Tenant
    {
        $tenantUuid = $this->option('tenant') ?: $this->ask('Enter tenant UUID');
        return $this->findTenantByUuid($tenantUuid);
    }

    private function findTenantByUuid(?string $uuid): ?Tenant
    {
        if (!$uuid) {
            $this->error('Tenant UUID is required');
            return null;
        }

        $tenant = Tenant::where('id', $uuid)->first();
        if (!$tenant) {
            $this->error("Tenant not found: {$uuid}");
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
            ['Created', $tenant->created_at->format('Y-m-d H:i:s')],
        ]);
    }

    private function checkSystemHealth(): int
    {
        $this->info('🔍 Checking system health...');
        $this->newLine();
        
        try {
            $health = $this->tenantService->checkSystemHealth();
            
            // Add additional checks
            $additionalChecks = $this->performAdditionalHealthChecks();
            $health['checks'] = array_merge($health['checks'], $additionalChecks);
            
            // Recalculate overall status
            $hasErrors = collect($health['checks'])->contains(function($check) {
                return $check['status'] !== 'ok';
            });
            $health['status'] = $hasErrors ? 'unhealthy' : 'healthy';
            
            $overallStatus = $health['status'] === 'healthy' ? '✅ HEALTHY' : '❌ UNHEALTHY';
            $this->info("🎯 System Status: {$overallStatus}");
            $this->newLine();
            
            // Create table for health checks
            $rows = [];
            foreach ($health['checks'] as $check => $result) {
                $status = $result['status'] === 'ok' ? '✅' : '❌';
                $rows[] = [
                    $check,
                    $status,
                    $result['message']
                ];
            }
            
            $this->table(['Component', 'Status', 'Details'], $rows);
            
            // Add summary info
            if (isset($health['summary'])) {
                $this->newLine();
                $this->info('📊 Summary:');
                foreach ($health['summary'] as $key => $value) {
                    $this->info("   {$key}: {$value}");
                }
            }
            
            return $health['status'] === 'healthy' ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error('❌ Health check failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Perform additional health checks beyond the basic tenant service checks
     */
    private function performAdditionalHealthChecks(): array
    {
        $checks = [];

        // Database privilege check
        try {
            $currentUser = $this->getCurrentDatabaseUser();
            $hasPrivileges = $this->hasCreateDatabasePrivilege($currentUser);
            $checks['database_privileges'] = [
                'status' => $hasPrivileges ? 'ok' : 'error',
                'message' => $hasPrivileges 
                    ? "User '{$currentUser}' has CREATE privileges" 
                    : "User '{$currentUser}' lacks CREATE DATABASE privileges"
            ];
        } catch (\Exception $e) {
            $checks['database_privileges'] = [
                'status' => 'error',
                'message' => 'Could not check database privileges: ' . $e->getMessage()
            ];
        }

        // File system permissions check
        $tenantViewsPath = resource_path('views/tenant');
        if (!is_dir($tenantViewsPath)) {
            try {
                mkdir($tenantViewsPath, 0755, true);
            } catch (\Exception $e) {
                // Ignore if we can't create
            }
        }
        
        $viewsWritable = is_dir($tenantViewsPath) && is_writable($tenantViewsPath);
        $checks['tenant_views_writable'] = [
            'status' => $viewsWritable ? 'ok' : 'error',
            'message' => $viewsWritable 
                ? 'Tenant views directory is writable' 
                : 'Tenant views directory not writable (needed for homepage creation)'
        ];

        // Storage directory check
        $storageWritable = is_writable(storage_path());
        $checks['storage_writable'] = [
            'status' => $storageWritable ? 'ok' : 'error',
            'message' => $storageWritable 
                ? 'Storage directory is writable' 
                : 'Storage directory not writable'
        ];

        // Public directory check (for asset linking)
        $publicWritable = is_writable(public_path());
        $checks['public_writable'] = [
            'status' => $publicWritable ? 'ok' : 'error',
            'message' => $publicWritable 
                ? 'Public directory is writable' 
                : 'Public directory not writable (needed for asset linking)'
        ];

        // FastPanel compatibility check (if enabled)
        if (env('FASTPANEL_ENABLED', false)) {
            $fastPanelCli = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            $fastPanelWorking = file_exists($fastPanelCli);
            
            if ($fastPanelWorking) {
                $testCommand = "sudo {$fastPanelCli} --version 2>/dev/null";
                $output = shell_exec($testCommand);
                $fastPanelWorking = !empty($output);
            }
            
            $checks['fastpanel_compatibility'] = [
                'status' => $fastPanelWorking ? 'ok' : 'error',
                'message' => $fastPanelWorking 
                    ? 'FastPanel CLI accessible' 
                    : 'FastPanel CLI not accessible or not found'
            ];
        }

        // Environment checks
        $dbConfig = config('database.connections.' . config('database.default'));
        $configMapping = [
            'DB_CONNECTION' => config('database.default'),
            'DB_HOST' => $dbConfig['host'] ?? null,
            'DB_DATABASE' => $dbConfig['database'] ?? null,
            'DB_USERNAME' => $dbConfig['username'] ?? null,
        ];
        
        $missingConfigs = [];
        foreach ($configMapping as $key => $value) {
            if (empty($value)) {
                $missingConfigs[] = $key;
            }
        }
        
        $checks['environment_config'] = [
            'status' => empty($missingConfigs) ? 'ok' : 'error',
            'message' => empty($missingConfigs) 
                ? 'All required database configuration present' 
                : 'Missing configuration: ' . implode(', ', $missingConfigs)
        ];

        return $checks;
    }

    private function showHelp(): int
    {
        $this->error("Unknown action. Available actions:");
        $this->info('- create: Create a new tenant');
        $this->info('- list: List all tenants');
        $this->info('- delete: Delete a tenant');
        $this->info('- activate: Activate a tenant');
        $this->info('- deactivate: Deactivate a tenant');
        $this->info('- enable-homepage: Enable homepage for a tenant');
        $this->info('- disable-homepage: Disable homepage for a tenant');
        $this->info('- status: Show tenant status');
        $this->info('- health: Check system health');
        $this->newLine();
        $this->comment('💡 For database operations (migrate, seed, rollback), use: php artisan tenant:db');
        return 1;
    }

    private function redirectToDatabaseCommand(string $action): int
    {
        $this->warn("⚠️  Database operation '{$action}' has been moved to 'tenant:db' command.");
        $this->newLine();
        $this->info('💡 Use one of these commands instead:');
        
        $suggestions = match($action) {
            'migrate' => [
                'tenant:db migrate --tenant=<uuid>' => 'Migrate specific tenant',
                'tenant:db migrate --all' => 'Migrate all active tenants'
            ],
            'migrate-all' => [
                'tenant:db migrate --all' => 'Migrate all active tenants'
            ],
            'seed' => [
                'tenant:db seed --tenant=<uuid>' => 'Seed specific tenant',
                'tenant:db seed --all' => 'Seed all active tenants'
            ],
            'seed-all' => [
                'tenant:db seed --all' => 'Seed all active tenants'
            ],
            default => [
                'tenant:db --help' => 'See all database operations'
            ]
        };

        foreach ($suggestions as $command => $description) {
            $this->info("  <fg=green>{$command}</fg=green> - {$description}");
        }
        
        $this->newLine();
        $this->comment('For more database operations: php artisan tenant:db --help');
        
        return 1;
    }

    /**
     * Check if current database user has CREATE DATABASE privileges
     */
    private function checkDatabasePrivileges(): bool
    {
        try {
            $this->info('🔍 Checking database privileges...');
            
            // Check current user privileges
            $currentUser = $this->getCurrentDatabaseUser();
            $this->info("📋 Current database user: {$currentUser}");
            
            if ($this->hasCreateDatabasePrivilege($currentUser)) {
                $this->info('✅ Current user has CREATE DATABASE privilege');
                return true;
            }
            
            $this->warn("⚠️  Current user '{$currentUser}' does not have CREATE DATABASE privilege");
            
            // Check if we have root credentials in env
            $rootUser = env('DB_ROOT_USER');
            $rootPassword = env('DB_ROOT_PASSWORD');
            
            if ($rootUser && $rootPassword) {
                $this->info("🔑 Using root credentials from environment");
                return $this->switchToRootUser($rootUser, $rootPassword);
            }
            
            // Show available users and let user select
            return $this->selectPrivilegedUser();
            
        } catch (\Exception $e) {
            $this->error("❌ Error checking database privileges: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get current database user
     */
    private function getCurrentDatabaseUser(): string
    {
        try {
            // Get the current connection configuration
            $config = config('database.connections.' . config('database.default'));
            return $config['username'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Check if user has CREATE DATABASE privilege
     */
    private function hasCreateDatabasePrivilege(string $user): bool
    {
        try {
            // For the current user, use SHOW GRANTS without specifying user
            $grants = DB::select("SHOW GRANTS");
            
            foreach ($grants as $grant) {
                $grantString = array_values((array)$grant)[0];
                if (str_contains($grantString, 'ALL PRIVILEGES') || 
                    (str_contains($grantString, 'CREATE') && str_contains($grantString, 'ON *.*'))) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            // Fallback: try to create and drop a test database
            try {
                $testDbName = 'tenancy_privilege_test_' . time();
                DB::statement("CREATE DATABASE {$testDbName}");
                DB::statement("DROP DATABASE {$testDbName}");
                return true;
            } catch (\Exception $e2) {
                return false;
            }
        }
    }

    /**
     * Get all database users with CREATE privileges
     */
    private function getPrivilegedUsers(): array
    {
        try {
            $users = DB::select("SELECT User, Host FROM mysql.user WHERE User != ''");
            $privilegedUsers = [];
            
            foreach ($users as $user) {
                $userString = $user->User . '@' . $user->Host;
                if ($this->hasCreateDatabasePrivilege($userString)) {
                    $privilegedUsers[] = [
                        'user' => $user->User,
                        'host' => $user->Host,
                        'full' => $userString
                    ];
                }
            }
            
            return $privilegedUsers;
        } catch (\Exception $e) {
            $this->warn("Cannot fetch database users: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Let user select a privileged database user
     */
    private function selectPrivilegedUser(): bool
    {
        $privilegedUsers = $this->getPrivilegedUsers();
        
        if (empty($privilegedUsers)) {
            $this->error('❌ No users with CREATE DATABASE privileges found');
            return false;
        }
        
        $this->info('🔍 Found users with CREATE DATABASE privileges:');
        $choices = [];
        
        foreach ($privilegedUsers as $index => $user) {
            $choices[] = "{$user['user']}@{$user['host']}";
            $this->info("  [{$index}] {$user['user']}@{$user['host']}");
        }
        
        $selection = $this->choice('Select a user to use for database creation:', $choices);
        $selectedUser = $privilegedUsers[array_search($selection, $choices)];
        
        // Ask for password
        $password = $this->secret("Enter password for {$selectedUser['user']}:");
        
        return $this->switchToUser($selectedUser['user'], $password, $selectedUser['host']);
    }

    /**
     * Switch to root user connection
     */
    private function switchToRootUser(string $username, string $password): bool
    {
        return $this->switchToUser($username, $password);
    }

    /**
     * Switch database connection to different user
     */
    private function switchToUser(string $username, string $password, string $host = 'localhost'): bool
    {
        try {
            // Create new database connection config
            $newConfig = config('database.connections.' . config('database.default'));
            $newConfig['username'] = $username;
            $newConfig['password'] = $password;
            $newConfig['host'] = $host;
            
            // Test the connection
            $testConnection = new \PDO(
                "mysql:host={$host};dbname=" . $newConfig['database'],
                $username,
                $password
            );
            
            // Update the current connection config
            config(['database.connections.' . config('database.default') => $newConfig]);
            
            // Reconnect
            DB::purge(config('database.default'));
            DB::reconnect(config('database.default'));
            
            $this->info("✅ Successfully switched to user: {$username}@{$host}");
            return true;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to switch to user {$username}: {$e->getMessage()}");
            return false;
        }
    }
}
