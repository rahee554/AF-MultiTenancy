<?php

namespace ArtflowStudio\Tenancy\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class CheckPrivilegesCommand extends Command
{
    protected $signature = 'tenant:check-privileges 
                            {--connection= : Database connection to check}
                            {--user= : Specific database user to check}';

    protected $description = 'Check database user privileges for tenant operations';

    public function handle()
    {
        $this->info('🔐 Database Privilege Checker');
        $this->info('============================');
        $this->newLine();

        $connection = $this->option('connection') ?: config('database.default');
        $specificUser = $this->option('user');

        $this->info("Checking privileges for connection: {$connection}");
        $this->newLine();

        try {
            // Get current connection info
            $config = config("database.connections.{$connection}");
            if (!$config) {
                $this->error("Connection '{$connection}' not found in configuration.");
                return 1;
            }

            $this->displayConnectionInfo($config);
            $this->newLine();

            // Check current user privileges
            $currentUser = $this->getCurrentDatabaseUser($connection);
            $this->info("Current database user: {$currentUser}");
            $this->newLine();

            // Check privileges for current user
            if ($specificUser) {
                $this->checkUserPrivileges($specificUser, $connection);
            } else {
                $this->checkUserPrivileges($currentUser, $connection);
            }

            // List all privileged users
            $this->newLine();
            $this->listPrivilegedUsers($connection);

            return 0;

        } catch (\Exception $e) {
            $this->error("Error checking privileges: {$e->getMessage()}");
            return 1;
        }
    }

    private function displayConnectionInfo(array $config): void
    {
        $this->table(['Setting', 'Value'], [
            ['Host', $config['host'] ?? 'localhost'],
            ['Port', $config['port'] ?? '3306'],
            ['Database', $config['database'] ?? 'N/A'],
            ['Username', $config['username'] ?? 'N/A'],
            ['Driver', $config['driver'] ?? 'N/A'],
        ]);
    }

