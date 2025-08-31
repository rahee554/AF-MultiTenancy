<div>
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">Tenants</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('tenancy.admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Tenants</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <a href="{{ route('tenancy.admin.create') }}" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-plus fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Create Tenant
                </a>
            </div>
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Content-->
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">

            <!--begin::Card-->
            <div class="card">
                <!--begin::Card header-->
                <div class="card-header border-0 pt-6">
                    <!--begin::Card title-->
                    <div class="card-title">
                        <!--begin::Search-->
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search" 
                                   class="form-control form-control-solid w-250px ps-13" 
                                   placeholder="Search tenants..." />
                        </div>
                        <!--end::Search-->
                    </div>
                    <!--begin::Card title-->
                    <!--begin::Card toolbar-->
                    <div class="card-toolbar">
                        <!--begin::Toolbar-->
                        <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                            <!--begin::Filter-->
                            <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                <i class="ki-duotone ki-filter fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Filter
                            </button>
                            <!--begin::Menu 1-->
                            <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                                <!--begin::Header-->
                                <div class="px-7 py-5">
                                    <div class="fs-5 text-gray-900 fw-bold">Filter Options</div>
                                </div>
                                <!--end::Header-->
                                <!--begin::Separator-->
                                <div class="separator border-gray-200"></div>
                                <!--end::Separator-->
                                <!--begin::Content-->
                                <div class="px-7 py-5" data-kt-user-table-filter="form">
                                    <!--begin::Input group-->
                                    <div class="mb-10">
                                        <label class="form-label fs-6 fw-semibold">Status:</label>
                                        <select class="form-select form-select-solid fw-bold" wire:model.live="statusFilter" data-placeholder="Select option">
                                            <option value="">All</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="blocked">Blocked</option>
                                        </select>
                                    </div>
                                    <!--end::Input group-->
                                    <!--begin::Actions-->
                                    <div class="d-flex justify-content-end">
                                        <button type="button" wire:click="resetFilters" class="btn btn-light btn-active-light-primary fw-semibold me-2 px-6">Reset</button>
                                        <button type="button" wire:click="applyFilters" class="btn btn-primary fw-semibold px-6">Apply</button>
                                    </div>
                                    <!--end::Actions-->
                                </div>
                                <!--end::Content-->
                            </div>
                            <!--end::Menu 1-->
                            <!--end::Filter-->
                            <!--begin::Export-->
                            <button type="button" class="btn btn-light-primary me-3">
                                <i class="ki-duotone ki-exit-up fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Export
                            </button>
                            <!--end::Export-->
                        </div>
                        <!--end::Toolbar-->
                    </div>
                    <!--end::Card toolbar-->
                </div>
                <!--end::Card header-->
                <!--begin::Card body-->
                <div class="card-body py-4">
                    <!--begin::Table-->
                    <div class="table-responsive">
                        <table class="table table-rounded table-striped border gy-7 gs-7">
                            <thead>
                                <tr class="fw-semibold fs-6 text-gray-800 border-bottom-2 border-gray-200">
                                    <th class="min-w-125px">
                                        <a href="#" wire:click="sortBy('name')" class="text-gray-600 text-hover-primary">
                                            Name
                                            @if($sortField === 'name')
                                                <i class="ki-duotone ki-{{ $sortDirection === 'asc' ? 'up' : 'down' }} fs-5 ms-1">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="min-w-125px">Domain</th>
                                    <th class="min-w-125px">Database</th>
                                    <th class="min-w-125px">Status</th>
                                    <th class="min-w-125px">
                                        <a href="#" wire:click="sortBy('created_at')" class="text-gray-600 text-hover-primary">
                                            Created
                                            @if($sortField === 'created_at')
                                                <i class="ki-duotone ki-{{ $sortDirection === 'asc' ? 'up' : 'down' }} fs-5 ms-1">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="text-end min-w-100px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tenants as $tenant)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-5">
                                                    <div class="symbol-label bg-light-primary">
                                                        <i class="ki-duotone ki-buildings fs-2x text-primary">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-start flex-column">
                                                    <a href="{{ route('tenancy.admin.tenants.show', $tenant) }}" class="text-gray-900 fw-bold text-hover-primary fs-6">{{ $tenant->name }}</a>
                                                    <span class="text-muted fw-semibold text-muted d-block fs-7">ID: {{ $tenant->id }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($tenant->domains->count() > 0)
                                                <div class="badge badge-light-info">{{ $tenant->domains->first()->domain }}</div>
                                                @if($tenant->domains->count() > 1)
                                                    <span class="text-muted fs-7">+{{ $tenant->domains->count() - 1 }} more</span>
                                                @endif
                                            @else
                                                <span class="text-muted">No domain</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-gray-700">{{ $tenant->data['database_name'] ?? 'tenant_' . $tenant->id }}</span>
                                            <div class="text-muted fs-7">Database Name</div>
                                        </td>
                                        <td>
                                            @php
                                                $statusClasses = [
                                                    'active' => 'badge-light-success',
                                                    'inactive' => 'badge-light-warning',
                                                    'suspended' => 'badge-light-danger',
                                                    'blocked' => 'badge-light-dark'
                                                ];
                                                $statusClass = $statusClasses[$tenant->status ?? 'inactive'] ?? 'badge-light-secondary';
                                            @endphp
                                            <div class="badge {{ $statusClass }}">{{ ucfirst($tenant->status ?? 'inactive') }}</div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold">{{ $tenant->created_at->format('M d, Y') }}</span>
                                            <div class="text-muted fs-7">{{ $tenant->created_at->diffForHumans() }}</div>
                                        </td>
                                        <td class="text-end">
                                            <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                Actions
                                                <i class="ki-duotone ki-down fs-5 ms-1"></i>
                                            </a>
                                            <!--begin::Menu-->
                                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="{{ route('tenancy.admin.tenants.show', $tenant) }}" class="menu-link px-3">
                                                        View Details
                                                    </a>
                                                </div>
                                                <!--end::Menu item-->
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="#" wire:click="manageTenant({{ $tenant->id }})" class="menu-link px-3">
                                                        Manage
                                                    </a>
                                                </div>
                                                <!--end::Menu item-->
                                                <!--begin::Menu item-->
                                                @if($tenant->status === 'active')
                                                    <div class="menu-item px-3">
                                                        <a href="#" wire:click="suspendTenant({{ $tenant->id }})" class="menu-link px-3 text-warning">
                                                            Suspend
                                                        </a>
                                                    </div>
                                                @else
                                                    <div class="menu-item px-3">
                                                        <a href="#" wire:click="activateTenant({{ $tenant->id }})" class="menu-link px-3 text-success">
                                                            Activate
                                                        </a>
                                                    </div>
                                                @endif
                                                <!--end::Menu item-->
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="#" wire:click="deleteTenant({{ $tenant->id }})" class="menu-link px-3 text-danger" data-kt-users-table-filter="delete_row">
                                                        Delete
                                                    </a>
                                                </div>
                                                <!--end::Menu item-->
                                            </div>
                                            <!--end::Menu-->
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-10">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                <div class="fw-semibold fs-6 text-gray-500 mb-3">No tenants found</div>
                                                <div class="fs-7 text-gray-400">
                                                    @if($search)
                                                        Try adjusting your search criteria
                                                    @else
                                                        Create your first tenant to get started
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <!--end::Table-->
                    
                    <!--begin::Pagination-->
                    @if($tenants->hasPages())
                        <div class="d-flex flex-stack flex-wrap pt-10">
                            <div class="fs-6 fw-semibold text-gray-700">
                                viewing {{ $tenants->firstItem() ?? 0 }} to {{ $tenants->lastItem() ?? 0 }} of {{ $tenants->total() }} results
                            </div>
                            <ul class="pagination">
                                {{ $tenants->links() }}
                            </ul>
                        </div>
                    @endif
                    <!--end::Pagination-->
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->

        </div>
        <!--end::Content container-->
    </div>
    <!--end::Content-->

</div>
