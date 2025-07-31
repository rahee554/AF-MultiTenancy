<?php

namespace ArtflowStudio\Tenancy\Commands\FastPanel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Exception;

class ListUsersCommand extends Command
{
    protected $signature = 'fastpanel:users 
                            {--format=table : Output format (table|json)}';

    protected $description = 'List FastPanel users and their database ownership';

    public function handle(): int
    {
        try {
            $this->info('👥 FastPanel Users & Database Ownership');
            $this->newLine();

            // Get panel users
            $users = $this->getFastPanelUsers();
            if (empty($users)) {
                $this->warn('No FastPanel users found');
                return 0;
            }

            // Get databases for ownership mapping
            $databases = $this->getFastPanelDatabases();
            
            if ($this->option('format') === 'json') {
                $this->outputJson($users, $databases);
            } else {
                $this->outputTable($users, $databases);
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function getFastPanelUsers(): array
    {
        $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json users list');
        if (!$result->successful()) {
            throw new Exception('Failed to get FastPanel users: ' . $result->errorOutput());
        }

        $users = json_decode($result->output(), true);
        return is_array($users) ? $users : [];
    }

    private function getFastPanelDatabases(): array
    {
        $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json databases list');
        if (!$result->successful()) {
            return [];
        }

        $databases = json_decode($result->output(), true);
        return is_array($databases) ? $databases : [];
    }

    private function outputTable(array $users, array $databases): void
    {
        foreach ($users as $user) {
            $this->info("👤 User: {$user['username']} (ID: {$user['id']})");
            $this->line("   📧 Email: {$user['email']}");
            
            // Find databases owned by this user
            $userDatabases = collect($databases)->filter(function ($db) use ($user) {
                return isset($db['owner']['id']) && $db['owner']['id'] === $user['id'];
            });

            if ($userDatabases->count() > 0) {
                $this->line("   💾 Databases ({$userDatabases->count()}):");
                foreach ($userDatabases as $db) {
                    $siteInfo = isset($db['site']['domain']) ? " → {$db['site']['domain']}" : '';
                    $this->line("      • {$db['name']}{$siteInfo}");
                }
            } else {
                $this->line("   💾 Databases: None");
            }
            $this->newLine();
        }

        // Summary table
        $rows = [];
        foreach ($users as $user) {
            $dbCount = collect($databases)->filter(function ($db) use ($user) {
                return isset($db['owner']['id']) && $db['owner']['id'] === $user['id'];
            })->count();

            $rows[] = [
                $user['id'],
                $user['username'],
                $user['email'],
                $dbCount
            ];
        }

        $this->table(['ID', 'Username', 'Email', 'Databases'], $rows);
    }

    private function outputJson(array $users, array $databases): void
    {
        $output = collect($users)->map(function ($user) use ($databases) {
            $userDatabases = collect($databases)->filter(function ($db) use ($user) {
                return isset($db['owner']['id']) && $db['owner']['id'] === $user['id'];
            })->values();

            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'database_count' => $userDatabases->count(),
                'databases' => $userDatabases->map(function ($db) {
                    return [
                        'id' => $db['id'],
                        'name' => $db['name'],
                        'site_domain' => $db['site']['domain'] ?? null,
                    ];
                })
            ];
        });

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }
}
