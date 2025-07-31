<head>
    <title>{{ $title ?? 'Artflow Tenancy Admin' }}</title>
    <meta charset="utf-8" />
    <meta name="description" content="Artflow Tenancy Administration Panel" />
    <meta name="keywords" content="tenancy, multi-tenant, admin, dashboard" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Artflow Tenancy Admin" />
    <meta property="og:site_name" content="Artflow Tenancy" />
    <link rel="shortcut icon" href="{{ asset('vendor/artflow-studio/tenancy/media/logos/favicon.ico') }}" />
    
    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    
    <!-- Global Stylesheets Bundle (Used by all pages) -->
    <link href="https://preview.keenthemes.com/metronic8/demo1/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="{{ asset('vendor/artflow-studio/tenancy/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    
    <!-- Custom Tenancy Styles -->
    <style>
        .app-sidebar .menu .menu-item .menu-link.active {
            background-color: var(--kt-primary-light);
            color: var(--kt-primary);
        }
        .symbol-label {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
    </style>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @livewireStyles
</head>
