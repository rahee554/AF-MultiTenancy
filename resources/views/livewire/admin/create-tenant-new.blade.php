<div>
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">Create Tenant</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('tenancy.admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Create Tenant</li>
                </ul>
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
                    <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
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
                    <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
                        <i class="ki-duotone ki-cross fs-1 text-danger">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            @endif

            <!--begin::Row-->
            <div class="row">
                <div class="col-lg-8">
                    <!--begin::Card-->
                    <div class="card card-custom">
                        <!--begin::Header-->
                        <div class="card-header">
                            <div class="card-title">
                                <h3 class="card-label">
                                    <i class="ki-duotone ki-buildings fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Tenant Information
                                </h3>
                            </div>
                        </div>
                        <!--end::Header-->
                        <!--begin::Form-->
                        <form wire:submit.prevent="create">
                            <!--begin::Body-->
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <!--begin::Form Group-->
                                        <div class="form-group mb-8">
                                            <label class="fs-6 fw-semibold form-label required">Tenant Name</label>
                                            <input type="text" 
                                                   class="form-control form-control-solid @error('name') is-invalid @enderror" 
                                                   wire:model.defer="name" 
                                                   placeholder="Enter tenant name">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <!--end::Form Group-->

                                        <!--begin::Form Group-->
                                        <div class="form-group mb-8">
                                            <label class="fs-6 fw-semibold form-label required">Domain</label>
                                            <input type="text" 
                                                   class="form-control form-control-solid @error('domain') is-invalid @enderror" 
                                                   wire:model.defer="domain" 
                                                   placeholder="example.com">
                                            @error('domain')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <!--end::Form Group-->

                                        <!--begin::Form Group-->
                                        <div class="form-group mb-8">
                                            <label class="fs-6 fw-semibold form-label">Status</label>
                                            <select class="form-select form-select-solid" wire:model.defer="status">
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="suspended">Suspended</option>
                                                <option value="blocked">Blocked</option>
                                            </select>
                                            @error('status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <!--end::Form Group-->
                                    </div>

                                    <div class="col-lg-6">
                                        <!--begin::Form Group-->
                                        <div class="form-group mb-8">
                                            <label class="fs-6 fw-semibold form-label">Database Name</label>
                                            <input type="text" 
                                                   class="form-control form-control-solid @error('databaseName') is-invalid @enderror" 
                                                   wire:model.defer="databaseName" 
                                                   placeholder="tenant_database (optional)">
                                            <div class="form-text">If provided it will be used as-is; otherwise an auto-prefixed name like <code>tenant_my_tenant</code> will be generated.</div>
                                            @error('databaseName')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <!--end::Form Group-->

                                        <!--begin::Form Group-->
                                        <div class="form-group mb-8">
                                            <label class="fs-6 fw-semibold form-label">Notes</label>
                                            <textarea class="form-control form-control-solid @error('notes') is-invalid @enderror" 
                                                      wire:model.defer="notes" 
                                                      rows="3"
                                                      placeholder="Optional notes about this tenant"></textarea>
                                            @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <!--end::Form Group-->
                                    </div>
                                </div>

                                <!--begin::Checkboxes-->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-check form-check-custom form-check-solid mb-5">
                                            <input class="form-check-input" type="checkbox" wire:model.defer="hasHomepage" id="hasHomepage">
                                            <label class="form-check-label fw-semibold text-gray-700" for="hasHomepage">
                                                Create homepage for tenant
                                            </label>
                                        </div>

                                        <div class="form-check form-check-custom form-check-solid mb-8">
                                            <input class="form-check-input" type="checkbox" wire:model.defer="useQueue" id="useQueue">
                                            <label class="form-check-label fw-semibold text-gray-700" for="useQueue">
                                                Use queue for creation (recommended for large tenants)
                                            </label>
                                        </div>

                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                            <input class="form-check-input" type="checkbox" wire:model="doMigrate" id="doMigrate">
                                            <label class="form-check-label fw-semibold text-gray-700" for="doMigrate">
                                                Run migrations after creation
                                            </label>
                                        </div>

                                        <div class="form-check form-check-custom form-check-solid mb-8">
                                            <input class="form-check-input" type="checkbox" wire:model="doSeed" id="doSeed" @if(! $doMigrate) disabled @endif>
                                            <label class="form-check-label fw-semibold text-gray-700" for="doSeed">
                                                Run seeders after migration
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Checkboxes-->
                            </div>
                            <!--end::Body-->
                            
                            <!--begin::Actions-->
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <a href="{{ route('tenancy.admin.dashboard') }}" class="btn btn-light me-3">
                                            <i class="ki-duotone ki-arrow-left fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Cancel
                                        </a>
                                    </div>
                                    <div class="col-lg-6 text-end">
                                        <button type="submit" 
                                                class="btn btn-primary" 
                                                @if($isCreating) disabled @endif>
                                            @if($isCreating)
                                                <span class="spinner-border spinner-border-sm align-middle me-3"></span>
                                                Creating...
                                            @else
                                                <i class="ki-duotone ki-plus fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Create Tenant
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!--end::Actions-->
                        </form>
                        <!--end::Form-->
                    </div>
                    <!--end::Card-->
                </div>

                <div class="col-lg-4">
                    <!--begin::Progress Card-->
                    @if($isCreating && $jobId)
                        <div class="card bg-light-primary">
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <span class="card-label fw-bold text-primary">Creation Progress</span>
                                </h3>
                            </div>
                            <div class="card-body pt-2">
                                <div class="d-flex align-items-center mb-7">
                                    <span class="text-muted fw-semibold fs-6 me-2">Current Step:</span>
                                    <span class="fw-bold text-gray-800 fs-6">{{ $currentStep ?: 'Initializing...' }}</span>
                                </div>

                                <div class="d-flex align-items-center mb-3">
                                    <span class="text-muted fw-semibold fs-6 me-2">Progress:</span>
                                    <span class="fw-bold text-primary fs-6">{{ $progressPercentage }}%</span>
                                </div>

                                <div class="progress h-6px w-100 bg-light-primary mb-7">
                                    <div class="progress-bar bg-primary" 
                                         role="progressbar" 
                                         style="width: {{ $progressPercentage }}%" 
                                         aria-valuenow="{{ $progressPercentage }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>

                                @if($jobId)
                                    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-6">
                                        <i class="ki-duotone ki-information-5 fs-2tx text-info me-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div class="d-flex flex-stack flex-grow-1">
                                            <div class="fw-semibold">
                                                <div class="fs-6 text-gray-700">Job ID: {{ $jobId }}</div>
                                                <div class="fs-7 text-gray-600">Track this creation process</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <!--begin::Help Card-->
                        <div class="card bg-light-info">
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <span class="card-label fw-bold text-info">
                                        <i class="ki-duotone ki-information-5 fs-3 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Creation Tips
                                    </span>
                                </h3>
                            </div>
                            <div class="card-body pt-2">
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-check-circle fs-5 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-semibold fs-6 text-gray-700">Choose a unique domain</span>
                                    </div>
                                    <div class="text-muted fs-7 ms-7">Ensure the domain is not already in use</div>
                                </div>

                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-check-circle fs-5 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-semibold fs-6 text-gray-700">Use queue for large tenants</span>
                                    </div>
                                    <div class="text-muted fs-7 ms-7">Queue creation for better performance</div>
                                </div>

                                <div class="mb-0">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-check-circle fs-5 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-semibold fs-6 text-gray-700">Add descriptive notes</span>
                                    </div>
                                    <div class="text-muted fs-7 ms-7">Help identify the tenant purpose</div>
                                </div>
                            </div>
                        </div>
                        <!--end::Help Card-->
                    @endif
                    <!--end::Progress Card-->
                </div>
            </div>
            <!--end::Row-->

        </div>
        <!--end::Content container-->
    </div>
    <!--end::Content-->

    @if($isCreating && $jobId)
        <div wire:poll.2s="checkProgress"></div>
    @endif

    @push('scripts')
    <script>
        window.addEventListener('tenant-created', function (ev) {
            // show done state for 1s then redirect
            setTimeout(function(){
                if (ev?.detail?.url) {
                    window.location.href = ev.detail.url;
                }
            }, 1000);
        });
    </script>
    @endpush

</div>
