@extends('layouts.admin.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">

            <!-- Header Stats -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-12">
                    <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <div class="d-flex align-items-center">
                                    <span class="fs-4 fw-semibold text-gray-400 me-1 align-self-start">#</span>
                                    <span
                                        class="fs-2hx fw-bold text-dark me-2 lh-1 ls-n2">{{ $enhancedStats['total_tenants'] }}</span>
                                </div>
                                <span class="text-gray-400 pt-1 fw-semibold fs-6">Total Tenants</span>
                            </div>
                        </div>
                        <div class="card-body pt-2 pb-4 d-flex flex-wrap align-items-center">
                            <div class="d-flex flex-center me-5 pt-2">
                                <div id="kt_card_widget_17_chart" style="min-width: 70px; min-height: 70px"
                                    data-kt-size="70" data-kt-line="11"></div>
                            </div>
                            <div class="d-flex flex-column content-justify-center flex-row-fluid">
                                <div class="d-flex fw-semibold align-items-center">
                                    <div class="bullet w-8px h-3px rounded-2 bg-success me-3"></div>
                                    <div class="text-gray-500 flex-1 fs-6">Active</div>
                                    <div class="fw-bolder text-gray-700 text-xxl-end">{{ $enhancedStats['active_tenants'] }}
                                    </div>
                                </div>
                                <div class="d-flex fw-semibold align-items-center my-3">
                                    <div class="bullet w-8px h-3px rounded-2 bg-danger me-3"></div>
                                    <div class="text-gray-500 flex-1 fs-6">Inactive</div>
                                    <div class="fw-bolder text-gray-700 text-xxl-end">
                                        {{ $enhancedStats['inactive_tenants'] }}</div>
                                </div>
                                <div class="d-flex fw-semibold align-items-center">
                                    <div class="bullet w-8px h-3px rounded-2 bg-primary me-3"></div>
                                    <div class="text-gray-500 flex-1 fs-6">Domains</div>
                                    <div class="fw-bolder text-gray-700 text-xxl-end">{{ $enhancedStats['total_domains'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!-- Connections Card -->
                <div class="col-xxl-6">
                    <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span
                                    class="fs-2hx fw-bold text-dark me-2 lh-1 ls-n2">{{ $enhancedStats['total_connections'] }}</span>
                                <span class="text-gray-400 pt-1 fw-semibold fs-6">Database Connections</span>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-end pe-0">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted fw-semibold fs-7">Persistent:</span>
                                <span class="fw-bold fs-6">{{ $enhancedStats['persistent_connections'] }}</span>
                            </div>
                            <div class="progress h-6px w-100">
                                <div class="progress-bar bg-primary" role="progressbar"
                                    style="width: {{ ($enhancedStats['persistent_connections'] / max($enhancedStats['total_connections'], 1)) * 100 }}%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cache Information -->
                <div class="col-xxl-6">
                    <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span
                                    class="fs-2hx fw-bold text-dark me-2 lh-1 ls-n2">{{ $enhancedStats['cache_size']['total_keys'] }}</span>
                                <span class="text-gray-400 pt-1 fw-semibold fs-6">Cache Keys</span>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-end pe-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted fw-semibold fs-7">Memory Used:</span>
                                <span class="fw-bold fs-6">{{ $enhancedStats['cache_size']['used_memory'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted fw-semibold fs-7">Peak Memory:</span>
                                <span class="fw-bold fs-6">{{ $enhancedStats['cache_size']['used_memory_peak'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Migration Status Overview -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-12">
                    <div class="card card-flush">
                        <div class="card-header">
                            <h3 class="card-title">Database Migration Status</h3>
                            <div class="card-toolbar">
                                <button type="button" class="btn btn-sm btn-light-primary"
                                    onclick="refreshMigrationStatus()">
                                    <i class="ki-duotone ki-arrows-circle fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <div class="row">
                                @php
                                    $migrationStats = [
                                        'up_to_date' => collect($enhancedStats['migration_status'])->where('status', 'up_to_date')->count(),
                                        'pending_migrations' => collect($enhancedStats['migration_status'])->where('status', 'pending_migrations')->count(),
                                        'not_migrated' => collect($enhancedStats['migration_status'])->where('status', 'not_migrated')->count(),
                                        'database_missing' => collect($enhancedStats['migration_status'])->where('status', 'database_missing')->count(),
                                        'error' => collect($enhancedStats['migration_status'])->where('status', 'error')->count(),
                                    ];
                                @endphp

                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3">
                                            <div class="symbol-label bg-light-success">
                                                <i class="ki-duotone ki-check-circle fs-1 text-success"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fs-4 fw-bold">{{ $migrationStats['up_to_date'] }}</span>
                                            <span class="text-muted fs-7">Up to Date</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3">
                                            <div class="symbol-label bg-light-warning">
                                                <i class="ki-duotone ki-time fs-1 text-warning"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fs-4 fw-bold">{{ $migrationStats['pending_migrations'] }}</span>
                                            <span class="text-muted fs-7">Pending</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3">
                                            <div class="symbol-label bg-light-info">
                                                <i class="ki-duotone ki-information fs-1 text-info"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fs-4 fw-bold">{{ $migrationStats['not_migrated'] }}</span>
                                            <span class="text-muted fs-7">Not Migrated</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3">
                                            <div class="symbol-label bg-light-danger">
                                                <i class="ki-duotone ki-cross-circle fs-1 text-danger"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fs-4 fw-bold">{{ $migrationStats['database_missing'] }}</span>
                                            <span class="text-muted fs-7">DB Missing</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3">
                                            <div class="symbol-label bg-light-dark">
                                                <i class="ki-duotone ki-warning fs-1 text-dark"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fs-4 fw-bold">{{ $migrationStats['error'] }}</span>
                                            <span class="text-muted fs-7">Errors</span>
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
                            <h3 class="card-title">System Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3">
                                <button type="button" class="btn btn-primary" onclick="migrateAllTenants()">
                                    <i class="ki-duotone ki-arrows-loop fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Migrate All Tenants
                                </button>

                                <button type="button" class="btn btn-warning" onclick="clearAllCaches()">
                                    <i class="ki-duotone ki-trash fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                    Clear All Caches
                                </button>

                                <a href="{{ route('admin.tenants.create') }}" class="btn btn-success">
                                    <i class="ki-duotone ki-plus fs-2"></i>
                                    Create New Tenant
                                </a>

                                <button type="button" class="btn btn-info" onclick="refreshDashboard()">
                                    <i class="ki-duotone ki-arrows-circle fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Refresh Dashboard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenants Management Table -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-12">
                    <div class="card card-flush">
                        <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                            <div class="card-title">
                                <div class="d-flex align-items-center position-relative my-1">
                                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <input type="text" data-kt-filter="search"
                                        class="form-control form-control-solid w-250px ps-12"
                                        placeholder="Search Tenants" />
                                </div>
                            </div>
                            <div class="card-toolbar">
                                <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                                    <button type="button" class="btn btn-light-primary me-3"
                                        onclick="refreshTenantsTable()">
                                        <i class="ki-duotone ki-arrows-circle fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="tenants_table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-125px">Tenant</th>
                                        <th class="min-w-125px">Domain</th>
                                        <th class="min-w-125px">Status</th>
                                        <th class="min-w-100px">Migration</th>
                                        <th class="min-w-100px">Active Users</th>
                                        <th class="min-w-100px">DB Size</th>
                                        <th class="min-w-100px">Created</th>
                                        <th class="text-end min-w-100px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    @foreach($tenants as $tenant)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-circle symbol-50px overflow-hidden me-3">
                                                        <div class="symbol-label">
                                                            <div class="symbol-label fs-3 bg-light-primary text-primary">
                                                                {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span
                                                            class="text-gray-800 text-hover-primary mb-1">{{ $tenant->name }}</span>
                                                        <span class="text-muted fs-7">{{ $tenant->uuid }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($tenant->domains && $tenant->domains->count() > 0)
                                                    @foreach($tenant->domains as $domain)
                                                        <span class="badge badge-light-info fs-7 m-1">
                                                            {{ is_object($domain) ? ($domain->domain ?? $domain) : $domain }}
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">No domains</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $status = $tenant->status ?? 'active';
                                                @endphp
                                                @if($status === 'active')
                                                    <span class="badge badge-light-success">Active</span>
                                                @elseif($status === 'suspended')
                                                    <span class="badge badge-light-warning">Suspended</span>
                                                @elseif($status === 'blocked')
                                                    <span class="badge badge-light-danger">Blocked</span>
                                                @else
                                                    <span class="badge badge-light-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $migrationStatus = collect($enhancedStats['migration_status'])->where('tenant_id', $tenant->id)->first();
                                                @endphp
                                                @if($migrationStatus)
                                                    @if($migrationStatus['status'] === 'up_to_date')
                                                        <span class="badge badge-light-success">
                                                            <i class="ki-duotone ki-check-circle fs-7 me-1"></i>Up to Date
                                                        </span>
                                                    @elseif($migrationStatus['status'] === 'pending_migrations')
                                                        <span class="badge badge-light-warning">
                                                            <i
                                                                class="ki-duotone ki-time fs-7 me-1"></i>{{ $migrationStatus['pending_migrations'] }}
                                                            Pending
                                                        </span>
                                                    @elseif($migrationStatus['status'] === 'not_migrated')
                                                        <span class="badge badge-light-info">
                                                            <i class="ki-duotone ki-information fs-7 me-1"></i>Not Migrated
                                                        </span>
                                                    @elseif($migrationStatus['status'] === 'database_missing')
                                                        <span class="badge badge-light-danger">
                                                            <i class="ki-duotone ki-cross-circle fs-7 me-1"></i>DB Missing
                                                        </span>
                                                    @else
                                                        <span class="badge badge-light-dark">
                                                            <i class="ki-duotone ki-warning fs-7 me-1"></i>Error
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge badge-light-secondary">Unknown</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-light-primary" id="active-users-{{ $tenant->id }}">
                                                    {{ collect($enhancedStats['active_users_per_tenant'])->where('tenant_id', $tenant->id)->first()['active_users'] ?? 0 }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-dark fw-bold">
                                                    {{ collect($enhancedStats['database_sizes'])->where('tenant_id', $tenant->id)->first()['size_formatted'] ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td>{{ $tenant->created_at->format('M d, Y') }}</td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end flex-shrink-0">
                                                    <!-- View Button -->
                                                    <a href="{{ route('admin.tenants.show', $tenant->uuid) }}" 
                                                       class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1"
                                                       data-bs-toggle="tooltip" title="View Details">
                                                        <i class="ki-duotone ki-eye fs-3">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                            <span class="path3"></span>
                                                        </i>
                                                    </a>
                                                    
                                                    <!-- Edit Button -->
                                                    <a href="{{ route('admin.tenants.edit', $tenant->uuid) }}" 
                                                       class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1"
                                                       data-bs-toggle="tooltip" title="Edit Tenant">
                                                        <i class="ki-duotone ki-pencil fs-3">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                    </a>
                                                    
                                                    <!-- Migration Button -->
                                                    @php
                                                        $migrationStatus = collect($enhancedStats['migration_status'])->where('tenant_id', $tenant->id)->first();
                                                        $needsMigration = $migrationStatus && in_array($migrationStatus['status'], ['pending_migrations', 'not_migrated', 'database_missing']);
                                                    @endphp
                                                    <button type="button" 
                                                            class="btn btn-icon btn-bg-light btn-active-color-{{ $needsMigration ? 'warning' : 'success' }} btn-sm me-1"
                                                            onclick="migrateTenant('{{ $tenant->uuid }}')"
                                                            data-bs-toggle="tooltip" title="Run Migrations">
                                                        <i class="ki-duotone ki-arrows-loop fs-3">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                    </button>
                                                    
                                                    <!-- Status Toggle Button -->
                                                    @if($tenant->status === 'active')
                                                        <button type="button" 
                                                                class="btn btn-icon btn-bg-light btn-active-color-warning btn-sm me-1"
                                                                onclick="updateTenantStatus('{{ $tenant->uuid }}', 'suspended')"
                                                                data-bs-toggle="tooltip" title="Suspend Tenant">
                                                            <i class="ki-duotone ki-time fs-3">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </button>
                                                    @elseif($tenant->status === 'suspended')
                                                        <button type="button" 
                                                                class="btn btn-icon btn-bg-light btn-active-color-success btn-sm me-1"
                                                                onclick="updateTenantStatus('{{ $tenant->uuid }}', 'active')"
                                                                data-bs-toggle="tooltip" title="Activate Tenant">
                                                            <i class="ki-duotone ki-check-circle fs-3">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </button>
                                                    @elseif($tenant->status === 'blocked')
                                                        <button type="button" 
                                                                class="btn btn-icon btn-bg-light btn-active-color-success btn-sm me-1"
                                                                onclick="updateTenantStatus('{{ $tenant->uuid }}', 'active')"
                                                                data-bs-toggle="tooltip" title="Activate Tenant">
                                                            <i class="ki-duotone ki-check-circle fs-3">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </button>
                                                    @else
                                                        <button type="button" 
                                                                class="btn btn-icon btn-bg-light btn-active-color-success btn-sm me-1"
                                                                onclick="updateTenantStatus('{{ $tenant->uuid }}', 'active')"
                                                                data-bs-toggle="tooltip" title="Activate Tenant">
                                                            <i class="ki-duotone ki-check-circle fs-3">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </button>
                                                    @endif
                                                    
                                                    <!-- More Actions Dropdown -->
                                                    <div class="me-0">
                                                        <button class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm" 
                                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end"
                                                                data-bs-toggle="tooltip" title="More Actions">
                                                            <i class="ki-duotone ki-dots-horizontal fs-3">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                            </i>
                                                        </button>
                                                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4"
                                                             data-kt-menu="true">
                                                            <div class="menu-item px-3">
                                                                <a href="#" class="menu-link px-3"
                                                                   onclick="seedTenant('{{ $tenant->uuid }}')">
                                                                    <i class="ki-duotone ki-seed fs-7 me-2"></i>Seed Database
                                                                </a>
                                                            </div>
                                                            @if($tenant->status !== 'blocked')
                                                                <div class="menu-item px-3">
                                                                    <a href="#" class="menu-link px-3 text-danger"
                                                                       onclick="updateTenantStatus('{{ $tenant->uuid }}', 'blocked')">
                                                                        <i class="ki-duotone ki-cross-circle fs-7 me-2"></i>Block Tenant
                                                                    </a>
                                                                </div>
                                                            @endif
                                                            <div class="separator my-3"></div>
                                                            <div class="menu-item px-3">
                                                                <a href="#" class="menu-link px-3 text-danger"
                                                                   onclick="deleteTenant('{{ $tenant->uuid }}')">
                                                                    <i class="ki-duotone ki-trash fs-7 me-2"></i>Delete Tenant
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-6">
                    <div class="card card-flush h-lg-100">
                        <div class="card-header pt-5">
                            <h3 class="card-title text-gray-800">System Information</h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">Database Server:</div>
                                <div class="d-flex align-items-senter">
                                    <span
                                        class="text-gray-900 fw-bolder fs-6">{{ $systemInfo['database_version'] ?? 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="separator separator-dashed my-3"></div>

                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">PHP Version:</div>
                                <div class="d-flex align-items-senter">
                                    <span class="text-gray-900 fw-bolder fs-6">{{ PHP_VERSION }}</span>
                                </div>
                            </div>
                            <div class="separator separator-dashed my-3"></div>

                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">Laravel Version:</div>
                                <div class="d-flex align-items-senter">
                                    <span class="text-gray-900 fw-bolder fs-6">{{ app()->version() }}</span>
                                </div>
                            </div>
                            <div class="separator separator-dashed my-3"></div>

                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">System Uptime:</div>
                                <div class="d-flex align-items-senter">
                                    <span
                                        class="text-gray-900 fw-bolder fs-6">{{ $enhancedStats['last_activity']['system_uptime'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-flush h-lg-100">
                        <div class="card-header pt-5">
                            <h3 class="card-title text-gray-800">Recent Activity</h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">Last Tenant Created:</div>
                                <div class="d-flex align-items-senter">
                                    <span
                                        class="text-gray-900 fw-bolder fs-6">{{ $enhancedStats['last_activity']['last_tenant_created'] }}</span>
                                </div>
                            </div>
                            <div class="separator separator-dashed my-3"></div>

                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">Last User Login:</div>
                                <div class="d-flex align-items-senter">
                                    <span
                                        class="text-gray-900 fw-bolder fs-6">{{ $enhancedStats['last_activity']['last_login'] }}</span>
                                </div>
                            </div>
                            <div class="separator separator-dashed my-3"></div>

                            <div class="d-flex flex-stack">
                                <div class="text-gray-700 fw-semibold fs-6 me-2">Total Database Size:</div>
                                <div class="d-flex align-items-senter">
                                    <span class="text-gray-900 fw-bolder fs-6">
                                        {{ number_format(collect($enhancedStats['database_sizes'])->sum('size_mb'), 2) }} MB
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
        $(document).ready(function () {
            // Initialize search functionality
            const searchInput = document.querySelector('[data-kt-filter="search"]');
            if (searchInput) {
                searchInput.addEventListener('keyup', function (e) {
                    const value = e.target.value.toLowerCase();
                    const rows = document.querySelectorAll('#tenants_table tbody tr');

                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(value) ? '' : 'none';
                    });
                });
            }

            // Auto-refresh dashboard every 30 seconds
            setInterval(function () {
                refreshTenantsTable();
            }, 30000);
        });

        function showLoading(text = 'Processing...') {
            document.getElementById('loadingText').textContent = text;
            $('#loadingModal').modal('show');
        }

        function hideLoading() {
            $('#loadingModal').modal('hide');
        }

        function migrateAllTenants() {
            if (!confirm('Are you sure you want to run migrations for all tenants? This may take some time.')) {
                return;
            }

            showLoading('Running migrations for all tenants...');

            fetch('{{ route("admin.migrate.all") }}', {
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
                        toastr.success('Migrations completed successfully');
                        refreshDashboard();
                    } else {
                        toastr.error('Migration failed: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    toastr.error('Migration failed: ' + error.message);
                });
        }

        function clearAllCaches() {
            if (!confirm('Are you sure you want to clear all caches?')) {
                return;
            }

            showLoading('Clearing all caches...');

            fetch('{{ route("admin.cache.clear.all") }}', {
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
                        toastr.success('All caches cleared successfully');
                        refreshDashboard();
                    } else {
                        toastr.error('Cache clear failed: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    toastr.error('Cache clear failed: ' + error.message);
                });
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

        function activateTenant(uuid) {
            showLoading('Activating tenant...');

            fetch(`/admin/tenants/${uuid}/activate`, {
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
                        toastr.success('Tenant activated successfully');
                        refreshDashboard();
                    } else {
                        toastr.error('Activation failed: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    toastr.error('Activation failed: ' + error.message);
                });
        }

        function suspendTenant(uuid) {
            if (!confirm('Are you sure you want to suspend this tenant?')) {
                return;
            }

            showLoading('Suspending tenant...');

            fetch(`/admin/tenants/${uuid}/suspend`, {
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
                        toastr.success('Tenant suspended successfully');
                        refreshDashboard();
                    } else {
                        toastr.error('Suspension failed: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    toastr.error('Suspension failed: ' + error.message);
                });
        }

        function deleteTenant(uuid) {
            if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
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
                        refreshDashboard();
                    } else {
                        toastr.error('Deletion failed: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    toastr.error('Deletion failed: ' + error.message);
                });
        }

        function refreshDashboard() {
            location.reload();
        }

        function refreshTenantsTable() {
            // Update active users count for each tenant
            fetch('/admin/live-stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.active_users_per_tenant) {
                        data.data.active_users_per_tenant.forEach(tenant => {
                            const element = document.getElementById(`active-users-${tenant.tenant_id}`);
                            if (element) {
                                element.textContent = tenant.active_users;
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Failed to refresh tenant stats:', error);
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
                        refreshDashboard();
                    } else {
                        toastr.error('Status update failed: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    toastr.error('Status update failed: ' + error.message);
                });
        }

        function refreshMigrationStatus() {
            showLoading('Checking migration status...');

            setTimeout(() => {
                hideLoading();
                refreshDashboard();
            }, 2000);
        }

        // Legacy function aliases for backwards compatibility
        function activateTenant(uuid) {
            updateTenantStatus(uuid, 'active');
        }

        function suspendTenant(uuid) {
            updateTenantStatus(uuid, 'suspended');
        }
    </script>
@endpush