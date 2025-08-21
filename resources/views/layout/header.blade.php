 <!--begin::Header-->
 <div id="kt_app_header" class="app-header  d-flex flex-column flex-stack ">

     <!--begin::Header main-->
     <div class="d-flex flex-stack flex-grow-1">

         <div class="app-header-logo d-flex align-items-center ps-lg-12" id="kt_app_header_logo">
             <!--begin::Sidebar toggle-->
             <div id="kt_app_sidebar_toggle"
                 class="app-sidebar-toggle btn btn-sm btn-icon bg-body btn-color-gray-500 btn-active-color-primary w-30px h-30px ms-n2 me-4 d-none d-lg-flex "
                 data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                 data-kt-toggle-name="app-sidebar-minimize">

                 <i class="ki-outline ki-abstract-14 fs-3 mt-1"></i>
             </div>
             <!--end::Sidebar toggle-->

             <!--begin::Sidebar mobile toggle-->
             <div class="btn btn-icon btn-active-color-primary w-35px h-35px ms-3 me-2 d-flex d-lg-none"
                 id="kt_app_sidebar_mobile_toggle">
                 <i class="ki-outline ki-abstract-14 fs-2"></i>
             </div>
             <!--end::Sidebar mobile toggle-->

             <!--begin::Logo-->
             {{-- <a href="/dashboard">
                <img alt="Logo" src="{{ asset('businesses/' . $dbDomainName . '/brand/' . $logoHorizontal) }}"
                    class="theme-light-show h-50px logo-default cw-200px" alt="ss" />

                <img alt="Logo" src="{{ asset('businesses/' . $dbDomainName . '/brand/' . $logoHorizontalDark) }}"
                    class="theme-dark-show  h-50px logo-default cw-200px" alt="ss" />

            </a> --}}

             <!--end::Logo-->
         </div>

         <!--begin::Navbar-->
         <div class="app-navbar flex-grow-1 justify-content-end" id="kt_app_header_navbar">
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Actions
                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('tenancy.admin.create') }}" wire:navigate>Create Member</a></li>
                                </ul>
              </div>
    

             <!--begin::User menu-->
             <div class="app-navbar-item ms-2 ms-lg-6 me-lg-6" id="kt_header_user_menu_toggle">
                 <!--begin::Menu wrapper-->
                 <div class="cursor-pointer symbol symbol-circle symbol-30px symbol-lg-45px"
                     data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent"
                     data-kt-menu-placement="bottom-end">
                     <img src="https://preview.keenthemes.com/metronic8/demo1/assets/media/avatars/blank.png" alt="user" />
                 </div>

                 <!--begin::User account menu-->
                 <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                     data-kt-menu="true">
                     <!--begin::Menu item-->
                     <div class="menu-item px-3">
                         <div class="menu-content d-flex align-items-center px-3">
                             <!--begin::Avatar-->
                             <div class="symbol symbol-50px me-5">
                                 <img alt="Logo" src="https://i.pravatar.cc/150" />
                             </div>
                             <!--end::Avatar-->

                             <!--begin::Username-->
                             <div class="d-flex flex-column">
                                 <div class="fw-bold d-flex align-items-center fs-5">
                                     Max Smith <span
                                         class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">Pro</span>
                                 </div>

                                 <a href="#" class="fw-semibold text-muted text-hover-primary fs-7">
                                     max@kt.com </a>
                             </div>
                             <!--end::Username-->
                         </div>
                     </div>
                     <!--end::Menu item-->

                     <!--begin::Menu separator-->
                     <div class="separator my-2"></div>
                     <!--end::Menu separator-->

                     <!--begin::Menu item-->
                     <div class="menu-item px-5">
                         <a href="account/overview.html" class="menu-link px-5">
                             My Profile
                         </a>
                     </div>
                     <!--end::Menu item-->



                     <!--begin::Menu separator-->
                     <div class="separator my-2"></div>
                     <!--end::Menu separator-->

                     <!--begin::Menu item-->
                     <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                         data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                         <a href="#" class="menu-link px-5">
                             <span class="menu-title position-relative">
                                 Mode

                                 <span class="ms-5 position-absolute translate-middle-y top-50 end-0">
                                     <i class="ki-outline ki-night-day theme-light-show fs-2"></i> <i
                                         class="ki-outline ki-moon theme-dark-show fs-2"></i> </span>
                             </span>
                         </a>

                         <!--begin::Menu-->
                         <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
                             data-kt-menu="true" data-kt-element="theme-mode-menu">
                             <!--begin::Menu item-->
                             <div class="menu-item px-3 my-0">
                                 <a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
                                     data-kt-value="light">
                                     <span class="menu-icon" data-kt-element="icon">
                                         <i class="ki-outline ki-night-day fs-2"></i> </span>
                                     <span class="menu-title">
                                         Light
                                     </span>
                                 </a>
                             </div>
                             <!--end::Menu item-->

                             <!--begin::Menu item-->
                             <div class="menu-item px-3 my-0">
                                 <a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
                                     data-kt-value="dark">
                                     <span class="menu-icon" data-kt-element="icon">
                                         <i class="ki-outline ki-moon fs-2"></i> </span>
                                     <span class="menu-title">
                                         Dark
                                     </span>
                                 </a>
                             </div>
                             <!--end::Menu item-->

                             <!--begin::Menu item-->
                             <div class="menu-item px-3 my-0">
                                 <a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
                                     data-kt-value="system">
                                     <span class="menu-icon" data-kt-element="icon">
                                         <i class="ki-outline ki-screen fs-2"></i> </span>
                                     <span class="menu-title">
                                         System
                                     </span>
                                 </a>
                             </div>
                             <!--end::Menu item-->
                         </div>
                         <!--end::Menu-->

                     </div>
                     <!--end::Menu item-->



                     <!--begin::Menu item-->
                     <div class="menu-item px-5 my-1">
                         <a href="account/settings.html" class="menu-link px-5">
                             Account Settings
                         </a>
                     </div>
                     <!--end::Menu item-->

                     <!--begin::Menu item-->
                     <div class="menu-item px-5">
                         <a class="dropdown-item" href="{{ route('logout') }}"
                             onclick="event.preventDefault();
                                                    document.getElementById('logout-form').submit();">
                             {{ __('Logout') }}
                         </a>

                         <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                             @csrf
                         </form>
                     </div>
                     <!--end::Menu item-->
                 </div>
                 <!--end::User account menu-->
                 <!--end::Menu wrapper-->
             </div>
             <!--end::User menu-->


             <!--begin::Header menu toggle-->
             <div class="app-navbar-item ms-2 ms-lg-6 ms-n2 me-3 d-flex d-lg-none">

             </div>
             <!--end::Header menu toggle-->
         </div>
         <!--end::Navbar-->
     </div>
     <!--end::Header main-->

 </div>
 <!--end::Header-->
