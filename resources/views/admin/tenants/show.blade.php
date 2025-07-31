@extends('layouts.admin.app')

@section('title', 'Tenant Details - ' . $tenant->name)

@push('page-title')
    Tenant Management - {{ $tenant->name }}
@endpush

@section('content')
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-fluid">
        
        <!-- Tenant Overview Card -->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-md-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <div class="card-title">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-circle symbol-50px overflow-hidden me-3">
                                    <div class="symbol-label">
                                        <div class="symbol-label fs-2 bg-light-primary text-primary">
                                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column">
                                    <h2 class="text-gray-800 mb-1">{{ $tenant->name }}</h2>
                                    <span class="text-muted fs-7">{{ $tenant->uuid }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-light">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenant Information Cards -->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <!-- Status & Basic Info -->
            <div class="col-xl-6">
                <div class="card card-flush h-lg-100">
                    <div class="card-header pt-5">
                        <h3 class="card-title">Tenant Information</h3>
                    </div>
                    <div class="card-body pt-5">
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Status:</div>
                            <div class="d-flex align-items-center">
                                @if($tenant->status === 'active')
                                    <span class="badge badge-light-success">Active</span>
                                @elseif($tenant->status === 'suspended')
                                    <span class="badge badge-light-warning">Suspended</span>
                                @elseif($tenant->status === 'blocked')
                                    <span class="badge badge-light-danger">Blocked</span>
                                @else
                                    <span class="badge badge-light-secondary">Inactive</span>
                                @endif
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Database:</div>
                            <div class="d-flex align-items-center">
                                <span class="text-gray-900 fw-bolder fs-6">{{ $tenant->database_name }}</span>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Created:</div>
                            <div class="d-flex align-items-center">
                                <span class="text-gray-900 fw-bolder fs-6">{{ $tenant->created_at->format('M d, Y H:i') }}</span>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Last Updated:</div>
                            <div class="d-flex align-items-center">
                                <span class="text-gray-900 fw-bolder fs-6">{{ $tenant->updated_at->format('M d, Y H:i') }}</span>
                            </div>
                        </div>
                        
                        @if($tenant->notes)
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex flex-stack">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Notes:</div>
                            <div class="d-flex align-items-center">
                                <span class="text-gray-900 fs-6">{{ $tenant->notes }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Migration Status -->
            <div class="col-xl-6">
                <div class="card card-flush h-lg-100">
                    <div class="card-header pt-5">
                        <h3 class="card-title">Database Status</h3>
                    </div>
                    <div class="card-body pt-5">
                        @if($migrationStatus)
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Migration Status:</div>
                            <div class="d-flex align-items-center">
                                @if($migrationStatus['status'] === 'up_to_date')
                                    <span class="badge badge-light-success">
                                        <i class="ki-duotone ki-check-circle fs-7 me-1"></i>Up to Date
                                    </span>
                                @elseif($migrationStatus['status'] === 'pending_migrations')
                                    <span class="badge badge-light-warning">
                                        <i class="ki-duotone ki-time fs-7 me-1"></i>{{ $migrationStatus['pending_count'] }} Pending
                                    </span>
                                @elseif($migrationStatus['status'] === 'not_migrated')
                                    <span class="badge badge-light-info">
                                        <i class="ki-duotone ki-information fs-7 me-1"></i>Not Migrated
                                    </span>
                                @elseif($migrationStatus['status'] === 'database_missing')
                                    <span class="badge badge-light-danger">
                                        <i class="ki-duotone ki-cross-circle fs-7 me-1"></i>Database Missing
                                    </span>
                                @else
                                    <span class="badge badge-light-dark">
                                        <i class="ki-duotone ki-warning fs-7 me-1"></i>Error
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Total Migrations:</div>
                            <div class="d-flex align-items-center">
                                <span class="text-gray-900 fw-bolder fs-6">{{ $migrationStatus['total_count'] }}</span>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        
                        <div class="d-flex flex-stack mb-4">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Pending:</div>
                            <div class="d-flex align-items-center">
                                <span class="text-gray-900 fw-bolder fs-6">{{ $migrationStatus['pending_count'] }}</span>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-3"></div>
                        
                        <div class="d-flex flex-stack">
                            <div class="text-gray-700 fw-semibold fs-6 me-2">Database Exists:</div>
                            <div class="d-flex align-items-center">
                                @if($migrationStatus['database_exists'])
                                    <span class="badge badge-light-success">Yes</span>
                                @else
                                    <span class="badge badge-light-danger">No</span>
                                @endif
                            </div>
                        </div>
                        @else
                        <div class="text-center">
                            <span class="text-muted">Unable to determine migration status</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Domains -->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-md-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <h3 class="card-title">Domains</h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-primary" onclick="showAddDomainForm()">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Add Domain
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Add Domain Form (Hidden by default) -->
                        <div id="addDomainForm" class="mb-5" style="display: none;">
                            <div class="border border-gray-300 rounded p-4 bg-light">
                                <h6 class="mb-3">Add New Domain</h6>
                                <form onsubmit="addDomain(event, '{{ $tenant->uuid }}')">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="newDomainInput" 
                                               placeholder="Enter domain (e.g., example.com)" required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ki-duotone ki-plus fs-7"></i> Add
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="hideAddDomainForm()">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @if($tenant->domains->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 gy-7">
                                    <thead>
                                        <tr class="fw-bold fs-6 text-gray-800">
                                            <th>Domain</th>
                                            <th>Created</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tenant->domains as $domain)
                                        <tr>
                                            <td>
                                                <span class="text-gray-800 fw-bold">{{ $domain->domain }}</span>
                                                @if($loop->first)
                                                    <span class="badge badge-light-primary ms-2">Primary</span>
                                                @endif
                                            </td>
                                            <td>{{ $domain->created_at->format('M d, Y') }}</td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-light-danger" onclick="removeDomain('{{ $domain->id }}')">
                                                    <i class="ki-duotone ki-trash fs-7"></i>
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <div class="text-gray-400 fs-4 mb-3">No domains configured</div>
                                <button type="button" class="btn btn-primary" onclick="showAddDomainForm()">
                                    <i class="ki-duotone ki-plus fs-2"></i>
                                    Add First Domain
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-md-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <h3 class="card-title">Tenant Statistics</h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-light" onclick="refreshStatistics('{{ $tenant->uuid }}')">
                                <i class="ki-duotone ki-arrows-loop fs-2"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body" id="statisticsContent">
                        <div class="row g-5">
                            <div class="col-lg-3 col-sm-6">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-user fs-1 text-primary"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-2">{{ $statistics['active_users'] }}</div>
                                        <div class="text-muted fs-7">Active Users</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-info">
                                            <i class="ki-duotone ki-database fs-1 text-info"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-2">{{ $statistics['database_size'] }}</div>
                                        <div class="text-muted fs-7">Database Size</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-success">
                                            <i class="ki-duotone ki-element-11 fs-1 text-success"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-2">{{ $statistics['total_tables'] }}</div>
                                        <div class="text-muted fs-7">Total Tables</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-warning">
                                            <i class="ki-duotone ki-clock fs-1 text-warning"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-2">{{ $statistics['last_activity'] }}</div>
                                        <div class="text-muted fs-7">Last Activity</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-md-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <h3 class="card-title">Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Database Actions -->
                            <div class="col-md-6">
                                <h5 class="text-gray-800 mb-4">Database Management</h5>
                                <div class="d-flex flex-wrap gap-3 mb-4">
                                    <button type="button" class="btn btn-primary" onclick="migrateTenant('{{ $tenant->uuid }}')">
                                        <i class="ki-duotone ki-arrows-loop fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Run Migrations
                                    </button>
                                    
                                    <button type="button" class="btn btn-info" onclick="seedTenant('{{ $tenant->uuid }}')">
                                        <i class="ki-duotone ki-seed fs-2"></i>
                                        Seed Database
                                    </button>
                                    
                                    <button type="button" class="btn btn-warning" onclick="resetTenant('{{ $tenant->uuid }}')">
                                        <i class="ki-duotone ki-arrows-circle fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Reset Database
                                    </button>
                                </div>
                            </div>

                            <!-- Status Actions -->
                            <div class="col-md-6">
                                <h5 class="text-gray-800 mb-4">Status Management</h5>
                                <div class="d-flex flex-wrap gap-3">
                                    @if($tenant->status !== 'active')
                                    <button type="button" class="btn btn-success" onclick="updateTenantStatus('{{ $tenant->uuid }}', 'active')">
                                        <i class="ki-duotone ki-check-circle fs-2"></i>
                                        Activate
                                    </button>
                                    @endif
                                    
                                    @if($tenant->status !== 'suspended')
                                    <button type="button" class="btn btn-warning" onclick="updateTenantStatus('{{ $tenant->uuid }}', 'suspended')">
                                        <i class="ki-duotone ki-time fs-2"></i>
                                        Suspend
                                    </button>
                                    @endif
                                    
                                    @if($tenant->status !== 'blocked')
                                    <button type="button" class="btn btn-danger" onclick="updateTenantStatus('{{ $tenant->uuid }}', 'blocked')">
                                        <i class="ki-duotone ki-cross-circle fs-2"></i>
                                        Block
                                    </button>
                                    @endif
                                    
                                    @if($tenant->status !== 'inactive')
                                    <button type="button" class="btn btn-secondary" onclick="updateTenantStatus('{{ $tenant->uuid }}', 'inactive')">
                                        <i class="ki-duotone ki-minus-circle fs-2"></i>
                                        Deactivate
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="separator my-5"></div>
                        
                        <!-- Danger Zone -->
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="text-danger mb-4">Danger Zone</h5>
                                <div class="d-flex flex-wrap gap-3">
                                    <button type="button" class="btn btn-light-danger" onclick="deleteTenant('{{ $tenant->uuid }}')">
                                        <i class="ki-duotone ki-trash fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                        Delete Tenant
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        @if($stats)
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-md-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <h3 class="card-title">Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-40px me-3">
                                        <div class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-people fs-1 text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fs-4 fw-bold">{{ $stats['active_users'] ?? 0 }}</span>
                                        <span class="text-muted fs-7">Active Users</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-40px me-3">
                                        <div class="symbol-label bg-light-info">
                                            <i class="ki-duotone ki-storage fs-1 text-info"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fs-4 fw-bold">{{ $stats['database_size'] ?? 'N/A' }}</span>
                                        <span class="text-muted fs-7">Database Size</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-40px me-3">
                                        <div class="symbol-label bg-light-success">
                                            <i class="ki-duotone ki-check-circle fs-1 text-success"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fs-4 fw-bold">{{ $stats['total_tables'] ?? 0 }}</span>
                                        <span class="text-muted fs-7">Database Tables</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-40px me-3">
                                        <div class="symbol-label bg-light-warning">
                                            <i class="ki-duotone ki-time fs-1 text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fs-4 fw-bold">{{ $stats['last_activity'] ?? 'Never' }}</span>
                                        <span class="text-muted fs-7">Last Activity</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-10">
                <span class="spinner-border spinner-border-lg text-primary" role="status"></span>
                <div class="text-gray-600 fs-6 fw-semibold mt-5" id="loadingText">Processing...</div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function showLoading(text = 'Processing...') {
    document.getElementById('loadingText').textContent = text;
    $('#loadingModal').modal('show');
}

function hideLoading() {
    $('#loadingModal').modal('hide');
}

function migrateTenant(uuid) {
    if (!confirm('Are you sure you want to run migrations for this tenant?')) {
        return;
    }
    
    showLoading('Running tenant migrations...');
    
    fetch(`/admin/tenants/${uuid}/migrate`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Tenant migrations completed successfully');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error('Migration failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Migration failed: ' + error.message);
    });
}

function seedTenant(uuid) {
    if (!confirm('Are you sure you want to seed this tenant database?')) {
        return;
    }
    
    showLoading('Seeding tenant database...');
    
    fetch(`/admin/tenants/${uuid}/seed`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Tenant database seeded successfully');
        } else {
            toastr.error('Seeding failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Seeding failed: ' + error.message);
    });
}

