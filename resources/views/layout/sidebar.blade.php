<!--begin::Sidebar-->
<div id="kt_app_sidebar" class="app-sidebar  flex-column " data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="250px"
    data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <!--begin::Wrapper-->
    <div id="kt_app_sidebar_wrapper" class="app-sidebar-wrapper">

        <div class="hover-scroll-y my-5 my-lg-2 mx-4" data-kt-scroll="true"
            data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto"
            data-kt-scroll-dependencies="#kt_app_header" data-kt-scroll-wrappers="#kt_app_sidebar_wrapper"
            data-kt-scroll-offset="5px">

            <!--begin::Sidebar menu-->
            <div id="#kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false"
                class="app-sidebar-menu-primary menu menu-column menu-rounded menu-sub-indention menu-state-bullet-primary px-3 mb-5">

                <!-- Dashboard -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.dashboard*') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.dashboard') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-element-11 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Dashboard</span>
                    </a>
                </div>

                <!-- Tenants Management -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.index') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.index') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-buildings fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Tenants</span>
                    </a>
                </div>

                <!-- Create Tenant -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.create') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.create') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-plus fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">Create Tenant</span>
                    </a>
                </div>

                <!-- System Monitoring -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.monitoring') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.monitoring') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-monitor fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">System Monitor</span>
                    </a>
                </div>

                <!-- Queue Monitoring -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.queue') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.queue') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-timer fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                        <span class="menu-title">Queue Monitor</span>
                    </a>
                </div>

                <!-- Analytics -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.analytics') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.analytics') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-chart-simple fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Analytics</span>
                    </a>
                </div>

                <!-- Separator -->
                <div class="menu-separator my-4"></div>

                <!-- API Configuration Section -->
                <div class="menu-item pt-5">
                    <div class="menu-content">
                        <span class="menu-heading fw-bold text-uppercase fs-7">API Management</span>
                    </div>
                </div>

                <!-- API Settings -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.api.settings') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.api.settings') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-setting-3 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </span>
                        <span class="menu-title">API Settings</span>
                    </a>
                </div>

                <!-- API Keys -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.api.keys') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.api.keys') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-key fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <span class="menu-title">API Keys</span>
                    </a>
                </div>

                <!-- API Endpoints -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.api.endpoints') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.api.endpoints') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-delivery fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </span>
                        <span class="menu-title">API Endpoints</span>
                    </a>
                </div>

                <!-- API Documentation -->
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('tenancy.admin.api.docs') ? 'active' : '' }}" 
                       href="{{ route('tenancy.admin.api.docs') }}" wire:navigate>
                        <span class="menu-icon">
                            <i class="ki-duotone ki-book fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">API Documentation</span>
                    </a>
                </div>

            </div>
        </div>
        <!--end::Menu-->
    </div>
    <!--end::Aside menu-->
    <!--begin::Footer-->
    <div class="aside-footer flex-column-auto pb-5 d-none" id="kt_aside_footer">
        <a href="{{ route('tenancy.admin.dashboard') }}" class="btn btn-light-primary w-100">Go to Dashboard</a>
    </div>
    <!--end::Footer-->
</div>
<!--end::Aside-->
