<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Models\Tenant;

class FixTenantDatabasesCommand extends Command
{
    protected $signature = 'tenancy:fix-tenant-databases {--force : Force database recreation}';
    protected $description = 'Fix tenant database connectivity issues by ensuring proper permissions and connections';

    public function handle()
    {
        $this->info('🔧 Fixing tenant database connectivity issues...');

        try {
            $tenants = Tenant::all();
            $this->info("Found {$tenants->count()} tenants to check.");

            foreach ($tenants as $tenant) {
                $this->line("Testing tenant: {$tenant->name} (ID: {$tenant->id})");
                
                // Use the actual database name from tenant configuration
                $databaseName = $tenant->database()->getName();
                $this->line("  Database: {$databaseName}");

                // Test if database exists using proper SQL syntax
                try {
                    $result = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
                    if (empty($result)) {
                        $this->error("  ❌ Database does not exist");
                        
                        // Create database using root connection
                        $this->createDatabase($databaseName);
                        $this->info("  ✅ Database created");
                    } else {
                        $this->info("  ✅ Database exists");
                    }

                    // Test basic connection
                    $this->testDatabaseConnection($databaseName);
                    $this->info("  ✅ Connection successful");

                } catch (\Exception $e) {
                    $this->error("  ❌ Error: " . $e->getMessage());
                    
                    // Try to create database if it doesn't exist
                    if (str_contains($e->getMessage(), 'does not exist')) {
                        try {
                            $this->createDatabase($databaseName);
                            $this->info("  ✅ Database created on retry");
                        } catch (\Exception $createError) {
                            $this->error("  ❌ Failed to create database: " . $createError->getMessage());
                        }
                    }
                }
            }

            $this->newLine();
            $this->info('🎉 Tenant database fixes completed!');

        } catch (\Exception $e) {
            $this->error("Error fixing tenant databases: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function createDatabase(string $databaseName): void
    {
        // Use root credentials to create database
        $rootConfig = [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'username' => config('tenancy.database.root_username', 'root'),
            'password' => config('tenancy.database.root_password', env('DB_ROOT_PASSWORD')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        $rootConnection = DB::connection()->getPdo();
        $rootConnection->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Grant permissions to main user
        $dbUser = config('database.connections.mysql.username');
        $rootConnection->exec("GRANT ALL PRIVILEGES ON `{$databaseName}`.* TO '{$dbUser}'@'localhost'");
        $rootConnection->exec("GRANT ALL PRIVILEGES ON `{$databaseName}`.* TO '{$dbUser}'@'%'");
        $rootConnection->exec("FLUSH PRIVILEGES");
    }

    private function testDatabaseConnection(string $databaseName): void
    {
        // Test by selecting 1 from the database
        DB::select("SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1", [$databaseName]);
    }
}
