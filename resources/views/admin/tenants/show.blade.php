@extends('admin.layouts.app')

@section('title', 'Tenant Details - ' . $tenant->name)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Tenant Details</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('tenancy.admin.index') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('tenancy.admin.tenants.index') }}">Tenants</a></li>
                        <li class="breadcrumb-item active">{{ $tenant->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Tenant Information Card -->
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="ri-building-2-line align-middle me-1"></i>
                        {{ $tenant->name }}
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tenant ID</label>
                                <p class="text-muted">{{ $tenant->id }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Name</label>
                                <p class="text-muted">{{ $tenant->name }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <p>
                                    @if($tenant->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @elseif($tenant->status === 'inactive')
                                        <span class="badge bg-warning">Inactive</span>
                                    @elseif($tenant->status === 'suspended')
                                        <span class="badge bg-danger">Suspended</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($tenant->status) }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Database</label>
                                <p class="text-muted">{{ $tenant->database ?? 'Default' }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Created At</label>
                                <p class="text-muted">{{ $tenant->created_at->format('M d, Y g:i A') }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Last Updated</label>
                                <p class="text-muted">{{ $tenant->updated_at->format('M d, Y g:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    @if($tenant->domains && $tenant->domains->count() > 0)
                    <div class="row">
                        <div class="col-12">
                            <h5 class="mb-3">Domains</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tenant->domains as $domain)
                                        <tr>
                                            <td>{{ $domain->domain }}</td>
                                            <td>{{ $domain->created_at->format('M d, Y') }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="ri-information-line me-2"></i>
                        No domains configured for this tenant.
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Tenant Statistics -->
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="ri-bar-chart-2-line align-middle me-1"></i>
                        Statistics
                    </h4>
                </div>
                <div class="card-body">
                    @if(isset($metrics))
                    <div class="mb-3">
                        <small class="text-muted">Database Health</small>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Status</span>
                            <span class="badge bg-{{ $metrics['health']['status'] === 'healthy' ? 'success' : 'warning' }}">
                                {{ ucfirst($metrics['health']['status']) }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Database</small>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Tables</span>
                            <span>{{ $metrics['database']['table_count'] ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Records</span>
                            <span>{{ number_format($metrics['database']['total_records'] ?? 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Size</span>
                            <span>{{ $metrics['database']['database_size'] ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Performance</small>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Avg Response</span>
                            <span>{{ $metrics['performance']['avg_response_time'] ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Uptime</span>
                            <span>{{ $metrics['performance']['uptime_percentage'] ?? 'N/A' }}</span>
                        </div>
                    </div>
                    @else
                    <div class="text-center text-muted">
                        <i class="ri-information-line fs-1 mb-2"></i>
                        <p>Statistics not available</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="ri-settings-2-line align-middle me-1"></i>
                        Actions
                    </h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('tenancy.tenants.edit', $tenant->id) }}" class="btn btn-soft-primary">
                            <i class="ri-edit-2-line me-1"></i> Edit Tenant
                        </a>
                        
                        @if($tenant->status === 'active')
                        <button type="button" class="btn btn-soft-warning" onclick="toggleTenantStatus('{{ $tenant->id }}', 'inactive')">
                            <i class="ri-pause-circle-line me-1"></i> Deactivate
                        </button>
                        @else
                        <button type="button" class="btn btn-soft-success" onclick="toggleTenantStatus('{{ $tenant->id }}', 'active')">
                            <i class="ri-play-circle-line me-1"></i> Activate
                        </button>
                        @endif

                        <div class="dropdown">
                            <button class="btn btn-soft-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                                <i class="ri-more-2-line me-1"></i> More Actions
                            </button>
                            <ul class="dropdown-menu w-100">
                                <li><a class="dropdown-item" href="#"><i class="ri-download-2-line me-2"></i>Export Data</a></li>
                                <li><a class="dropdown-item" href="#"><i class="ri-database-2-line me-2"></i>Database Backup</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger" onclick="deleteTenant('{{ $tenant->id }}')">
                                        <i class="ri-delete-bin-line me-2"></i>Delete Tenant
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteTenantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-error-warning-line text-danger me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the tenant "<strong>{{ $tenant->name }}</strong>"?</p>
                <p class="text-danger">⚠️ This action cannot be undone and will permanently remove all tenant data.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteTenantForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Tenant</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteTenant(tenantId) {
    const form = document.getElementById('deleteTenantForm');
    form.action = `{{ route('tenancy.tenants.destroy', ':id') }}`.replace(':id', tenantId);
    new bootstrap.Modal(document.getElementById('deleteTenantModal')).show();
}

function toggleTenantStatus(tenantId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this tenant?`)) {
        // You would typically make an AJAX request here to update the status
        fetch(`/af-tenancy-api/tenants/${tenantId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to update tenant status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating tenant status');
        });
    }
}
</script>
@endsection
