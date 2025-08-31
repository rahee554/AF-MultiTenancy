@extends('artflow-tenancy::layout.app')

@section('title', 'All Tenants')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">All Tenants</h1>
                <p class="mt-2 text-gray-600">Manage your tenants and their configurations</p>
            </div>
            <a href="{{ route('tenancy.tenants.create') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition duration-200 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Create New Tenant
            </a>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Tenants</label>
                    <input type="text" 
                           id="search" 
                           placeholder="Search by name or domain..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                    <select id="status-filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div>
                    <label for="sort-by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select id="sort-by" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="created_at_desc">Newest First</option>
                        <option value="created_at_asc">Oldest First</option>
                        <option value="name_asc">Name A-Z</option>
                        <option value="name_desc">Name Z-A</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            @if($tenants->count() > 0)
                <!-- Table Header -->
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 font-medium text-gray-900">
                        <div>Tenant Info</div>
                        <div>Domains</div>
                        <div>Status</div>
                        <div>Created</div>
                        <div>Actions</div>
                    </div>
                </div>

                <!-- Table Body -->
                <div class="divide-y divide-gray-200">
                    @foreach($tenants as $tenant)
                        <div class="px-6 py-4 hover:bg-gray-50 transition duration-150">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                                <!-- Tenant Info -->
                                <div>
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                            <span class="text-blue-600 font-medium text-sm">
                                                {{ substr($tenant->name ?? $tenant->id, 0, 2) }}
                                            </span>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-900">
                                                {{ $tenant->name ?? 'Unnamed Tenant' }}
                                            </h3>
                                            <p class="text-xs text-gray-500">ID: {{ Str::limit($tenant->id, 20) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Domains -->
                                <div>
                                    @if($tenant->domains && $tenant->domains->count() > 0)
                                        <div class="space-y-1">
                                            @foreach($tenant->domains->take(2) as $domain)
                                                <span class="inline-block bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">
                                                    {{ $domain->domain }}
                                                </span>
                                            @endforeach
                                            @if($tenant->domains->count() > 2)
                                                <span class="text-xs text-gray-500">
                                                    +{{ $tenant->domains->count() - 2 }} more
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-500">No domains</span>
                                    @endif
                                </div>

                                <!-- Status -->
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ ($tenant->status ?? 'active') === 'active' ? 'bg-green-100 text-green-800' : 
                                           (($tenant->status ?? 'active') === 'inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ ucfirst($tenant->status ?? 'active') }}
                                    </span>
                                </div>

                                <!-- Created -->
                                <div>
                                    <p class="text-sm text-gray-900">{{ $tenant->created_at->format('M d, Y') }}</p>
                                    <p class="text-xs text-gray-500">{{ $tenant->created_at->diffForHumans() }}</p>
                                </div>

                                <!-- Actions -->
                                <div>
                                    <div class="flex space-x-2">
                                        <a href="{{ route('tenancy.tenants.show', $tenant) }}" 
                                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            View
                                        </a>
                                        <a href="{{ route('tenancy.tenants.edit', $tenant) }}" 
                                           class="text-green-600 hover:text-green-800 text-sm font-medium">
                                            Edit
                                        </a>
                                        <button class="text-red-600 hover:text-red-800 text-sm font-medium"
                                                onclick="confirmDelete('{{ $tenant->id }}', '{{ $tenant->name ?? 'Unnamed' }}')">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($tenants->hasPages())
                    <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
                        {{ $tenants->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No tenants found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first tenant.</p>
                    <div class="mt-6">
                        <a href="{{ route('tenancy.tenants.create') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create New Tenant
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 m-4 max-w-md mx-auto">
        <div class="flex items-center mb-4">
            <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.94-.833-2.73 0L3.084 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900">Confirm Deletion</h3>
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Are you sure you want to delete the tenant "<span id="deleteTenantName" class="font-medium"></span>"? 
            This action cannot be undone and will permanently remove all tenant data.
        </p>
        <div class="flex justify-end space-x-3">
            <button onclick="closeDeleteModal()" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                Cancel
            </button>
            <form id="deleteForm" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                    Delete Tenant
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(tenantId, tenantName) {
    document.getElementById('deleteTenantName').textContent = tenantName;
    document.getElementById('deleteForm').action = `/tenancy/tenants/${tenantId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>
@endsection
