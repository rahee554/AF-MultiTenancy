<?php

namespace ArtflowStudio\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;

class CreateTenantWithProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $name;
    public string $domain;
    public string $status;
    public ?string $databaseName;
    public ?string $notes;
    public bool $hasHomepage;
    public bool $doMigrate;
    public bool $doSeed;
    public string $progressKey;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        string $name,
        string $domain,
        string $status = 'active',
        ?string $databaseName = null,
        ?string $notes = null,
        bool $hasHomepage = false,
        bool $doMigrate = true,
        bool $doSeed = true,
        string $progressKey = null
    ) {
        $this->name = $name;
        $this->domain = $domain;
        $this->status = $status;
    $this->databaseName = $databaseName;
        $this->notes = $notes;
        $this->hasHomepage = $hasHomepage;
        $this->doMigrate = $doMigrate;
        $this->doSeed = $doSeed;
        $this->progressKey = $progressKey ?? 'tenant_creation_' . uniqid();
    }

    public function handle(TenantService $tenantService): void
    {
        // Run steps inside a DB transaction to avoid partial state on failure
        DB::beginTransaction();
        try {
            $this->updateProgress('Starting tenant creation...', 10);

            // Create tenant (this should create the tenant record and database entry)
            $tenant = $tenantService->createTenant(
                $this->name,
                $this->domain,
                $this->status,
                $this->databaseName,
                $this->notes,
                $this->hasHomepage
            );

            $this->updateProgress('Tenant created, setting up database...', 40);

            // Run migrations if requested
            if ($this->doMigrate) {
                $tenantService->migrateTenant($tenant);
                $this->updateProgress('Database migrations completed', 70);
            }

            // Run seeders if requested and after migrations
            if ($this->doSeed) {
                $tenantService->seedTenant($tenant);
                $this->updateProgress('Database seeding completed', 90);
            }

            // Finalize
            $this->updateProgress('Tenant creation completed successfully!', 100, [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'domain' => $this->domain,
                'database' => $tenant->getDatabaseName(),
            ]);

            DB::commit();

            Log::info('Tenant created successfully via queue job', [
                'tenant_id' => $tenant->id,
                'name' => $this->name,
                'domain' => $this->domain,
                'job_id' => $this->job->getJobId(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->updateProgress('Tenant creation failed: ' . $e->getMessage(), 0, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Log::error('Tenant creation failed via queue job', [
                'name' => $this->name,
                'domain' => $this->domain,
                'error' => $e->getMessage(),
                'job_id' => isset($this->job) && method_exists($this->job, 'getJobId') ? $this->job->getJobId() : null,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('Tenant creation failed: ' . $exception->getMessage(), 0, [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
        ]);

        Log::error('CreateTenantWithProgressJob failed', [
            'name' => $this->name,
            'domain' => $this->domain,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    private function updateProgress(string $message, int $percentage, array $data = []): void
    {
        $progress = [
            'message' => $message,
            'step' => $message,
            'percentage' => $percentage,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        cache()->put($this->progressKey, $progress, 600); // 10 minutes

        // Also broadcast to any listening clients (if using websockets)
        if (function_exists('broadcast')) {
            try {
                broadcast(new \ArtflowStudio\Tenancy\Events\TenantCreationProgress(
                    $this->progressKey,
                    $progress
                ));
            } catch (\Exception $e) {
                // Ignore broadcasting errors
            }
        }
    }

    public static function getProgress(string $progressKey): ?array
    {
        return cache()->get($progressKey);
    }
}
