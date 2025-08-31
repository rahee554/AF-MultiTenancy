@extends('artflow-tenancy::admin.layouts.app')

@section('title', 'Manage Quotas - ' . $tenant->name)
@section('header', 'Manage Quotas: ' . $tenant->name)

@section('content')
<div class="space-y-6">
    <!-- Tenant Info -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</h3>
                <p class="text-gray-600">ID: {{ $tenant->id }}</p>
                @if($tenant->domains->count() > 0)
                    <p class="text-gray-600">Domain: {{ $tenant->domains->first()->domain }}</p>
                @endif
            </div>
            <div>
                <a href="{{ route('admin.tenancy.tenants.show', $tenant) }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Tenant
                </a>
            </div>
        </div>
    </div>
    
    <!-- Quota Summary -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Quota Overview</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $quotaSummary['total_quotas'] }}</div>
                    <div class="text-sm text-gray-600">Total Quotas</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $quotaSummary['ok_count'] }}</div>
                    <div class="text-sm text-gray-600">OK</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ $quotaSummary['warning_count'] }}</div>
                    <div class="text-sm text-gray-600">Warning</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $quotaSummary['exceeded_count'] }}</div>
                    <div class="text-sm text-gray-600">Exceeded</div>
                </div>
            </div>
            
            <div class="flex items-center">
                <span class="text-sm font-medium text-gray-600">Overall Status:</span>
                <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium
                    {{ $quotaSummary['overall_status'] === 'ok' ? 'bg-green-100 text-green-800' : 
                       ($quotaSummary['overall_status'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                    {{ ucfirst($quotaSummary['overall_status']) }}
                </span>
            </div>
        </div>
    </div>
    
    <!-- Quota Management -->
    <form action="{{ route('admin.tenancy.tenants.quotas.update', $tenant) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Resource Quotas</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resource</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Usage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Limit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warning Threshold</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enforcement</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($quotas as $quota)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ ucwords(str_replace('_', ' ', $quota->resource_type)) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ number_format($quota->current_usage) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="quotas[{{ $quota->resource_type }}][limit]" 
                                           value="{{ $quota->quota_limit }}"
                                           class="w-24 px-2 py-1 border border-gray-300 rounded-md text-sm">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <div class="h-2 rounded-full {{ $quota->status === 'exceeded' ? 'bg-red-500' : ($quota->status === 'warning' ? 'bg-yellow-500' : 'bg-green-500') }}" 
                                                 style="width: {{ min(100, $quota->usage_percentage) }}%"></div>
                                        </div>
                                        <span class="text-sm {{ $quota->status === 'exceeded' ? 'text-red-600' : ($quota->status === 'warning' ? 'text-yellow-600' : 'text-green-600') }}">
                                            {{ number_format($quota->usage_percentage, 1) }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="quotas[{{ $quota->resource_type }}][warning_threshold]" 
                                           value="{{ $quota->warning_threshold }}"
                                           min="0" max="100" step="0.1"
                                           class="w-20 px-2 py-1 border border-gray-300 rounded-md text-sm">
                                    <span class="text-xs text-gray-500">%</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               name="quotas[{{ $quota->resource_type }}][enforcement_enabled]" 
                                               value="1"
                                               {{ $quota->enforcement_enabled ? 'checked' : '' }}
                                               class="form-checkbox h-4 w-4 text-blue-600">
                                        <span class="ml-2 text-sm text-gray-600">Enforce</span>
                                    </label>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form action="{{ route('admin.tenancy.tenants.quotas.reset', $tenant) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="resource_type" value="{{ $quota->resource_type }}">
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-900 transition duration-200"
                                                onclick="return confirm('Reset usage for {{ $quota->resource_type }}?')">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    No quotas configured. Default quotas will be created automatically.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end">
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md transition duration-200">
                    <i class="fas fa-save mr-2"></i>
                    Update Quotas
                </button>
            </div>
        </div>
    </form>
    
    <!-- Recommendations -->
    @if(!empty($recommendations))
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                Recommendations
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @foreach($recommendations as $resource => $recommendation)
                    <div class="border border-gray-200 rounded-lg p-4 {{ $recommendation['priority'] === 'high' ? 'border-red-300 bg-red-50' : ($recommendation['priority'] === 'medium' ? 'border-yellow-300 bg-yellow-50' : 'border-blue-300 bg-blue-50') }}">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-{{ $recommendation['priority'] === 'high' ? 'exclamation-triangle text-red-500' : ($recommendation['priority'] === 'medium' ? 'exclamation-circle text-yellow-500' : 'info-circle text-blue-500') }}"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $resource)) }}</h4>
                                <p class="text-sm text-gray-600 mt-1">{{ $recommendation['message'] }}</p>
                                <p class="text-sm font-medium mt-2">
                                    Suggested limit: {{ number_format($recommendation['suggested_limit']) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