function resetTenant(uuid) {
    if (!confirm('Are you sure you want to reset this tenant database? This will drop all tables and re-run migrations.')) {
        return;
    }
    
    showLoading('Resetting tenant database...');
    
    fetch(`/admin/tenants/${uuid}/reset`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Tenant database reset successfully');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error('Reset failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Reset failed: ' + error.message);
    });
}

function updateTenantStatus(uuid, status) {
    const statusLabels = {
        'active': 'activate',
        'suspended': 'suspend',
        'blocked': 'block',
        'inactive': 'deactivate'
    };
    
    if (!confirm(`Are you sure you want to ${statusLabels[status]} this tenant?`)) {
        return;
    }
    
    showLoading(`Updating tenant status to ${status}...`);
    
    fetch(`/admin/tenants/${uuid}/status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success(`Tenant ${statusLabels[status]}d successfully`);
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error('Status update failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Status update failed: ' + error.message);
    });
}

function deleteTenant(uuid) {
    if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
        return;
    }
    
    if (!confirm('This will permanently delete all tenant data. Are you absolutely sure?')) {
        return;
    }
    
    showLoading('Deleting tenant...');
    
    fetch(`/admin/tenants/${uuid}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Tenant deleted successfully');
            setTimeout(() => window.location.href = '/admin/dashboard', 1500);
        } else {
            toastr.error('Deletion failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Deletion failed: ' + error.message);
    });
}

