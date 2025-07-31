<!-- Global Javascript Bundle (Used by all pages) -->
<script src="https://preview.keenthemes.com/metronic8/demo1/assets/plugins/global/plugins.bundle.js"></script>
<script src="{{ asset('vendor/artflow-studio/tenancy/js/scripts.bundle.js') }}"></script>

<!-- Livewire Scripts -->
@livewireScripts

<!-- Custom Tenancy Scripts -->
<script>
    document.addEventListener('livewire:navigated', () => {
        // Reinitialize Metronic components after Livewire navigation
        if (typeof KTMenu !== 'undefined') {
            KTMenu.createInstances();
        }
        if (typeof KTDrawer !== 'undefined') {
            KTDrawer.createInstances();
        }
        if (typeof KTToggle !== 'undefined') {
            KTToggle.createInstances();
        }
        if (typeof KTScrolltop !== 'undefined') {
            KTScrolltop.createInstances();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Metronic components on page load
        if (typeof KTUtil !== 'undefined') {
            KTUtil.onDOMContentLoaded(() => {
                // Menu
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                // Drawer
                if (typeof KTDrawer !== 'undefined') {
                    KTDrawer.createInstances();
                }
                // Toggle
                if (typeof KTToggle !== 'undefined') {
                    KTToggle.createInstances();
                }
                // Scrolltop
                if (typeof KTScrolltop !== 'undefined') {
                    KTScrolltop.createInstances();
                }
            });
        }
    });

    // Handle Livewire loading states
    document.addEventListener('livewire:loading', (event) => {
        // Show loading spinner
        if (event.detail.component.name) {
            console.log('Loading:', event.detail.component.name);
        }
    });

    document.addEventListener('livewire:loaded', (event) => {
        // Hide loading spinner
        if (event.detail.component.name) {
            console.log('Loaded:', event.detail.component.name);
        }
    });
</script>

<!-- Session Messages -->
@if(session('success'))
    <script>
        toastr.success("{{ session('success') }}");
    </script>
@endif

@if(session('error'))
    <script>
        toastr.error("{{ session('error') }}");
    </script>
@endif

@if(session('warning'))
    <script>
        toastr.warning("{{ session('warning') }}");
    </script>
@endif

@if(session('info'))
    <script>
        toastr.info("{{ session('info') }}");
    </script>
@endif
