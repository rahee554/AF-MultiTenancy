<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Artisan;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;

class ViewTenant extends Component
{
    public Tenant $tenant;
    protected $layout = 'artflow-tenancy::layout.app';

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function migrateTenant()
    {
        try {
            // Run migrations for the tenant
            Artisan::call('tenants:migrate', ['--tenant' => $this->tenant->id]);
            session()->flash('message', 'Database migrated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to migrate database: ' . $e->getMessage());
        }
    }

    public function seedTenant()
    {
        try {
            // Run seeders for the tenant
            Artisan::call('tenants:seed', ['--tenant' => $this->tenant->id]);
            session()->flash('message', 'Database seeded successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to seed database: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.view-tenant', ['tenant' => $this->tenant])->extends($this->layout);
    }
}
