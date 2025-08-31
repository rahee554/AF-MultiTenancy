<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tenancy Admin') - {{ config('app.name') }}</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        [x-cloak] { display: none !important; }
    </style>
    
    @stack('styles')
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-gray-800 text-white w-64 min-h-screen">
            <div class="p-4">
                <h1 class="text-xl font-bold">Tenancy Admin</h1>
            </div>
            
            <nav class="mt-8">
                <a href="{{ route('admin.tenancy.system.dashboard') }}" 
                   class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white {{ request()->routeIs('admin.tenancy.system.*') ? 'bg-gray-700 text-white' : '' }}">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    System Dashboard
                </a>
                
                <a href="{{ route('admin.tenancy.tenants.index') }}" 
                   class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white {{ request()->routeIs('admin.tenancy.tenants.*') ? 'bg-gray-700 text-white' : '' }}">
                    <i class="fas fa-building mr-3"></i>
                    Tenants
                </a>
                
                <a href="{{ route('admin.tenancy.system.configuration') }}" 
                   class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white {{ request()->routeIs('admin.tenancy.system.configuration') ? 'bg-gray-700 text-white' : '' }}">
                    <i class="fas fa-cog mr-3"></i>
                    Configuration
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">
                            @yield('header')
                        </h2>
                        
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-500">{{ now()->format('M j, Y g:i A') }}</span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Alerts -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mx-6 mt-4 rounded">
                    <i class="fas fa-check-circle mr-2"></i>
                    {{ session('success') }}
                </div>
            @endif
            
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mx-6 mt-4 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    {{ session('error') }}
                </div>
            @endif
            
            @if(session('info'))
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 mx-6 mt-4 rounded">
                    <i class="fas fa-info-circle mr-2"></i>
                    {{ session('info') }}
                </div>
            @endif
            
            @if(session('warning'))
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 mx-6 mt-4 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    {{ session('warning') }}
                </div>
            @endif
            
            <!-- Content -->
            <main class="p-6">
                @yield('content')
            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    @stack('scripts')
</body>
</html>
