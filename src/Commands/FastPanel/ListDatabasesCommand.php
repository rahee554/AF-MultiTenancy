<?php

namespace ArtflowStudio\Tenancy\Commands\FastPanel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Exception;

class ListDatabasesCommand extends Command
{
    protected $signature = 'fastpanel:databases 
                            {--user= : Filter by FastPanel user ID}
                            {--unassigned : Show only unassigned databases}
                            {--format=table : Output format (table|json)}';

    protected $description = 'List FastPanel databases with ownership and site mapping';

    public function handle(): int
    {
        try {
            $this->info('💾 FastPanel Databases');
            $this->newLine();

            $databases = $this->getFastPanelDatabases();
            if (empty($databases)) {
                $this->warn('No FastPanel databases found');
                return 0;
            }

            // Apply filters
            $databases = $this->applyFilters($databases);

            if ($this->option('format') === 'json') {
                $this->outputJson($databases);
            } else {
                $this->outputTable($databases);
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function getFastPanelDatabases(): array
    {
        $result = Process::run('sudo /usr/local/fastpanel2/fastpanel --json databases list');
        if (!$result->successful()) {
            throw new Exception('Failed to get FastPanel databases: ' . $result->errorOutput());
        }

        $databases = json_decode($result->output(), true);
        return is_array($databases) ? $databases : [];
    }

    private function applyFilters(array $databases): array
    {
        $filtered = collect($databases);

        // Filter by user
        if ($userId = $this->option('user')) {
            $filtered = $filtered->filter(function ($db) use ($userId) {
                return isset($db['owner']['id']) && $db['owner']['id'] == $userId;
            });
        }

        // Filter unassigned
        if ($this->option('unassigned')) {
            $filtered = $filtered->filter(function ($db) {
                return !isset($db['owner']['id']) || !isset($db['site']['id']);
            });
        }

        return $filtered->values()->all();
    }

    private function outputTable(array $databases): void
    {
        $rows = [];
        foreach ($databases as $db) {
            $owner = $db['owner']['username'] ?? 'Unassigned';
            $ownerId = isset($db['owner']['id']) ? "({$db['owner']['id']})" : '';
            $site = $db['site']['domain'] ?? 'No site';
            $siteId = isset($db['site']['id']) ? "({$db['site']['id']})" : '';
            $server = $db['server']['name'] ?? 'Unknown';

            $rows[] = [
                $db['id'],
                $db['name'],
                $owner . ' ' . $ownerId,
                $site . ' ' . $siteId,
                $server,
                $db['created_at'] ?? 'Unknown'
            ];
        }

        $this->table([
            'ID',
            'Database Name', 
            'Owner (ID)',
            'Site (ID)',
            'Server',
            'Created'
        ], $rows);

        // Show summary
        $total = count($databases);
        $assigned = collect($databases)->filter(fn($db) => isset($db['owner']['id']))->count();
        $siteLinked = collect($databases)->filter(fn($db) => isset($db['site']['id']))->count();

        $this->newLine();
        $this->info("📊 Summary:");
        $this->line("   Total databases: {$total}");
        $this->line("   Assigned to users: {$assigned}");
        $this->line("   Linked to sites: {$siteLinked}");
        $this->line("   Unassigned: " . ($total - $assigned));
    }

    private function outputJson(array $databases): void
    {
        $output = collect($databases)->map(function ($db) {
            return [
                'id' => $db['id'],
                'name' => $db['name'],
                'owner' => [
                    'id' => $db['owner']['id'] ?? null,
                    'username' => $db['owner']['username'] ?? null,
                ],
                'site' => [
                    'id' => $db['site']['id'] ?? null,
                    'domain' => $db['site']['domain'] ?? null,
                ],
                'server' => [
                    'id' => $db['server']['id'] ?? null,
                    'name' => $db['server']['name'] ?? null,
                ],
                'created_at' => $db['created_at'] ?? null,
                'status' => [
                    'assigned' => isset($db['owner']['id']),
                    'site_linked' => isset($db['site']['id']),
                ]
            ];
        });

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }
}
