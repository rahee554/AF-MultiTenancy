<div>
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">Dashboard</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="#" class="text-muted text-hover-primary">Tenancy</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Dashboard</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <button type="button" wire:click="refreshDashboard" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-arrows-circle fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Refresh
                </button>
                <button type="button" wire:click="clearCache" class="btn btn-sm btn-light-primary">
                    <i class="ki-duotone ki-trash fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Clear Cache
                </button>
                <button type="button" wire:click="runHealthCheck" class="btn btn-sm btn-light-success">
                    <i class="ki-duotone ki-shield-tick fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Health Check
                </button>
            </div>
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Content-->
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">

            <!--begin::Row-->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Statistics Widget 5-->
                    <div class="card bg-body hoverable card-xl-stretch mb-xl-8">
                        <!--begin::Body-->
                        <div class="card-body">
                            <i class="ki-duotone ki-buildings text-primary fs-2x ms-n1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-gray-900 fw-bold fs-2 mb-2 mt-5">
                                {{ is_array($stats) ? ($stats['total_tenants'] ?? 0) : 0 }}
                            </div>
                            <div class="fw-semibold text-gray-400">Total Tenants</div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Statistics Widget 5-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Statistics Widget 5-->
                    <div class="card bg-success hoverable card-xl-stretch mb-xl-8">
                        <!--begin::Body-->
                        <div class="card-body">
                            <i class="ki-duotone ki-check text-white fs-2x ms-n1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5">
                                {{ is_array($stats) ? ($stats['active_tenants'] ?? 0) : 0 }}
                            </div>
                            <div class="fw-semibold text-white opacity-75">Active Tenants</div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Statistics Widget 5-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Statistics Widget 5-->
                    <div class="card bg-warning hoverable card-xl-stretch mb-xl-8">
                        <!--begin::Body-->
                        <div class="card-body">
                            <i class="ki-duotone ki-cpu text-white fs-2x ms-n1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5">
                                {{ is_array($stats) ? ($stats['memory_usage'] ?? 0) : 0 }} MB
                            </div>
                            <div class="fw-semibold text-white opacity-75">Memory Usage</div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Statistics Widget 5-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Statistics Widget 5-->
                    <div class="card bg-info hoverable card-xl-stretch mb-xl-8">
                        <!--begin::Body-->
                        <div class="card-body">
                            <i class="ki-duotone ki-timer text-white fs-2x ms-n1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5">
                                {{ is_array($queueStats) ? ($queueStats['pending_jobs'] ?? ($queueStats['pending'] ?? 0)) : 0 }}
                            </div>
                            <div class="fw-semibold text-white opacity-75">Pending Jobs</div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Statistics Widget 5-->
                </div>
                <!--end::Col-->
            </div>
            <!--end::Row-->

                <!-- Permissions card -->
                <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Required Permissions & Environment</h3>
                            </div>
                            <div class="card-body">
                                @if(! empty($required_permissions))
                                    @php
                                        $storagePath = $required_permissions['storage']['path'] ?? null;
                                        $storageExists = $storagePath ? is_dir($storagePath) : false;
                                        $storageWritable = $required_permissions['storage']['writable'] ?? false;

                                        $views = $required_permissions['views'] ?? [];
                                        $viewsPath = $views['path'] ?? resource_path('views/tenants');
                                        $viewsExists = $views['exists'] ?? is_dir($viewsPath);
                                        $viewsWritable = $views['writable'] ?? false;
                                        $canCreateTenantFolder = $views['can_create_tenant_folder'] ?? $viewsWritable;

                                        $db = $required_permissions['database'] ?? [];
                                        $dbAccessible = $db['accessible'] ?? false;

                                        $php = $required_permissions['php'] ?? [];
                                        $phpVersion = $php['version'] ?? PHP_VERSION;
                                        $phpRequired = $php['required'] ?? '>= 8.2';
                                        $phpOk = version_compare($phpVersion, '8.2', '>=');
                                    @endphp

                                    <div class="row gy-4">
                                        <!-- Storage -->
                                        <div class="col-12">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-4">
                                                    <span class="symbol-label bg-light-{{ ($storageExists && $storageWritable) ? 'success' : (($storageExists && ! $storageWritable) ? 'warning' : 'danger') }}">
                                                        <i class="ki-duotone ki-folder fs-2 text-{{ ($storageExists && $storageWritable) ? 'success' : (($storageExists && ! $storageWritable) ? 'warning' : 'danger') }}">
                                                            <span class="path1"></span><span class="path2"></span>
                                                        </i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="fw-bold text-gray-900">Storage path</div>
                                                            <div class="text-muted fs-7">{{ $storagePath }}</div>
                                                        </div>
                                                        <div class="ms-auto text-end">
                                                            @if($storageExists)
                                                                @if($storageWritable)
                                                                    <span class="badge badge-light-success">Writable</span>
                                                                @else
                                                                    <span class="badge badge-light-warning">Exists — Not writable</span>
                                                                @endif
                                                            @else
                                                                <span class="badge badge-light-danger">Missing</span>
                                                            @endif
                                                            <div class="text-muted fs-8 mt-1"><code>{{ $required_permissions['storage']['required_perms'] }}</code></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Views -->
                                        <div class="col-12">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-4">
                                                    <span class="symbol-label bg-light-{{ ($viewsExists && $viewsWritable) ? 'success' : (($viewsExists && ! $viewsWritable) ? 'warning' : 'danger') }}">
                                                        <i class="ki-duotone ki-eye fs-2 text-{{ ($viewsExists && $viewsWritable) ? 'success' : (($viewsExists && ! $viewsWritable) ? 'warning' : 'danger') }}">
                                                            <span class="path1"></span><span class="path2"></span>
                                                        </i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="fw-bold text-gray-900">Views folder</div>
                                                            <div class="text-muted fs-7">{{ $viewsPath }}</div>
                                                        </div>
                                                        <div class="ms-auto text-end">
                                                            @if($viewsExists)
                                                                <span class="badge badge-light-{{ $viewsWritable ? 'success' : 'warning' }}">
                                                                    {{ $viewsWritable ? 'Writable' : 'Exists — Not writable' }}
                                                                </span>
                                                            @else
                                                                <span class="badge badge-light-danger">Missing</span>
                                                            @endif
                                                            <div class="text-muted fs-8 mt-1">
                                                                Can create tenants folder:
                                                                <strong>{{ $canCreateTenantFolder ? 'Yes' : 'No' }}</strong>
                                                            </div>
                                                            <div class="text-muted fs-8 mt-1"><code>{{ $views['required_perms'] ?? 'resources/views should be writable' }}</code></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Database -->
                                        <div class="col-12">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-4">
                                                    <span class="symbol-label bg-light-{{ $dbAccessible ? 'success' : 'danger' }}">
                                                        <i class="ki-duotone ki-database fs-2 text-{{ $dbAccessible ? 'success' : 'danger' }}">
                                                            <span class="path1"></span><span class="path2"></span>
                                                        </i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="fw-bold text-gray-900">Database connection</div>
                                                            <div class="text-muted fs-7">{{ $db['connection'] ?? config('database.default') }}</div>
                                                        </div>
                                                        <div class="ms-auto text-end">
                                                            <span class="badge badge-light-{{ $dbAccessible ? 'success' : 'danger' }}">
                                                                {{ $dbAccessible ? 'Accessible' : 'Not accessible' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PHP -->
                                        <div class="col-12">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-4">
                                                    <span class="symbol-label bg-light-{{ $phpOk ? 'success' : 'danger' }}">
                                                        <i class="ki-duotone ki-code fs-2 text-{{ $phpOk ? 'success' : 'danger' }}">
                                                            <span class="path1"></span><span class="path2"></span>
                                                        </i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="fw-bold text-gray-900">PHP version</div>
                                                            <div class="text-muted fs-7">{{ $phpVersion }}</div>
                                                        </div>
                                                        <div class="ms-auto text-end">
                                                            <span class="badge badge-light-{{ $phpOk ? 'success' : 'danger' }}">
                                                                {{ $phpOk ? 'OK' : 'Too old' }}
                                                            </span>
                                                            <div class="text-muted fs-8 mt-1">Required: <code>{{ $phpRequired }}</code></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center text-muted">No permission data available.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            <!--begin::Row-->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-lg-6">
                    <!--begin::Card-->
                    <div class="card card-xl-stretch mb-xl-8">
                        <!--begin::Header-->
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">System Health</span>
                                <span class="text-muted fw-semibold fs-7">Check system status</span>
                            </h3>
                            <div class="card-toolbar">
                                @if(isset($systemHealth['overall_status']))
                                    @if($systemHealth['overall_status'] === 'healthy')
                                        <span class="badge badge-light-success">Healthy</span>
                                    @elseif($systemHealth['overall_status'] === 'warning')
                                        <span class="badge badge-light-warning">Warning</span>
                                    @else
                                        <span class="badge badge-light-danger">Issues</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="card-body pt-5">
                            @if(is_array($systemHealth) && !empty($systemHealth['checks'] ?? []))
                                @foreach($systemHealth['checks'] as $component => $status)
                                    @php
                                        $s = is_array($status) ? $status : ['status' => (string) $status, 'message' => ''];
                                    @endphp
                                    <div class="d-flex align-items-center mb-5">
                                        <div class="symbol symbol-40px me-4">
                                            <span class="symbol-label bg-light-{{ ($s['status'] ?? '') === 'healthy' ? 'success' : ((($s['status'] ?? '') === 'warning') ? 'warning' : 'danger') }}">
                                                <i class="ki-duotone ki-gear fs-2 text-{{ ($s['status'] ?? '') === 'healthy' ? 'success' : ((($s['status'] ?? '') === 'warning') ? 'warning' : 'danger') }}">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="#" class="text-gray-900 text-hover-primary fs-6 fw-bold">{{ ucfirst($component) }}</a>
                                            <span class="text-muted fw-semibold">{{ $s['message'] ?? 'No message' }}</span>
                                        </div>
                                        <div class="ms-auto">
                                            <span class="badge badge-light-{{ ($s['status'] ?? '') === 'healthy' ? 'success' : ((($s['status'] ?? '') === 'warning') ? 'warning' : 'danger') }}">
                                                {{ ucfirst($s['status'] ?? 'unknown') }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center">
                                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="text-muted fw-semibold fs-6">No health data available. Run health check.</div>
                                </div>
                            @endif
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-lg-6">
                    <!--begin::Card-->
                    <div class="card card-xl-stretch mb-xl-8">
                        <!--begin::Header-->
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Recent Activities</span>
                                <span class="text-muted fw-semibold fs-7">Latest system activities</span>
                            </h3>
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="card-body pt-5">
                            @if(is_array($recentActivities) && count($recentActivities) > 0)
                                @foreach($recentActivities as $activity)
                                    @php
                                        $act = is_array($activity) ? $activity : ['message' => (string) $activity, 'time' => 'Just now', 'icon' => 'information', 'color' => 'primary'];
                                    @endphp
                                    <div class="d-flex align-items-center mb-6">
                                        <div class="symbol symbol-40px me-4">
                                            <span class="symbol-label bg-light-{{ $act['color'] ?? 'primary' }}">
                                                <i class="ki-duotone ki-{{ $act['icon'] ?? 'information' }} fs-2 text-{{ $act['color'] ?? 'primary' }}">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="#" class="text-gray-900 text-hover-primary fs-6 fw-bold">{{ $act['message'] ?? 'Activity' }}</a>
                                            <span class="text-muted fw-semibold">{{ $act['time'] ?? 'Just now' }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center">
                                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="text-muted fw-semibold fs-6">No recent activities</div>
                                </div>
                            @endif
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
            </div>
            <!--end::Row-->

            <!--begin::Row-->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-lg-8">
                    <!--begin::Card-->
                    <div class="card card-xl-stretch mb-xl-8">
                        <!--begin::Header-->
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Queue Statistics</span>
                                <span class="text-muted fw-semibold fs-7">Monitor queue performance</span>
                            </h3>
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="card-body pt-5">
                            <div class="row">
                                <div class="col-6 mb-7">
                                    <div class="d-flex align-items-center">
                                        <span class="fw-semibold text-muted me-1">Driver:</span>
                                        <span class="fw-bold text-gray-800 fs-6">{{ is_array($queueStats) ? ($queueStats['driver'] ?? 'Unknown') : 'Unknown' }}</span>
                                    </div>
                                </div>
                                <div class="col-6 mb-7">
                                    <div class="d-flex align-items-center">
                                        <span class="fw-semibold text-muted me-1">Workers:</span>
                                        <span class="fw-bold text-gray-800 fs-6">{{ is_array($queueStats) ? ($queueStats['workers'] ?? 0) : 0 }}</span>
                                    </div>
                                </div>
                                <div class="col-6 mb-7">
                                    <div class="d-flex align-items-center">
                                        <span class="fw-semibold text-muted me-1">Pending Jobs:</span>
                                        <span class="fw-bold text-warning fs-6">{{ is_array($queueStats) ? ($queueStats['pending_jobs'] ?? ($queueStats['pending'] ?? 0)) : 0 }}</span>
                                    </div>
                                </div>
                                <div class="col-6 mb-7">
                                    <div class="d-flex align-items-center">
                                        <span class="fw-semibold text-muted me-1">Failed Jobs:</span>
                                        <span class="fw-bold text-danger fs-6">{{ is_array($queueStats) ? ($queueStats['failed_jobs'] ?? 0) : 0 }}</span>
                                    </div>
                                </div>
                            </div>
                            @if(is_array($queueStats) && ($queueStats['failed_jobs'] ?? 0) > 0)
                                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
                                    <i class="ki-duotone ki-information-5 fs-2tx text-warning me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-stack flex-grow-1">
                                        <div class="fw-semibold">
                                            <div class="fs-6 text-gray-700">You have {{ $queueStats['failed_jobs'] }} failed jobs that need attention.</div>
                                        </div>
                                        <a href="{{ route('tenancy.admin.queue') }}" class="btn btn-warning btn-sm">View Failed Jobs</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-lg-4">
                    <!--begin::Card-->
                    <div class="card card-xl-stretch mb-xl-8">
                        <!--begin::Header-->
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Quick Actions</span>
                                <span class="text-muted fw-semibold fs-7">Admin shortcuts</span>
                            </h3>
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="card-body pt-5">
                            <div class="d-grid gap-3">
                                <a href="{{ route('tenancy.admin.create') }}" class="btn btn-outline btn-outline-primary btn-active-light-primary">
                                    <i class="ki-duotone ki-plus fs-3 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Create Tenant
                                </a>
                                <a href="{{ route('tenancy.admin.analytics') }}" class="btn btn-outline btn-outline-info btn-active-light-info">
                                    <i class="ki-duotone ki-chart-simple fs-3 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    Analytics
                                </a>
                                <a href="{{ route('tenancy.admin.queue') }}" class="btn btn-outline btn-outline-warning btn-active-light-warning">
                                    <i class="ki-duotone ki-timer fs-3 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Queue
                                </a>
                                <a href="{{ route('tenancy.admin.monitoring') }}" class="btn btn-outline btn-outline-success btn-active-light-success">
                                    <i class="ki-duotone ki-monitor fs-3 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Monitoring
                                </a>
                            </div>
                            
                            <div class="separator my-6"></div>
                            
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" wire:model.live="autoRefresh" id="autoRefreshToggle">
                                <label class="form-check-label fw-semibold text-gray-700" for="autoRefreshToggle">
                                    Auto Refresh ({{ $refreshInterval ?? 30 }}s)
                                </label>
                            </div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
            </div>
            <!--end::Row-->

        </div>
        <!--end::Content container-->
    </div>
    <!--end::Content-->

    @if($autoRefresh)
        @push('scripts')
        <script>
            setInterval(function() {
                Livewire.emit('refreshDashboard');
            }, {{ (int) ($refreshInterval ?? 30) * 1000 }});
        </script>
        @endpush
    @endif

</div>