function addDomain(event, tenantUuid) {
    event.preventDefault();
    
    const domain = document.getElementById('newDomainInput').value.trim();
    if (!domain) {
        toastr.error('Please enter a domain');
        return;
    }
    
    showLoading('Adding domain...');
    
    fetch(`/admin/tenants/${tenantUuid}/domains`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ domain: domain })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Domain added successfully');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error('Failed to add domain: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Failed to add domain: ' + error.message);
    });
}

function showAddDomainForm() {
    document.getElementById('addDomainForm').style.display = 'block';
    document.getElementById('newDomainInput').focus();
}

function hideAddDomainForm() {
    document.getElementById('addDomainForm').style.display = 'none';
    document.getElementById('newDomainInput').value = '';
}

function resetTenant(tenantUuid) {
    if (!confirm('Are you sure you want to reset this tenant\'s database? This will DROP ALL TABLES and re-run migrations. This action cannot be undone!')) {
        return;
    }
    
    showLoading('Resetting tenant database...');
    
    fetch(`/admin/tenants/${tenantUuid}/reset`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Database reset successfully');
            setTimeout(() => location.reload(), 2000);
        } else {
            toastr.error('Failed to reset database: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Failed to reset database: ' + error.message);
    });
}

function refreshStatistics(tenantUuid) {
    showLoading('Refreshing statistics...');
    
    fetch(`/admin/tenants/${tenantUuid}`)
    .then(response => response.text())
    .then(html => {
        hideLoading();
        // Parse the HTML to extract just the statistics content
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newStatsContent = doc.getElementById('statisticsContent');
        
        if (newStatsContent) {
            document.getElementById('statisticsContent').innerHTML = newStatsContent.innerHTML;
            toastr.success('Statistics refreshed');
        } else {
            toastr.error('Failed to refresh statistics');
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Failed to refresh statistics: ' + error.message);
    });
}

function removeDomain(domainId) {
    if (!confirm('Are you sure you want to remove this domain?')) {
        return;
    }
    
    showLoading('Removing domain...');
    
    fetch(`/admin/domains/${domainId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            toastr.success('Domain removed successfully');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error('Failed to remove domain: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        toastr.error('Failed to remove domain: ' + error.message);
    });
}
</script>
@endpush
