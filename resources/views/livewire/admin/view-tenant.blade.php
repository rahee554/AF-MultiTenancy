<div>
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">Tenant Details</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('tenancy.admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('tenancy.admin.tenants.index') }}" class="text-muted text-hover-primary">Tenants</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">{{ $tenant->name }}</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <a href="{{ route('tenancy.admin.tenants.index') }}" class="btn btn-sm btn-light">
                    <i class="ki-duotone ki-arrow-left fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Back to Tenants
                </a>
                <button type="button" wire:click="migrateTenant" class="btn btn-sm btn-success">
                    <i class="ki-duotone ki-rocket fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Migrate Database
                </button>
                <button type="button" wire:click="seedTenant" class="btn btn-sm btn-warning">
                    <i class="ki-duotone ki-seed fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Seed Database
                </button>
                <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_manage_tenant">
                    <i class="ki-duotone ki-setting-2 fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Manage Tenant
                </a>
            </div>
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Content-->
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">

            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                    <!--begin::Card widget 20-->
                        <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-md-50 mb-5 mb-xl-10" style="background-color: #F1416C;background-image:url('{{ asset('vendor/artflow-studio/tenancy/media/patterns/vector-1.png') }}')">
                        <!--begin::Header-->
                        <div class="card-header pt-5">
                            <!--begin::Title-->
                            <div class="card-title d-flex flex-column">
                                <!--begin::Amount-->
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">{{ $tenant->name }}</span>
                                <!--end::Amount-->
                                <!--begin::Subtitle-->
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Tenant Information</span>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Title-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Card body-->
                        <div class="card-body d-flex flex-column justify-content-end pe-0">
                            <!--begin::Title-->
                            <span class="fs-6 fw-bolder text-white opacity-75 pt-1">ID: {{ $tenant->id }}</span>
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
                                    <span class="fs-4 fw-semibold text-gray-500 me-1 align-self-start">Domain</span>
                                    <!--end::Currency-->
                                    <!--begin::Amount-->
                                    <span class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2">{{ $tenant->domains->first()?->domain ?? 'N/A' }}</span>
                                    <!--end::Amount-->
                                </div>
                                <!--end::Info-->
                                <!--begin::Subtitle-->
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">Primary Domain</span>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Title-->
                        </div>
                        <!--end::Header-->
                        <!--begin::Card body-->
                        <div class="card-body pt-2 pb-4 d-flex flex-wrap align-items-center">
                            <!--begin::Chart-->
                            <div class="d-flex flex-center me-5 pt-2">
                                <div id="kt_card_widget_7_chart" style="min-width: 70px; min-height: 70px" data-kt-size="70" data-kt-line="11"></div>
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
                                    <div class="text-gray-500 flex-grow-1 me-4">Active</div>
                                    <!--end::Label-->
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
            </div>

            <!--begin::Row-->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <!--begin::Col-->
                <div class="col-xl-12">
                    <!--begin::Tables Widget 5-->
                    <div class="card card-flush h-xl-100">
                        <!--begin::Card header-->
                        <div class="card-header pt-7">
                            <!--begin::Title-->
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-900">Database Status</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Tenant database information</span>
                            </h3>
                            <!--end::Title-->
                            <!--begin::Actions-->
                            <div class="card-toolbar">
                                <a href="#" class="btn btn-sm btn-light">
                                    <i class="ki-duotone ki-arrows-circle fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Refresh
                                </a>
                            </div>
                            <!--end::Actions-->
                        </div>
                        <!--end::Card header-->
                        <!--begin::Card body-->
                        <div class="card-body">
                            @php
                                $status = app(\ArtflowStudio\Tenancy\Services\TenantService::class)->getTenantStatus($tenant);
                            @endphp
                            
                            <!--begin::Table-->
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle gs-0 gy-3 my-0">
                                    <!--begin::Table head-->
                                    <thead>
                                        <tr class="fs-7 fw-bold text-gray-500 border-bottom-0">
                                            <th class="p-0 pb-3 min-w-175px text-start">Metric</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">Value</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <!--end::Table head-->
                                    <!--begin::Table body-->
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-50px me-3">
                                                        <span class="symbol-label bg-light-primary">
                                                            <i class="ki-duotone ki-database fs-2x text-primary">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-start flex-column">
                                                        <a href="#" class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">Database Exists</a>
                                                        <span class="text-gray-500 fw-semibold d-block fs-7">Database connectivity</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-900 fw-bold d-block fs-6">{{ $status['database_exists'] ? 'Yes' : 'No' }}</span>
                                            </td>
                                            <td class="text-end">
                                                @if($status['database_exists'])
                                                    <span class="badge badge-light-success fs-7 fw-bold">Connected</span>
                                                @else
                                                    <span class="badge badge-light-danger fs-7 fw-bold">Not Found</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-50px me-3">
                                                        <span class="symbol-label bg-light-warning">
                                                            <i class="ki-duotone ki-code fs-2x text-warning">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                                <span class="path4"></span>
                                                            </i>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-start flex-column">
                                                        <a href="#" class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">Migrations</a>
                                                        <span class="text-gray-500 fw-semibold d-block fs-7">Schema migrations count</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-900 fw-bold d-block fs-6">{{ number_format($status['migration_count']) }}</span>
                                            </td>
                                            <td class="text-end">
                                                @if($status['migration_count'] > 0)
                                                    <span class="badge badge-light-success fs-7 fw-bold">Migrated</span>
                                                @else
                                                    <span class="badge badge-light-warning fs-7 fw-bold">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-50px me-3">
                                                        <span class="symbol-label bg-light-info">
                                                            <i class="ki-duotone ki-tablet-book fs-2x text-info">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                            </i>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-start flex-column">
                                                        <a href="#" class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">Database Tables</a>
                                                        <span class="text-gray-500 fw-semibold d-block fs-7">Total table count</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-900 fw-bold d-block fs-6">{{ number_format($status['table_count']) }}</span>
                                            </td>
                                            <td class="text-end">
                                                @if($status['table_count'] > 0)
                                                    <span class="badge badge-light-info fs-7 fw-bold">Active</span>
                                                @else
                                                    <span class="badge badge-light-secondary fs-7 fw-bold">Empty</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-50px me-3">
                                                        <span class="symbol-label bg-light-success">
                                                            <i class="ki-duotone ki-chart-simple fs-2x text-success">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                                <span class="path4"></span>
                                                            </i>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-start flex-column">
                                                        <a href="#" class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">Database Size</a>
                                                        <span class="text-gray-500 fw-semibold d-block fs-7">Storage usage in MB</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-900 fw-bold d-block fs-6">{{ number_format($status['database_size'], 2) }} MB</span>
                                            </td>
                                            <td class="text-end">
                                                @if($status['database_size'] > 100)
                                                    <span class="badge badge-light-warning fs-7 fw-bold">Large</span>
                                                @elseif($status['database_size'] > 10)
                                                    <span class="badge badge-light-info fs-7 fw-bold">Medium</span>
                                                @else
                                                    <span class="badge badge-light-success fs-7 fw-bold">Small</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                    <!--end::Table body-->
                                </table>
                            </div>
                            <!--end::Table-->
                        </div>
                        <!--end::Card body-->
                    </div>
                    <!--end::Tables Widget 5-->
                </div>
                <!--end::Col-->
            </div>
            <!--end::Row-->

        </div>
        <!--end::Content container-->
    </div>
    <!--end::Content-->

</div>
