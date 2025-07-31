<?php

namespace ArtflowStudio\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ArtflowStudio\Tenancy\Models\Tenant;

class TestQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $message;
    public ?string $tenantId;

    public function __construct(string $message = 'Test queue job executed successfully', ?string $tenantId = null)
    {
        $this->message = $message;
        $this->tenantId = $tenantId;
    }

    public function handle(): void
    {
        Log::info('TestQueueJob executed', [
            'message' => $this->message,
            'tenant_id' => $this->tenantId,
            'job_id' => $this->job->getJobId(),
            'timestamp' => now()->toISOString(),
        ]);

        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                $tenant->run(function () {
                    Log::info('Test job executed in tenant context', [
                        'tenant_database' => config('database.connections.tenant.database'),
                        'current_tenant' => tenancy()->tenant?->id,
                    ]);
                });
            }
        }

        // Simulate some work
        sleep(2);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TestQueueJob failed', [
            'message' => $this->message,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
