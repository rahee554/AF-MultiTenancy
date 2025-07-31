<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<!--begin::Head-->
@include('artflow-tenancy::layout.head')
<!--end::Head-->
<!--begin::Body-->

<body id="kt_app_body" data-kt-app-header-fixed="true" data-kt-app-header-fixed-mobile="true"
    data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-toolbar="true" data-kt-app-sidebar-push-footer="true" class="app-default">






    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Page-->
        <div class="app-page  flex-column flex-column-fluid " id="kt_app_page">


            @include('artflow-tenancy::layout.header')

            <!--begin::Wrapper-->
            <div class="app-wrapper  flex-column flex-row-fluid " id="kt_app_wrapper"> <!--begin::Sidebar-->
                @include('artflow-tenancy::layout.sidebar')


                <!--begin::Main-->
                <div class="app-main flex-column flex-row-fluid " id="kt_app_main">
                    <!--begin::Content wrapper-->
                    <div class="d-flex flex-column flex-column-fluid">


                        <!--begin::Content-->
                        <div id="kt_app_content"
                            class="app-content flex-column-fluid p-4 p-md-6 p-lg-8 p-XL-10 p-xxl-12">

                            <div class="page-heading">
                                <div class="app-toolbar-wrapper d-flex flex-stack flex-wrap gap-4 w-100">


                                    <!--begin::Page title-->
                                    <div class="page-title d-flex flex-column justify-content-center gap-1 me-3">
                                        <!--begin::Title-->
                                        <h1
                                            class="page-heading d-flex flex-column justify-content-center text-gray-900 fw-bold fs-3 m-0">
                                            @stack('page-title')
                                        </h1>
                                        <!--end::Title-->


                                    </div>
                                    <!--end::Page title-->
                                    <!--begin::Actions-->
                                    <div class="d-flex align-items-center gap-2 gap-lg-3">
                                        @stack('page-action-btns')

                                    </div>
                                    <!--end::Actions-->
                                </div>
                            </div>
                            <div class="container p-0 my-2">
                                @yield('content')
                              

                            </div>

                        </div>
                        <!--end::Content-->

                    </div>
                    <!--end::Content wrapper-->


                    <!--begin::Footer-->
                    @include('artflow-tenancy::layout.footer')
                    <!--end::Footer-->
                </div>
                <!--end:::Main-->


            </div>
            <!--end::Wrapper-->


        </div>
        <!--end::Page-->
        <!--begin::Scrolltop-->
        <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
            <i class="ki-outline ki-arrow-up"></i>
        </div>
        <!--end::Scrolltop-->

    </div>
    <!--end::App-->

    <!--end::Drawers-->





    @livewireScripts
    @include('artflow-tenancy::layout.scripts')
    @stack('scripts')





    <!--begin::Theme mode setup on page load-->
    <script data-navigate-once>
        var defaultThemeMode = "light";
        var themeMode;

        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }

            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }

            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>
    <!--end::Theme mode setup on page load-->

    <!-- begin::Session Message -->
    @if (app()->environment('local'))
        @if (session('success'))
            <script type="text/javascript">
                console.log('{{ session('success') }}');
            </script>
        @endif
        @if (session('error'))
            <script type="text/javascript">
                console.error('{{ session('error') }}');
            </script>
        @endif
    @endif


</body>

</html>
