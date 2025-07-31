<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;

class TenantsIndex extends Component
{
    use WithPagination;
    
    protected $paginationTheme = 'bootstrap';

    protected $layout = 'artflow-tenancy::layout.app';

    public string $search = '';
    public int $perPage = 15;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public string $statusFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    
    public function sortBy($field): void
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
        $query = Tenant::query()->with(['domains' => function ($q) {
            $q->select('tenant_id', 'domain')->limit(3);
        }]);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('data->name', 'like', "%{$this->search}%")
                  ->orWhere('id', 'like', "%{$this->search}%")
                  ->orWhereHas('domains', function ($sub) {
                      $sub->where('domain', 'like', "%{$this->search}%");
                  });
            });
        }

        // Apply status filter
        if (!empty($this->statusFilter)) {
            $query->where('data->status', $this->statusFilter);
        }

        // Apply sorting
        if (in_array($this->sortField, ['created_at', 'updated_at', 'id'])) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } elseif ($this->sortField === 'name') {
            $query->orderBy('data->name', $this->sortDirection);
        } elseif ($this->sortField === 'status') {
            $query->orderBy('data->status', $this->sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $tenants = $query->paginate($this->perPage);

        return view('artflow-tenancy::livewire.admin.tenants-index', [
            'tenants' => $tenants
        ])->extends($this->layout)->section('content');
    }

    public function suspendTenant($tenantId): void
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            $tenant->update(['data->status' => 'suspended']);
            
            session()->flash('message', 'Tenant suspended successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to suspend tenant: ' . $e->getMessage());
        }
    }

    public function activateTenant($tenantId): void
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            $tenant->update(['data->status' => 'active']);
            
            session()->flash('message', 'Tenant activated successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to activate tenant: ' . $e->getMessage());
        }
    }

    public function resetFilters(): void
    {
        $this->statusFilter = '';
        $this->search = '';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }
    
    public function deleteTenant($tenantId): void
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            $tenant->delete();
            
            session()->flash('message', 'Tenant deleted successfully.');
            $this->resetPage();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete tenant: ' . $e->getMessage());
        }
    }
}
