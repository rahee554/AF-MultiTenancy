@php
    $layout = config('tenancy.admin.layout', 'layouts.app');
@endphp

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-800">Tenant Management</h1>
        <button wire:click="refreshStats" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Refresh
        </button>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <input wire:model.live="search" type="text" placeholder="Search tenants..." 
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    <!-- Tenants Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Database</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($tenants as $tenant)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $tenant->name }}</div>
                                <div class="text-sm text-gray-500">{{ Str::limit($tenant->id, 20) }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900">
                                {{ $tenant->domains->first()->domain ?? 'No domain' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                {{ $tenant->status === 'active' ? 'bg-green-100 text-green-800' : 
                                   ($tenant->status === 'inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst($tenant->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $tenant->database ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tenant->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <button wire:click="openQuotaModal('{{ $tenant->id }}')" 
                                    class="text-blue-600 hover:text-blue-900">Quotas</button>
                            <button wire:click="openSettingsModal('{{ $tenant->id }}')" 
                                    class="text-green-600 hover:text-green-900">Settings</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $tenants->links() }}
    </div>

    <!-- Quota Modal -->
    @if($showQuotaModal && $selectedTenant)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Manage Quotas - {{ $selectedTenant->name }}
                    </h3>
                    
                    <div class="space-y-4">
                        @foreach($quotas as $resource => $limit)
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700 capitalize">
                                    {{ str_replace('_', ' ', $resource) }}
                                </label>
                                <input wire:model="quotas.{{ $resource }}" type="number" min="0"
                                       class="w-24 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="flex justify-end mt-6 space-x-3">
                        <button wire:click="closeModals" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button wire:click="saveQuotas" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Save Quotas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Settings Modal -->
    @if($showSettingsModal && $selectedTenant)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Manage Settings - {{ $selectedTenant->name }}
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Maintenance Mode</label>
                            <input wire:model="settings.maintenance_mode" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Backup Enabled</label>
                            <input wire:model="settings.backup_enabled" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Analytics Enabled</label>
                            <input wire:model="settings.analytics_enabled" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">API Enabled</label>
                            <input wire:model="settings.api_enabled" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Theme</label>
                            <select wire:model="settings.theme" 
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                <option value="default">Default</option>
                                <option value="dark">Dark</option>
                                <option value="blue">Blue</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Timezone</label>
                            <select wire:model="settings.timezone" 
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">Eastern</option>
                                <option value="America/Chicago">Central</option>
                                <option value="America/Denver">Mountain</option>
                                <option value="America/Los_Angeles">Pacific</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6 space-x-3">
                        <button wire:click="closeModals" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button wire:click="saveSettings" 
                                class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                            Save Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
