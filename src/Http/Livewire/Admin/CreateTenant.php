<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Jobs\CreateTenantWithProgressJob;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Bus;


class CreateTenant extends Component
{
    
    public string $name = '';
    public string $domain = 'https://';
    public string $status = 'active';
    public string $notes = '';
    public bool $hasHomepage = false;
    public bool $useQueue = true;
    public ?string $progressKey = null;
    public array $progress = [];
    
    // Add missing properties for view
    public bool $isCreating = false;
    public ?string $jobId = null;
    public string $currentStep = '';
    public int $progressPercentage = 0;
    public string $databaseName = '';
    public bool $showMigrateButton = false;
    public bool $showSeedButton = false;
    public ?int $createdTenantId = null;
    // Options
    public bool $doMigrate = true;
    public bool $doSeed = true;

    protected $layout = 'artflow-tenancy::layout.app';

    protected $listeners = ['checkProgress'];

    // Ensure seed checkbox follows migrate checkbox
    public function updatedDoMigrate(): void
    {
        if (! $this->doMigrate) {
            $this->doSeed = false;
        } else {
            // if migrate re-enabled, also enable seed by default
            $this->doSeed = true;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            // Domain must be a valid URL and MUST start with https:// per product requirement
            'domain' => ['required', 'string', 'max:255', 'url', 'regex:/^https:\/\/.+/'],
            // add suspended status option
            'status' => 'required|in:active,inactive,blocked,suspended',
            'notes' => 'nullable|string|max:1000',
            'hasHomepage' => 'boolean',
            'useQueue' => 'boolean',
            'databaseName' => 'nullable|string|max:64|regex:/^[a-zA-Z0-9_]+$/',
        ];
    }

    public function updatedName()
    {
        // Auto-generate database name from tenant name
        // Only auto-fill when user hasn't supplied a custom value yet
        if (empty($this->databaseName)) {
            $this->databaseName = 'tenant_' . Str::slug($this->name, '_');
        }
    }

    public function create(TenantService $tenantService)
    {
        $this->validate();

        // Run lightweight preflight checks (DB connection, filesystem permissions)
        try {
            $this->preflightChecks();
        } catch (\Exception $e) {
            session()->flash('error', 'Preflight checks failed: ' . $e->getMessage());
            return;
        }

        $this->isCreating = true;

        try {
            if ($this->useQueue) {
                $this->createWithQueue();
            } else {
                $this->createDirectly($tenantService);
            }
        } catch (\Exception $e) {
            $this->isCreating = false;
            session()->flash('error', 'Failed to create tenant: ' . $e->getMessage());
        }
    }

    protected function preflightChecks(): void
    {
        // Check DB connection
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }

