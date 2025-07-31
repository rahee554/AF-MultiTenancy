<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Stancl\Tenancy\Database\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantResourceQuotaService;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;
use Illuminate\Support\Facades\DB;

class TenantManagement extends Component
{
    use WithPagination;

    #[Layout('artflow-tenancy::layout.app')]

    // Search and pagination
    public $search = '';
    public $perPage = 15;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $statusFilter = '';

    // Modal states
    public $showQuotaModal = false;
    public $showSettingsModal = false;
    public $showCreateModal = false;
    public $showAnalyticsModal = false;

    // Selected tenant data
    public $selectedTenant = null;
    public $selectedTenantId = null;

    // Quota management
    public $quotas = [];
    public $quotaRecommendations = [];

    // Settings management
    public $settings = [];
    public $newSettings = [];

    // Create tenant form
    public $newTenant = [
        'name' => '',
        'domain' => '',
        'status' => 'active',
        'has_homepage' => false,
    ];

    // Analytics data
    public $analyticsData = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    protected $rules = [
        'newTenant.name' => 'required|string|max:255',
        'newTenant.domain' => 'required|string|max:255|unique:domains,domain',
        'newTenant.status' => 'required|in:active,inactive',
        'quotas.*' => 'nullable|integer|min:0',
    ];

    public function mount()
    {
        $this->resetQuotas();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
        $this->resetPage();
    }

    public function render()
    {
        $tenants = Tenant::when($this->search, function($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('id', 'like', '%' . $this->search . '%');
        })
        ->when($this->statusFilter, function($query) {
            $query->where('status', $this->statusFilter);
        })
        ->with('domains')
        ->orderBy($this->sortField, $this->sortDirection)
        ->paginate($this->perPage);

        // Get quota summaries for each tenant
        $quotaService = app(TenantResourceQuotaService::class);
        foreach ($tenants as $tenant) {
            $tenant->quota_summary = $quotaService->getQuotaSummary($tenant->id);
        }

        return view('artflow-tenancy::livewire.admin.tenant-management', [
            'tenants' => $tenants,
        ]);
    }

    public function openQuotaModal($tenantId)
    {
        $this->selectedTenantId = $tenantId;
        $this->selectedTenant = Tenant::find($tenantId);
        $this->loadTenantQuotas();
        $this->showQuotaModal = true;
    }

    public function openSettingsModal($tenantId)
    {
        $this->selectedTenantId = $tenantId;
        $this->selectedTenant = Tenant::find($tenantId);
        $this->loadTenantSettings();
        $this->showSettingsModal = true;
    }

    public function openAnalyticsModal($tenantId)
    {
        $this->selectedTenantId = $tenantId;
        $this->selectedTenant = Tenant::find($tenantId);
        $this->loadAnalyticsData();
        $this->showAnalyticsModal = true;
    }

    public function openCreateModal()
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function closeModals()
    {
        $this->showQuotaModal = false;
        $this->showSettingsModal = false;
        $this->showCreateModal = false;
        $this->showAnalyticsModal = false;
        $this->selectedTenant = null;
        $this->selectedTenantId = null;
        $this->resetValidation();
    }

    private function loadTenantQuotas()
    {
        $quotaService = app(TenantResourceQuotaService::class);
        $this->quotas = $quotaService->getTenantQuotas($this->selectedTenantId);
        $this->quotaRecommendations = $quotaService->getQuotaRecommendations($this->selectedTenantId);
    }

    private function loadTenantSettings()
    {
        $quotaService = app(TenantResourceQuotaService::class);
        $this->settings = $quotaService->getTenantSettings($this->selectedTenantId);
        $this->newSettings = $this->settings;
    }

    private function loadAnalyticsData()
    {
        try {
            $analyticsService = app(TenantAnalyticsService::class);
            $this->analyticsData = $analyticsService->getTenantMetrics($this->selectedTenantId);
        } catch (\Exception $e) {
            $this->analyticsData = [
                'error' => 'Unable to load analytics: ' . $e->getMessage()
            ];
        }
    }

    public function saveQuotas()
    {
        $this->validate([
            'quotas.*' => 'nullable|integer|min:0',
        ]);

        try {
            $quotaService = app(TenantResourceQuotaService::class);
            $quotaService->setTenantQuotas($this->selectedTenantId, $this->quotas);
            
            session()->flash('message', 'Quotas updated successfully!');
            $this->closeModals();
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating quotas: ' . $e->getMessage());
        }
    }

    public function saveSettings()
    {
        try {
            $quotaService = app(TenantResourceQuotaService::class);
            $quotaService->updateTenantSettings($this->selectedTenantId, $this->newSettings);
            
            session()->flash('message', 'Settings updated successfully!');
            $this->closeModals();
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating settings: ' . $e->getMessage());
        }
    }

    public function createTenant()
    {
        $this->validate();

        try {
            DB::beginTransaction();

            // Create the tenant
            $tenant = Tenant::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $this->newTenant['name'],
                'status' => $this->newTenant['status'],
                'has_homepage' => $this->newTenant['has_homepage'],
                'settings' => json_encode([
                    'quotas' => [], // Will use defaults
                    'current_usage' => [],
                    'created_via' => 'admin_panel',
                    'created_at' => now()->toISOString(),
                ]),
            ]);

            // Create domain
            $tenant->domains()->create([
                'domain' => $this->newTenant['domain'],
            ]);

            DB::commit();
            
            session()->flash('message', 'Tenant created successfully!');
            $this->closeModals();
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', 'Error creating tenant: ' . $e->getMessage());
        }
    }

    public function deleteTenant($tenantId)
    {
        try {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                // Delete domains first
                $tenant->domains()->delete();
                // Delete tenant
                $tenant->delete();
                
                session()->flash('message', 'Tenant deleted successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting tenant: ' . $e->getMessage());
        }
    }

    public function toggleTenantStatus($tenantId)
    {
        try {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $newStatus = $tenant->status === 'active' ? 'inactive' : 'active';
                $tenant->update(['status' => $newStatus]);
                
                session()->flash('message', "Tenant status changed to {$newStatus}!");
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating tenant status: ' . $e->getMessage());
        }
    }

    public function resetMonthlyUsage($tenantId)
    {
        try {
            $quotaService = app(TenantResourceQuotaService::class);
            $quotaService->resetMonthlyUsage($tenantId);
            
            session()->flash('message', 'Monthly usage reset successfully!');
            
            if ($this->selectedTenantId === $tenantId) {
                $this->loadTenantQuotas();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error resetting monthly usage: ' . $e->getMessage());
        }
    }

    private function resetQuotas()
    {
        $this->quotas = [
            'storage_mb' => 1000,
            'users' => 100,
            'monthly_bandwidth_gb' => 100,
            'api_calls_per_day' => 10000,
            'monthly_emails' => 1000,
            'cron_jobs' => 10,
            'webhooks' => 25,
            'database_size_mb' => 1000,
            'file_storage_mb' => 5000,
        ];
    }

    private function resetCreateForm()
    {
        $this->newTenant = [
            'name' => '',
            'domain' => '',
            'status' => 'active',
            'has_homepage' => false,
        ];
        $this->resetValidation();
    }

    public function addSetting()
    {
        $this->newSettings['new_key'] = '';
    }

    public function removeSetting($key)
    {
        unset($this->newSettings[$key]);
    }
}
