<div>
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    System Monitoring</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('tenancy.admin.dashboard') }}"
                            class="text-muted text-hover-primary">Dashboard</a>
                        <div>
                            <!--begin::Toolbar-->
                            <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
                                <div id="kt_app_toolbar_container"
                                    class="app-container container-xxl d-flex flex-stack">
                                    <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                                        <h1
                                            class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                                            System Monitoring</h1>
                                        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                                            <li class="breadcrumb-item text-muted">
                                                <a href="{{ route('tenancy.admin.dashboard') }}"
                                                    class="text-muted text-hover-primary">Dashboard</a>
                                            </li>
                                            <li class="breadcrumb-item">
                                                <span class="bullet bg-gray-500 w-5px h-2px"></span>
                                            </li>
                                            <li class="breadcrumb-item text-muted">System Monitoring</li>
                                        </ul>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 gap-lg-3">
                                        <div class="form-check form-switch form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox"
                                                wire:model.live="autoRefresh" id="autoRefreshToggle">
                                            <label class="form-check-label fw-semibold text-gray-500 ms-3"
                                                for="autoRefreshToggle">Auto Refresh</label>
                                        </div>

                                        <select wire:model.live="refreshInterval"
                                            class="form-select form-select-solid form-select-sm" data-control="select2"
                                            data-hide-search="true" data-placeholder="Interval">
                                            <option value="5">5s</option>
                                            <option value="10">10s</option>
                                            <option value="30">30s</option>
                                            <option value="60">1m</option>
                                        </select>

                                        <button type="button" wire:click="refreshData"
                                            class="btn btn-sm btn-primary">Refresh Now</button>
                                        <button type="button" wire:click="clearSystemCache"
                                            class="btn btn-sm btn-secondary">Clear Cache</button>
                                        <button type="button" wire:click="optimizeSystem"
                                            class="btn btn-sm btn-success">Optimize</button>
                                    </div>
                                </div>
                            </div>
                            <!--end::Toolbar-->

                            <!--begin::Content-->
                            <div id="kt_app_content" class="app-content flex-column-fluid">
                                <div id="kt_app_content_container" class="app-container container-xxl">

                                    <!-- System Overview Row -->
                                    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                                        <div class="col-xxl-6">
                                            <div class="card card-xxl-stretch">
                                                <div class="card-header border-0 bg-danger py-5">
                                                    <h3 class="card-title fw-bold text-white">System Resources</h3>
                                                </div>
                                                <div class="card-body p-0">
                                                    <div class="mixed-widget-2-chart card-rounded-bottom bg-danger"
                                                        data-kt-color="danger" style="height: 200px"></div>
                                                    <div class="card-p mt-n20 position-relative">
                                                        <div class="row g-0">
                                                            <div
                                                                class="col bg-light-warning px-6 py-8 rounded-2 me-7 mb-7">
                                                            </div>
                                                            <div class="col bg-light-primary px-6 py-8 rounded-2 mb-7">
                                                            </div>
                                                        </div>
                                                        <div class="row g-0">
                                                            <div class="col bg-light-danger px-6 py-8 rounded-2 me-7">
                                                            </div>
                                                            <div class="col bg-light-success px-6 py-8 rounded-2"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-xxl-6">
                                            <div class="card card-xxl-stretch">
                                                <div class="card-header border-0 pt-5">
                                                    <h3 class="card-title align-items-start flex-column">
                                                        <span class="card-label fw-bold fs-3 mb-1">Active
                                                            Processes</span>
                                                        <span class="text-muted fw-semibold fs-7">Top resource consuming
                                                            processes</span>
                                                    </h3>
                                                </div>
                                                <div class="card-body pt-5">
                                                    <div class="timeline-label">
                                                        @foreach($processStats ?? [] as $index => $process)
                                                            <div class="timeline-item"></div>
                                                        @endforeach

                                                        @if(empty($processStats))
                                                            <div class="text-center py-10"></div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Services & Metrics -->
                                    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                                        <div class="col-xl-12">
                                            <div class="card card-flush h-xl-100">
                                                <div class="card-header pt-7">
                                                    <h3 class="card-title align-items-start flex-column">
                                                        <span class="card-label fw-bold text-gray-900">System
                                                            Services</span>
                                                        <span class="text-gray-500 mt-1 fw-semibold fs-6">Service status
                                                            and health monitoring</span>
                                                    </h3>
                                                    <div class="card-toolbar">
                                                        <a href="#" class="btn btn-sm btn-light">Configure</a>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table
                                                            class="table table-row-dashed align-middle gs-0 gy-3 my-0">
                                                            <thead></thead>
                                                            <tbody></tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Health Checks -->
                                    <div class="bg-white p-6 rounded-lg shadow mb-8">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-lg font-semibold text-gray-900">System Health Checks</h3>
                                            <button wire:click="runHealthChecks" class="btn btn-outline">Run
                                                Checks</button>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            @foreach(is_array($healthChecks ?? []) ? $healthChecks : [] as $check => $status)
                                                <div class="flex items-center p-3 border rounded-lg">
                                                    <div class="flex-shrink-0 mr-3">
                                                        @if($status['status'] === 'healthy')
                                                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                                        @elseif($status['status'] === 'warning')
                                                            <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                                        @else
                                                            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                                        @endif
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium text-gray-900 capitalize">
                                                            {{ str_replace('_', ' ', $check) }}</p>
                                                        <p class="text-xs text-gray-500">
                                                            {{ $status['message'] ?? 'Unknown' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Performance Metrics -->
                                    <div class="bg-white p-6 rounded-lg shadow">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Metrics</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <div class="text-center p-4 border rounded-lg">
                                                <p class="text-2xl font-bold text-blue-600">
                                                    {{ number_format($performanceMetrics['response_time'] ?? 0, 2) }}ms
                                                </p>
                                                <p class="text-sm text-gray-600">Avg Response Time</p>
                                            </div>
                                            <div class="text-center p-4 border rounded-lg">
                                                <p class="text-2xl font-bold text-green-600">
                                                    {{ number_format($performanceMetrics['throughput'] ?? 0) }}</p>
                                                <p class="text-sm text-gray-600">Requests/min</p>
                                            </div>
                                            <div class="text-center p-4 border rounded-lg">
                                                <p class="text-2xl font-bold text-purple-600">
                                                    {{ number_format($performanceMetrics['database_queries'] ?? 0) }}
                                                </p>
                                                <p class="text-sm text-gray-600">DB Queries/req</p>
                                            </div>
                                            <div class="text-center p-4 border rounded-lg">
                                                <p class="text-2xl font-bold text-orange-600">
                                                    {{ number_format($performanceMetrics['cache_hit_rate'] ?? 0, 1) }}%
                                                </p>
                                                <p class="text-sm text-gray-600">Cache Hit Rate</p>
                                            </div>
                                        </div>
                                    </div>

                                    @if($autoRefresh)
                                        <div wire:poll.{{ $refreshInterval ?? 5 }}s="refreshStats"></div>
                                    @endif

                                </div>
                            </div>
                            <!--end::Content-->
                        </div>
                        <!--begin::Card body-->
                        <div class="card-body">
                            <!--begin::Table-->
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle gs-0 gy-3 my-0">
                                    <!--begin::Table head-->
                                    <thead>
                                        <tr class="fs-7 fw-bold text-gray-500 border-bottom-0">
                                            <th class="p-0 pb-3 min-w-175px text-start">Service</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">Status</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">Uptime</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <!--end::Table head-->
                                    <!--begin::Table body-->
                                    <tbody>
                                        @foreach($serviceStats ?? [] as $service)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol symbol-50px me-3">
                                                            <span
                                                                class="symbol-label {{ $service['status'] === 'running' ? 'bg-light-success' : 'bg-light-danger' }}">
                                                                <i
                                                                    class="ki-duotone ki-abstract-47 fs-2x {{ $service['status'] === 'running' ? 'text-success' : 'text-danger' }}">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex justify-content-start flex-column">
                                                            <a href="#"
                                                                class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">{{ $service['name'] ?? 'Unknown Service' }}</a>
                                                            <span
                                                                class="text-gray-500 fw-semibold d-block fs-7">{{ $service['description'] ?? 'System service' }}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    @if(($service['status'] ?? 'stopped') === 'running')
                                                        <span class="badge badge-light-success fs-7 fw-bold">Running</span>
                                                    @else
                                                        <span class="badge badge-light-danger fs-7 fw-bold">Stopped</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <span
                                                        class="text-gray-900 fw-bold d-block fs-6">{{ $service['uptime'] ?? '0h 0m' }}</span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="#" class="btn btn-sm btn-light btn-active-light-primary">
                                                        Restart
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach

                                        @if(empty($serviceStats))
                                            <tr>
                                                <td colspan="4" class="text-center py-10">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                            <span class="path3"></span>
                                                        </i>
                                                        <div class="fw-semibold fs-6 text-gray-500 mb-3">No service data
                                                            available</div>
                                                        <div class="fs-7 text-gray-400">Service monitoring data will appear
                                                            here</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <!--end::Table body-->
                                </table>
                            </div>
                            <!--end::Table-->
                        </div>
                        <!--end::Card body-->
            </div>
            <!--end::Table Widget 4-->
        </div>
        <!--end::Col-->
    </div>
    <!--end::Row-->

</div>
<!--end::Content container-->