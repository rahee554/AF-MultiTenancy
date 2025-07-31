<!DOCTYPE html>
<html lang="en">

<head>
    <base href="../../">
    <title>
        {{ $title ?? 'Page Title' }}
    </title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />


    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Datatables -->
    <link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

    <!-- font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('assets/media/icons/fontawesome-pro-5.15.3-web/css/all.min.css') }}">
    <!--begin::Theme Css-->
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.theme.css') }}" rel="stylesheet" type="text/css" />

</head>

<body>

    @yield('content')
    @include('layouts.admin.scripts')

</body>

</html>
