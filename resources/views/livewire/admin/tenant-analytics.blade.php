<div>
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Tenant Analytics</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('tenancy.admin.dashboard') }}"
                            class="text-muted text-hover-primary">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Analytics</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <!--begin::Time range selector-->
                <select wire:model.live="timeRange" class="form-select form-select-solid form-select-sm w-150px"
                    data-control="select2" data-hide-search="true" data-placeholder="Time Range">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 3 months</option>
                    <option value="365">Last year</option>
                </select>
                <!--end::Time range selector-->
                <button type="button" wire:click="exportData" class="btn btn-sm btn-light-primary">
                    <i class="ki-duotone ki-exit-up fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Export
                </button>
                <button type="button" wire:click="refreshAnalytics" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-arrows-circle fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Refresh
                </button>
            </div>
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Content-->
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">

            @if (session()->has('message'))
                <div class="alert alert-dismissible bg-light-success d-flex flex-column flex-sm-row p-5 mb-10">
                    <i class="ki-duotone ki-notification-bing fs-2hx text-success me-4 mb-5 mb-sm-0">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column pe-0 pe-sm-10">
                        <h4 class="fw-semibold">Success</h4>
                        <span>{{ session('message') }}</span>
                    </div>
                    <button type="button"
                        class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto"
                        data-bs-dismiss="alert">
                        <i class="ki-duotone ki-cross fs-1 text-success">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-dismissible bg-light-danger d-flex flex-column flex-sm-row p-5 mb-10">
                    <i class="ki-duotone ki-information-5 fs-2hx text-danger me-4 mb-5 mb-sm-0">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column pe-0 pe-sm-10">
                        <h4 class="fw-semibold">Error</h4>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button"
                        class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto"
                        data-bs-dismiss="alert">
                        <i class="ki-duotone ki-cross fs-1 text-danger">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            @endif

            <!--begin::Row-->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-xxl-6">
                    <!--begin::Chart widget 28-->
                    <div class="card card-flush h-xl-100">
                        <!--begin::Header-->
                        <div class="card-header pt-7">
                            <!--begin::Title-->
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-900">Tenant Growth</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Monthly tenant registration
                                    trends</span>
                            </h3>
                            <!--end::Title-->
                            <!--begin::Toolbar-->
                            <div class="card-toolbar">
                                <a href="#" class="btn btn-sm btn-light">
                                    View Report
                                </a>
                            </div>
                            <!--end::Toolbar-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="card-body d-flex align-items-end px-0 pt-3 pb-5">
                            <!--begin::Chart-->
                            <div id="kt_charts_widget_28" class="h-325px w-100 min-h-auto ps-4 pe-6"></div>
                            <!--end::Chart-->
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Chart widget 28-->
                </div>
                <!--end::Col-->
                <!--begin::Col-->
                <div class="col-xxl-6">
                    <!--begin::Chart widget 36-->
                    <div class="card card-flush h-xl-100">
                        <!--begin::Header-->
                        <div class="card-header pt-7">
                            <!--begin::Title-->
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-900">Usage Statistics</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Database storage and usage
                                    metrics</span>
                            </h3>
                            <!--end::Title-->
                            <!--begin::Toolbar-->
                            <div class="card-toolbar">
                                <a href="#" class="btn btn-sm btn-light">
                                    <i class="ki-duotone ki-setting-2 fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Settings
                                </a>
                            </div>
                            <!--end::Toolbar-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="card-body d-flex flex-column justify-content-between pb-5 px-0">
                            <!--begin::Chart-->
                            <div id="kt_charts_widget_36" class="h-325px w-100 min-h-auto ps-4 pe-6"></div>
                            <!--end::Chart-->
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Chart widget 36-->
                </div>
                <!--end::Col-->
            </div>
            <!--end::Row-->

            <!--begin::Row-->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Card widget 20-->
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-md-50 mb-5 mb-xl-10"
                        style="background-color: #F1416C;background-image:url('{{ asset('vendor/artflow-studio/tenancy/media/patterns/vector-1.png') }}')">
                        <!--begin::Header-->
                        <div class="card-header pt-5">
                            <!--begin::Title-->
                            <div class="card-title d-flex flex-column">
                                <!--begin::Amount-->
                                <span
                                    class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">{{ number_format($analyticsData['total_tenants'] ?? 0) }}</span>
                                <!--end::Amount-->
                                <!--begin::Subtitle-->
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Total Tenants</span>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Title-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Card body-->
                        <div class="card-body d-flex flex-column justify-content-end pe-0">
                            <!--begin::Title-->
                            <span
                                class="fs-6 fw-bolder text-white opacity-75 pt-1">{{ ($analyticsData['growth_rate'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($analyticsData['growth_rate'] ?? 0, 1) }}%
                                this month</span>
                            <!--end::Title-->
                        </div>
                        <!--end::Card body-->
                    </div>
                    <!--end::Card widget 20-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Card widget 7-->
                    <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                        <!--begin::Header-->
                        <div class="card-header pt-5">
                            <!--begin::Title-->
                            <div class="card-title d-flex flex-column">
                                <!--begin::Info-->
                                <div class="d-flex align-items-center">
                                    <!--begin::Currency-->
                                    <span class="fs-4 fw-semibold text-gray-500 me-1 align-self-start">Active</span>
                                    <!--end::Currency-->
                                    <!--begin::Amount-->
                                    <span
                                        class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2">{{ number_format($analyticsData['active_tenants'] ?? 0) }}</span>
                                    <!--end::Amount-->
                                </div>
                                <!--end::Info-->
                                <!--begin::Subtitle-->
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">Active Tenants</span>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Title-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Card body-->
                        <div class="card-body pt-2 pb-4 d-flex flex-wrap align-items-center">
                            <!--begin::Chart-->
                            <div class="d-flex flex-center me-5 pt-2">
                                <div id="kt_card_widget_7_chart" style="min-width: 70px; min-height: 70px"
                                    data-kt-size="70" data-kt-line="11"></div>
                            </div>
                            <!--end::Chart-->
                            <!--begin::Labels-->
                            <div class="d-flex flex-column content-justify-center flex-row-fluid">
                                <!--begin::Label-->
                                <div class="d-flex fw-semibold align-items-center">
                                    <!--begin::Bullet-->
                                    <div class="bullet w-8px h-6px rounded-2 bg-success me-3"></div>
                                    <!--end::Bullet-->
                                    <!--begin::Label-->
                                    <div class="text-gray-500 flex-grow-1 me-4">Online</div>
                                    <!--end::Label-->
                                    <!--begin::Stats-->
                                    <div class="fw-bolder text-gray-700 text-xxl-end">
                                        {{ number_format(($analyticsData['active_tenants'] ?? 0) * 0.85) }}
                                    </div>
                                    <!--end::Stats-->
                                </div>
                                <!--end::Label-->
                                <!--begin::Label-->
                                <div class="d-flex fw-semibold align-items-center my-2">
                                    <!--begin::Bullet-->
                                    <div class="bullet w-8px h-6px rounded-2 bg-primary me-3"></div>
                                    <!--end::Bullet-->
                                    <!--begin::Label-->
                                    <div class="text-gray-500 flex-grow-1 me-4">Offline</div>
                                    <!--end::Label-->
                                    <!--begin::Stats-->
                                    <div class="fw-bolder text-gray-700 text-xxl-end">
                                        {{ number_format(($analyticsData['active_tenants'] ?? 0) * 0.15) }}
                                    </div>
                                    <!--end::Stats-->
                                </div>
                                <!--end::Label-->
                            </div>
                            <!--end::Labels-->
                        </div>
                        <!--end::Card body-->
                    </div>
                    <!--end::Card widget 7-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Card widget 17-->
                    <div class="card card-flush h-md-50 mb-xl-10">
                        <!--begin::Header-->
                        <div class="card-header pt-5">
                            <!--begin::Title-->
                            <div class="card-title d-flex flex-column">
                                <!--begin::Info-->
                                <div class="d-flex align-items-center">
                                    <!--begin::Amount-->
                                    <span
                                        class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2">{{ number_format($analyticsData['total_storage_gb'] ?? 0, 1) }}</span>
                                    <!--end::Amount-->
                                    <!--begin::Currency-->
                                    <span class="fs-4 fw-semibold text-gray-500 me-1 align-self-start">GB</span>
                                    <!--end::Currency-->
                                </div>
                                <!--end::Info-->
                                <!--begin::Subtitle-->
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">Total Storage</span>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Title-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Card body-->
                        <div class="card-body pt-2 pb-4 d-flex align-items-center">
                            <!--begin::Chart-->
                            <div class="d-flex flex-center me-5 pt-2">
                                <div id="kt_card_widget_17_chart" style="min-width: 70px; min-height: 70px"
                                    data-kt-size="70" data-kt-line="11"></div>
                            </div>
                            <!--end::Chart-->
                            <!--begin::Labels-->
                            <div class="d-flex flex-column content-justify-center w-100">
                                <!--begin::Label-->
                                <div class="d-flex fs-6 fw-semibold align-items-center">
                                    <!--begin::Bullet-->
                                    <div class="bullet w-8px h-6px rounded-2 bg-info me-3"></div>
                                    <!--end::Bullet-->
                                    <!--begin::Label-->
                                    <div class="text-gray-500 flex-grow-1 me-4">Used</div>
                                    <!--end::Label-->
                                    <!--begin::Stats-->
                                    <div class="fw-bolder text-gray-700 text-xxl-end">
                                        {{ number_format($analyticsData['used_storage_percentage'] ?? 0, 1) }}%
                                    </div>
                                    <!--end::Stats-->
                                </div>
                                <!--end::Label-->
                            </div>
                            <!--end::Labels-->
                        </div>
                        <!--end::Card body-->
                    </div>
                    <!--end::Card widget 17-->
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Card widget 20-->
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-md-50 mb-5 mb-xl-10"
                        style="background-color: #7239EA;background-image:url('{{ asset('vendor/artflow-studio/tenancy/media/patterns/vector-1.png') }}')">
                        <!--begin::Header-->
                        <div class="card-header pt-5">
                            <!--begin::Title-->
                            <div class="card-title d-flex flex-column">
                                <!--begin::Amount-->
                                <span
                                    class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">{{ number_format($analyticsData['avg_response_time'] ?? 0) }}</span>
                                <!--end::Amount-->
                                <!--begin::Subtitle-->
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Avg Response Time (ms)</span>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Title-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Card body-->
                        <div class="card-body d-flex flex-column justify-content-end pe-0">
                            <!--begin::Title-->
                            <span class="fs-6 fw-bolder text-white opacity-75 pt-1">
                                @php $changePercent = $analyticsData['response_time_change'] ?? 0 @endphp
                                {{ $changePercent >= 0 ? '+' : '' }}{{ number_format($changePercent, 1) }}% from last
                                period
                            </span>
                            <!--end::Title-->
                        </div>
                        <!--end::Card body-->
                    </div>
                    <!--end::Card widget 20-->
                </div>
                <!--end::Col-->
            </div>
            <!--end::Row-->

        </div>
        <!--end::Content container-->
    </div>
    <!--end::Content-->

    
</div>
