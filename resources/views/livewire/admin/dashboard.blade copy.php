<div>
    @section('title', 'Tenancy Dashboard')

    <!--begin::Page title-->
    <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
        <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
            Tenancy Dashboard
        </h1>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
            <li class="breadcrumb-item text-muted">
                <a href="#" class="text-muted text-hover-primary">Home</a>
            </li>
            <li class="breadcrumb-item">
                <span class="bullet bg-gray-400 w-5px h-2px"></span>
            </li>
            <li class="breadcrumb-item text-muted">Dashboard</li>
        </ul>
    </div>
    <!--end::Page title-->

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        <!--begin::Toolbar-->
        <div class="toolbar" id="kt_toolbar">
            <div class="container-fluid d-flex flex-stack">
                <div class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
                    <!-- Actions -->
                    <div class="d-flex gap-2">
                        <button wire:click="refreshDashboard" class="btn btn-sm btn-primary">
                            <i class="ki-duotone ki-arrows-circle fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Refresh
                        </button>
                        <button wire:click="clearCache" class="btn btn-sm btn-secondary">
                            <i class="ki-duotone ki-trash fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            Clear Cache
                        </button>
                        <button wire:click="runHealthCheck" class="btn btn-sm btn-info">
                            <i class="ki-duotone ki-heart fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Health Check
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 gap-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" wire:model.live="autoRefresh" id="autoRefresh">
                        <label class="form-check-label fw-semibold text-gray-800" for="autoRefresh">
                            Auto-refresh ({{ $refreshInterval }}s)
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Toolbar-->

        <!--begin::Post-->
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <!--begin::Container-->
            <div class="container-xxl">
                <!--begin::Row-->
                <div class="row gy-5 g-xl-10">
                    <!--begin::Col-->
                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <div class="card bg-body hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <i class="ki-duotone ki-office-bag text-primary fs-2x ms-n1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
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
                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <div class="card bg-body hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <i class="ki-duotone ki-check-circle text-success fs-2x ms-n1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="text-gray-900 fw-bold fs-2 mb-2 mt-5">
                                    {{ is_array($stats) ? ($stats['active_tenants'] ?? 0) : 0 }}
                                </div>
                                <div class="fw-semibold text-gray-400">Active Tenants</div>
                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Statistics Widget 5-->
                    </div>
                    <!--end::Col-->

                    <!--begin::Col-->
                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <div class="card bg-body hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <i class="ki-duotone ki-server text-warning fs-2x ms-n1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="text-gray-900 fw-bold fs-2 mb-2 mt-5">
                                    {{ is_array($stats) ? ($stats['memory_usage'] ?? '0 MB') : '0 MB' }}
                                </div>
                                <div class="fw-semibold text-gray-400">Memory Usage</div>
                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Statistics Widget 5-->
                    </div>
                    <!--end::Col-->

                    <!--begin::Col-->
                    <div class="col-xl-3">
                        <!--begin::Statistics Widget 5-->
                        <div class="card bg-body hoverable card-xl-stretch mb-xl-8">
                            <!--begin::Body-->
                            <div class="card-body">
                                <i class="ki-duotone ki-timer text-info fs-2x ms-n1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="text-gray-900 fw-bold fs-2 mb-2 mt-5">
                                    {{ is_array($queueStats) ? ($queueStats['pending_jobs'] ?? ($queueStats['pending'] ?? 0)) : 0 }}
                                </div>
                                <div class="fw-semibold text-gray-400">Pending Jobs</div>
                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Statistics Widget 5-->
                    </div>
                    <!--end::Col-->
                </div>
                <!--end::Row-->

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">System Health</div>
                            <div class="card-body">
                                @if(is_array($systemHealth) && !empty($systemHealth['checks'] ?? []))
                                    @foreach($systemHealth['checks'] as $component => $status)
                                        @php
                                            $s = is_array($status) ? $status : ['status' => (string) $status, 'message' => ''];
                                        @endphp
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong>{{ ucfirst($component) }}</strong>
                                                <div class="text-muted small">{{ $s['message'] ?? '' }}</div>
                                            </div>
                                            <span
                                                class="badge bg-{{ ($s['status'] ?? '') === 'healthy' ? 'success' : ((($s['status'] ?? '') === 'warning') ? 'warning' : 'danger') }}">
                                                {{ ucfirst($s['status'] ?? 'unknown') }}
                                            </span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center text-muted py-4">No health data. Run health check.</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">Recent Activities</div>
                            <div class="card-body">
                                @if(is_array($recentActivities) && count($recentActivities) > 0)
                                    @foreach($recentActivities as $activity)
                                        @php
                                            $act = is_array($activity) ? $activity : ['message' => (string) $activity, 'time' => 'Just now', 'icon' => 'info-circle'];
                                        @endphp
                                        <div class="mb-3">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <span class="avatar bg-light p-2 rounded-circle"><i
                                                            class="fas fa-{{ $act['icon'] ?? 'info-circle' }}"></i></span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">{{ $act['message'] ?? 'Activity' }}</div>
                                                    <div class="text-muted small">{{ $act['time'] ?? 'Just now' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center text-muted py-4">No recent activities</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">Queue Statistics</div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>Driver</div>
                                    <div>{{ is_array($queueStats) ? ($queueStats['driver'] ?? 'Unknown') : 'Unknown' }}
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <div>Pending Jobs</div>
                                    <div>
                                        {{ is_array($queueStats) ? ($queueStats['pending_jobs'] ?? ($queueStats['pending'] ?? 0)) : 0 }}
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <div>Failed Jobs</div>
                                    <div class="text-danger">
                                        {{ is_array($queueStats) ? ($queueStats['failed_jobs'] ?? 0) : 0 }}</div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div>Workers</div>
                                    <div>{{ is_array($queueStats) ? ($queueStats['workers'] ?? 0) : 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">Quick Actions</div>
                            <div class="card-body d-grid gap-2">
                                <a href="{{ route('tenancy.admin.create') }}" class="btn btn-outline-primary">Create
                                    Tenant</a>
                                <a href="{{ route('tenancy.admin.analytics') }}"
                                    class="btn btn-outline-secondary">Analytics</a>
                                <a href="{{ route('tenancy.admin.queue') }}" class="btn btn-outline-warning">Queue</a>
                                <a href="{{ route('tenancy.admin.monitoring') }}"
                                    class="btn btn-outline-success">Monitoring</a>
                            </div>
                        </div>
                    </div>
                </div>

                @if($autoRefresh)
                    @push('scripts')
                        <script>
                            setInterval(function () {
                                Livewire.emit('refreshDashboard');
                            }, {{ (int) ($refreshInterval ?? 30) * 1000 }});
                        </script>
                    @endpush
                @endif

            </div>

        </div>
    </div>

    <!-- Queue Statistics -->
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Queue Status</h3>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Driver</span>
                    <span class="text-sm font-medium">{{ $queueStats['driver'] ?? 'Unknown' }}</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Pending Jobs</span>
                    <span class="text-sm font-medium">{{ $queueStats['pending_jobs'] ?? 0 }}</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Failed Jobs</span>
                    <span class="text-sm font-medium text-red-600">{{ $queueStats['failed_jobs'] ?? 0 }}</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Workers</span>
                    <span class="text-sm font-medium">{{ $queueStats['workers'] ?? 0 }}</span>
                </div>
            </div>

            @if(($queueStats['failed_jobs'] ?? 0) > 0)
                <div class="mt-4">
                    <a href="{{ route('tenancy.admin.queue') }}" class="text-sm text-red-600 hover:text-red-800">
                        View Failed Jobs →
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Links -->
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <a href="{{ route('tenancy.admin.create') }}"
                    class="group block w-full text-left px-4 py-3 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create New Tenant
                    </div>
                </a>

                <a href="{{ route('tenancy.admin.analytics') }}"
                    class="group block w-full text-left px-4 py-3 text-sm bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        View Analytics
                    </div>
                </a>

                <a href="{{ route('tenancy.admin.queue') }}"
                    class="group block w-full text-left px-4 py-3 text-sm bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Monitor Queues
                    </div>
                </a>

                <a href="{{ route('tenancy.admin.monitoring') }}"
                    class="group block w-full text-left px-4 py-3 text-sm bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        System Monitoring
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

@if($autoRefresh)
    <script>
        setInterval(() => {
            @this.call('refreshDashboard');
        }, {{ $refreshInterval * 1000 }});
    </script>
@endif


<style>
    .btn {
        @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm transition-colors duration-200;
    }

    .btn-primary {
        @apply text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
    }

    .btn-secondary {
        @apply text-gray-700 bg-white border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
    }

    .btn-info {
        @apply text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500;
    }
</style>


<!-- Quick Actions -->
<div class="mb-8 flex flex-wrap gap-4">
    <button wire:click="refreshDashboard" class="btn btn-primary">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
            </path>
        </svg>
        Refresh
    </button>

    <button wire:click="clearCache" class="btn btn-secondary">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
            </path>
        </svg>
        Clear Cache
    </button>

    <button wire:click="runHealthCheck" class="btn btn-info">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Health Check
    </button>

    <label class="flex items-center">
        <input type="checkbox" wire:model.live="autoRefresh" class="mr-2">
        Auto Refresh ({{ $refreshInterval }}s)
    </label>
</div>

<!-- Statistics Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Tenants -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-blue-500 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_tenants'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Active Tenants -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-green-500 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Tenants</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['active_tenants'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Memory Usage -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-purple-500 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Memory Usage</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['memory_usage'] ?? 0 }} MB</p>
            </div>
        </div>
    </div>

    <!-- Queue Jobs -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-500 rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Pending Jobs</p>
                <p class="text-2xl font-bold text-gray-900">{{ $queueStats['pending_jobs'] ?? 0 }}</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- System Health -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">System Health</h3>

        @if(isset($systemHealth['overall_status']))
            <div class="mb-4">
                <div class="flex items-center">
                    @if($systemHealth['overall_status'] === 'healthy')
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-green-700 font-medium">System Healthy</span>
                    @elseif($systemHealth['overall_status'] === 'warning')
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                        <span class="text-yellow-700 font-medium">System Warning</span>
                    @else
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                        <span class="text-red-700 font-medium">System Issues</span>
                    @endif
                </div>
            </div>

            <div class="space-y-3">
                @foreach($systemHealth['checks'] ?? [] as $check => $status)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $check) }}</span>
                        <div class="flex items-center">
                            @if($status['status'] === 'healthy')
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-xs text-green-700">{{ $status['message'] }}</span>
                            @elseif($status['status'] === 'warning')
                                <div class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></div>
                                <span class="text-xs text-yellow-700">{{ $status['message'] }}</span>
                            @else
                                <div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div>
                                <span class="text-xs text-red-700">{{ $status['message'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500">Health check data unavailable</p>
        @endif
    </div>

    <!-- Recent Activities -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activities</h3>

        <div class="space-y-3">
            @forelse($recentActivities as $activity)
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        @if($activity['color'] === 'green')
                            <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                        @elseif($activity['color'] === 'blue')
                            <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        @else
                            <div class="w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-900">{{ $activity['message'] }}</p>
                        <p class="text-xs text-gray-500">{{ $activity['time'] }}</p>
                    </div>
                </div>
            @empty
                <p class="text-gray-500">No recent activities</p>
            @endforelse
        </div>
    </div>

    <!-- Queue Statistics -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Queue Status</h3>

        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Driver</span>
                <span class="text-sm font-medium">{{ $queueStats['driver'] ?? 'Unknown' }}</span>
            </div>

            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Pending Jobs</span>
                <span class="text-sm font-medium">{{ $queueStats['pending_jobs'] ?? 0 }}</span>
            </div>

            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Failed Jobs</span>
                <span class="text-sm font-medium text-red-600">{{ $queueStats['failed_jobs'] ?? 0 }}</span>
            </div>

            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Workers</span>
                <span class="text-sm font-medium">{{ $queueStats['workers'] ?? 0 }}</span>
            </div>
        </div>

        @if(($queueStats['failed_jobs'] ?? 0) > 0)
            <div class="mt-4">
                <a href="{{ route('tenancy.admin.queue') }}" class="text-sm text-red-600 hover:text-red-800">
                    View Failed Jobs →
                </a>
            </div>
        @endif
    </div>

    <!-- Quick Links -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>

        <div class="space-y-3">
            <a href="{{ route('tenancy.admin.create') }}"
                class="block w-full text-left px-4 py-2 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                Create New Tenant
            </a>

            <a href="{{ route('tenancy.admin.analytics') }}"
                class="block w-full text-left px-4 py-2 text-sm bg-purple-50 text-purple-700 rounded hover:bg-purple-100">
                View Analytics
            </a>

            <a href="{{ route('tenancy.admin.queue') }}"
                class="block w-full text-left px-4 py-2 text-sm bg-yellow-50 text-yellow-700 rounded hover:bg-yellow-100">
                Monitor Queues
            </a>

            <a href="{{ route('tenancy.admin.monitoring') }}"
                class="block w-full text-left px-4 py-2 text-sm bg-green-50 text-green-700 rounded hover:bg-green-100">
                System Monitoring
            </a>
        </div>
    </div>
</div>

@if($autoRefresh)
    <script>
        setInterval(() => {
            @this.call('refreshDashboard');
        }, {{ $refreshInterval * 1000 }});
    </script>
@endif


</div>