        // Check storage writable for tenant assets
        $storagePath = storage_path('app/tenants');
        if (! is_dir($storagePath)) {
            if (! @mkdir($storagePath, 0775, true)) {
                throw new \Exception('Failed to create storage directory: ' . $storagePath);
            }
        }
        if (! is_writable($storagePath)) {
            throw new \Exception('Storage path not writable: ' . $storagePath);
        }
    }

    private function createWithQueue()
    {
        $this->progressKey = 'tenant_creation_' . Str::random(10);
        $this->jobId = $this->progressKey;
        $this->currentStep = 'Initializing...';
        $this->progressPercentage = 0;
        $job = new CreateTenantWithProgressJob(
            $this->name,
            $this->domain,
            $this->status,
            $this->databaseName ?: null,
            $this->notes ?: null,
            $this->hasHomepage,
            $this->doMigrate,
            $this->doSeed,
            $this->progressKey
        );

        // If the app uses the database queue but no worker is running, dispatchSync so the process continues.
        if (config('queue.default') === 'database') {
            // Run synchronously as a fallback so users don't get stuck when no worker is running.
            Bus::dispatchSync($job);
        } else {
            dispatch($job);
        }

        session()->flash('message', 'Tenant creation started! You can monitor progress below.');
    }

    private function createDirectly(TenantService $tenantService)
    {
        $this->currentStep = 'Creating tenant...';
        $this->progressPercentage = 10;

        DB::beginTransaction();
        try {
            $tenant = $tenantService->createTenant(
                $this->name,
                $this->domain,
                $this->status,
                $this->databaseName ?: null,
                $this->notes ?: null,
                $this->hasHomepage
            );

            $this->progressPercentage = 40;
            $this->currentStep = 'Tenant created successfully!';
            $this->createdTenantId = $tenant->id;
            $this->showMigrateButton = true;

            if ($this->doMigrate) {
                $tenantService->migrateTenant($tenant);
                $this->progressPercentage = 70;
                $this->currentStep = 'Migrations completed';
            }

            if ($this->doSeed) {
                $tenantService->seedTenant($tenant);
                $this->progressPercentage = 90;
                $this->currentStep = 'Seeding completed';
            }

            DB::commit();

            // Finalize UI and dispatch browser event for client-side redirect after a short delay
            $this->progressPercentage = 100;
            $this->currentStep = 'Done';
            $this->isCreating = false;

            session()->flash('message', 'Tenant created successfully!');
            $this->dispatch('tenant-created', url: route('tenancy.admin.view', ['tenant' => $tenant->id]));
        } catch (\Exception $e) {
            DB::rollBack();
            $this->isCreating = false;
            session()->flash('error', 'Failed to create tenant: ' . $e->getMessage());
        }
    }

    public function migrateTenant()
    {
        if ($this->createdTenantId) {
            try {
                // Run migrations for the tenant
                Artisan::call('tenants:migrate', ['--tenant' => $this->createdTenantId]);
                $this->showSeedButton = true;
                $this->currentStep = 'Database migrated successfully!';
                session()->flash('message', 'Database migrated successfully! You can now seed the database.');
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to migrate database: ' . $e->getMessage());
            }
        }
    }

    public function seedTenant()
    {
        if ($this->createdTenantId) {
            try {
                // Run seeders for the tenant
                Artisan::call('tenants:seed', ['--tenant' => $this->createdTenantId]);
                $this->currentStep = 'Database seeded successfully!';
                $this->progressPercentage = 100;
                session()->flash('message', 'Database seeded successfully! Tenant is ready to use.');
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to seed database: ' . $e->getMessage());
            }
        }
    }

    public function checkProgress()
    {
        if ($this->progressKey) {
            $this->progress = CreateTenantWithProgressJob::getProgress($this->progressKey) ?? [];
            
                if (!empty($this->progress)) {
                    $this->progressPercentage = $this->progress['percentage'] ?? 0;
                    $this->currentStep = $this->progress['step'] ?? ($this->progress['message'] ?? 'Processing...');
                }
            
            // If completed, redirect to tenant view
            if (isset($this->progress['percentage']) && $this->progress['percentage'] >= 100) {
                $this->isCreating = false;
                $this->progressPercentage = 100;
                $this->currentStep = 'Done';

                if (isset($this->progress['data']['tenant_id'])) {
                    session()->flash('message', 'Tenant created successfully!');
                    // Livewire v3: dispatch browser event
                    $this->dispatch('tenant-created', url: route('tenancy.admin.view', ['tenant' => $this->progress['data']['tenant_id']]));
                }
            }
        }
    }

    public function resetForm()
    {
        $this->name = '';
        $this->domain = '';
        $this->status = 'active';
        $this->databaseName = '';
        $this->notes = '';
        $this->hasHomepage = false;
        $this->useQueue = true;
        $this->progressKey = null;
        $this->progress = [];
        $this->isCreating = false;
        $this->jobId = null;
        $this->currentStep = '';
        $this->progressPercentage = 0;
        $this->showMigrateButton = false;
        $this->showSeedButton = false;
        $this->createdTenantId = null;
        $this->resetValidation();
    }

    public function render()
    {
    return view('artflow-tenancy::livewire.admin.create-tenant-new')->extends($this->layout);
    }
}