    private function getCurrentDatabaseUser(string $connection = null): string
    {
        try {
            // Get the current connection configuration
            $config = config("database.connections." . ($connection ?: config('database.default')));
            return $config['username'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    private function checkUserPrivileges(string $user, string $connection = null): void
    {
        try {
            $this->info("Checking privileges for user: {$user}");
            $this->newLine();

            // First try to get grants for the current user without specifying host
            try {
                $grants = DB::connection($connection)->select("SHOW GRANTS");
            } catch (\Exception $e) {
                // If that fails, try with specific user@host combinations
                $userWithHost = $this->findUserWithHost($user, $connection);
                if ($userWithHost) {
                    $grants = DB::connection($connection)->select("SHOW GRANTS FOR {$userWithHost}");
                } else {
                    $this->warn("Could not determine grants for user: {$user}");
                    return;
                }
            }
            
            if (empty($grants)) {
                $this->warn("No grants found for user: {$user}");
                return;
            }

            $privileges = [];
            $hasCreateDb = false;
            $hasDropDb = false;
            $hasCreateTable = false;
            $hasGlobalPrivs = false;

            foreach ($grants as $grant) {
                $grantText = array_values((array)$grant)[0];
                $privileges[] = $grantText;

                // Check for specific privileges
                if (stripos($grantText, 'CREATE') !== false) {
                    $hasCreateTable = true;
                    if (stripos($grantText, 'ON *.*') !== false || stripos($grantText, 'ALL PRIVILEGES') !== false) {
                        $hasCreateDb = true;
                        $hasGlobalPrivs = true;
                    }
                }
                
                if (stripos($grantText, 'DROP') !== false) {
                    $hasDropDb = true;
                }

                if (stripos($grantText, 'ALL PRIVILEGES') !== false) {
                    $hasCreateDb = true;
                    $hasDropDb = true;
                    $hasCreateTable = true;
                    $hasGlobalPrivs = true;
                }
            }

            // Display privilege summary
            $this->displayPrivilegeSummary($hasCreateDb, $hasDropDb, $hasCreateTable, $hasGlobalPrivs);
            $this->newLine();

            // Display detailed grants
            $this->info('📋 Detailed Grants:');
            foreach ($privileges as $privilege) {
                $this->line("  • {$privilege}");
            }

        } catch (\Exception $e) {
            $this->error("Error checking user privileges: {$e->getMessage()}");
        }
    }

    private function findUserWithHost(string $username, string $connection = null): ?string
    {
        try {
            // Get possible user@host combinations for this username
            $users = DB::connection($connection)->select("
                SELECT CONCAT('`', User, '`@`', Host, '`') as user_host
                FROM mysql.user 
                WHERE User = ?
                LIMIT 1
            ", [$username]);

            return $users[0]->user_host ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function displayPrivilegeSummary(bool $hasCreateDb, bool $hasDropDb, bool $hasCreateTable, bool $hasGlobalPrivs): void
    {
        $this->info('🔍 Privilege Summary:');
        
        $summary = [
            ['Privilege', 'Status', 'Required For'],
            [
                'CREATE DATABASE', 
                $hasCreateDb ? '✅ Yes' : '❌ No', 
                'Creating tenant databases'
            ],
            [
                'DROP DATABASE', 
                $hasDropDb ? '✅ Yes' : '❌ No', 
                'Removing tenant databases'
            ],
            [
                'CREATE TABLE', 
                $hasCreateTable ? '✅ Yes' : '❌ No', 
                'Creating tenant tables'
            ],
            [
                'Global Privileges', 
                $hasGlobalPrivs ? '✅ Yes' : '❌ No', 
                'Full tenant management'
            ],
        ];

        $this->table($summary[0], array_slice($summary, 1));

        // Overall assessment
        if ($hasCreateDb && $hasDropDb && $hasCreateTable) {
            $this->info('✅ User has sufficient privileges for tenant operations');
        } else {
            $this->warn('⚠️  User may have insufficient privileges for some tenant operations');
            
            if (!$hasCreateDb) {
                $this->error('❌ Cannot create tenant databases - CREATE privilege on *.* required');
            }
            if (!$hasDropDb) {
                $this->warn('⚠️  Cannot drop tenant databases - DROP privilege recommended');
            }
            if (!$hasCreateTable) {
                $this->warn('⚠️  Cannot create tables - CREATE privilege required');
            }
        }
    }

    private function listPrivilegedUsers(string $connection = null): void
    {
        try {
            $this->info('👥 Users with CREATE DATABASE privileges:');
            
            // Query mysql.user table for users with global CREATE privilege
            $privilegedUsers = DB::connection($connection)->select("
                SELECT 
                    User, 
                    Host, 
                    Create_priv,
                    Drop_priv,
                    Super_priv,
                    Grant_priv
                FROM mysql.user 
                WHERE Create_priv = 'Y' 
                   OR Super_priv = 'Y'
                ORDER BY User
            ");

            if (empty($privilegedUsers)) {
                $this->warn('No users found with CREATE DATABASE privileges');
                return;
            }

            $tableData = [];
            foreach ($privilegedUsers as $user) {
                $tableData[] = [
                    'User' => "{$user->User}@{$user->Host}",
                    'CREATE' => $user->Create_priv === 'Y' ? '✅' : '❌',
                    'DROP' => $user->Drop_priv === 'Y' ? '✅' : '❌',
                    'SUPER' => $user->Super_priv === 'Y' ? '✅' : '❌',
                    'GRANT' => $user->Grant_priv === 'Y' ? '✅' : '❌',
                ];
            }

            $this->table(
                ['User@Host', 'CREATE', 'DROP', 'SUPER', 'GRANT'],
                $tableData
            );

            $this->newLine();
            $this->info('💡 Recommendations:');
            $this->line('  • Users with SUPER privilege can perform all operations');
            $this->line('  • For tenant operations, CREATE and DROP privileges are essential');
            $this->line('  • Consider using root user for initial setup if current user lacks privileges');

        } catch (\Exception $e) {
            $this->error("Error listing privileged users: {$e->getMessage()}");
        }
    }
}
