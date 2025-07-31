@extends('artflow-tenancy::admin.layouts.app')

@section('title', 'System Dashboard')
@section('header', 'System Dashboard')

@section('content')
<div class="space-y-6">
    <!-- System Health Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full {{ $health['status'] === 'healthy' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                    <i class="fas {{ $health['status'] === 'healthy' ? 'fa-check' : 'fa-exclamation-triangle' }} text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">System Health</p>
                    <p class="text-lg font-bold {{ $health['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        {{ ucfirst($health['status']) }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-building text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Tenants</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total_tenants'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-play-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Tenants</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['active_tenants'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-memory text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Memory Usage</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['memory_usage'] }}MB</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Configuration Status -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Configuration Status</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Cache Configuration</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Driver:</span>
                            <span class="font-medium">{{ $cacheDriver }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Enhanced Cache Isolation:</span>
                            <span class="font-medium {{ $enhancedCacheEnabled ? 'text-green-600' : 'text-gray-500' }}">
                                {{ $enhancedCacheEnabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Session Configuration</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Driver:</span>
                            <span class="font-medium">{{ $sessionDriver }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Enhanced Session Isolation:</span>
                            <span class="font-medium {{ $enhancedSessionEnabled ? 'text-green-600' : 'text-gray-500' }}">
                                {{ $enhancedSessionEnabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <form action="{{ route('admin.tenancy.system.maintenance') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-trash mr-2"></i>
                        Clear Cache
                    </button>
                </form>
                
                <form action="{{ route('admin.tenancy.system.maintenance') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="optimize">
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-rocket mr-2"></i>
                        Optimize App
                    </button>
                </form>
                
                <form action="{{ route('admin.tenancy.system.maintenance') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="migrate_tenants">
                    <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-md transition duration-200" 
                            onclick="return confirm('This will migrate all tenant databases. Continue?')">
                        <i class="fas fa-database mr-2"></i>
                        Migrate Tenants
                    </button>
                </form>
                
                <a href="{{ route('admin.tenancy.system.configuration') }}" 
                   class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition duration-200 text-center inline-block">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </a>
            </div>
        </div>
    </div>
    
    <!-- System Health Details -->
    @if($health['status'] !== 'healthy')
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Health Check Details</h3>
        </div>
        <div class="p-6">
            @foreach($health['checks'] as $checkName => $check)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                    <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $checkName)) }}</span>
                    <div class="flex items-center">
                        <span class="mr-2 {{ $check['status'] === 'healthy' ? 'text-green-600' : ($check['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ ucfirst($check['status']) }}
                        </span>
                        <i class="fas {{ $check['status'] === 'healthy' ? 'fa-check text-green-600' : ($check['status'] === 'warning' ? 'fa-exclamation-triangle text-yellow-600' : 'fa-times text-red-600') }}"></i>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    // Auto-refresh every 30 seconds
    setInterval(function() {
        fetch('{{ route("admin.tenancy.system.stats") }}')
            .then(response => response.json())
            .then(data => {
                // Update stats without full page reload
                console.log('System stats updated', data);
            });
    }, 30000);
</script>
@endpush
@endsection
