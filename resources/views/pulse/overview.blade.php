<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Pulse Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center">
                        <h1 class="text-3xl font-bold text-gray-900">
                            📊 Tenant Pulse Dashboard
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <select id="tenantSelect" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Tenants</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}" {{ request('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                    {{ $tenant->name }}
                                </option>
                            @endforeach
                        </select>
                        <select id="hoursSelect" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1" {{ $hours == 1 ? 'selected' : '' }}>Last Hour</option>
                            <option value="6" {{ $hours == 6 ? 'selected' : '' }}>Last 6 Hours</option>
                            <option value="24" {{ $hours == 24 ? 'selected' : '' }}>Last 24 Hours</option>
                            <option value="72" {{ $hours == 72 ? 'selected' : '' }}>Last 3 Days</option>
                            <option value="168" {{ $hours == 168 ? 'selected' : '' }}>Last Week</option>
                        </select>
                        <button onclick="refreshDashboard()" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                            🔄 Refresh
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="text-2xl">🏢</div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Total Tenants
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $tenants->count() }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="text-2xl">📈</div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Active Metrics
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900" id="activeMetrics">
                                        {{ array_sum(array_map(fn($m) => count($m['metrics']), $allMetrics)) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="text-2xl">⏱️</div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Time Range
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $hours }}h
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="text-2xl">🔄</div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Last Updated
                                    </dt>
                                    <dd class="text-sm font-medium text-gray-900" id="lastUpdated">
                                        {{ now()->format('H:i:s') }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Metrics Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($allMetrics as $tenantId => $data)
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                🏢 {{ $data['tenant']->name }}
                                <span class="text-sm text-gray-500">({{ Str::limit($tenantId, 12) }}...)</span>
                            </h3>
                            
                            @if(empty($data['metrics']))
                                <p class="text-gray-500 text-center py-8">No metrics available for this period</p>
                            @else
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($data['metrics'] as $type => $metrics)
                                        <div class="border rounded p-3">
                                            <div class="text-sm font-medium text-gray-700 mb-1">
                                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                                            </div>
                                            <div class="text-2xl font-bold text-indigo-600">
                                                {{ number_format($metrics->count ?? 0) }}
                                            </div>
                                            @if(isset($metrics->avg_value))
                                                <div class="text-xs text-gray-500">
                                                    Avg: {{ round($metrics->avg_value, 2) }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if(empty($allMetrics))
                <div class="bg-white shadow rounded-lg p-8 text-center">
                    <div class="text-6xl mb-4">📊</div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Metrics Available</h3>
                    <p class="text-gray-500 mb-6">No tenant metrics found for the selected time period.</p>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p>💡 To start collecting metrics:</p>
                        <p>1. Ensure Laravel Pulse is properly configured</p>
                        <p>2. Visit tenant domains to generate traffic</p>
                        <p>3. Run: <code class="bg-gray-100 px-2 py-1 rounded">php artisan tenancy:test-all</code></p>
                    </div>
                </div>
            @endif
        </main>
    </div>

    <script>
        function refreshDashboard() {
            const tenantId = document.getElementById('tenantSelect').value;
            const hours = document.getElementById('hoursSelect').value;
            
            const url = new URL(window.location);
            if (tenantId) {
                url.searchParams.set('tenant_id', tenantId);
            } else {
                url.searchParams.delete('tenant_id');
            }
            url.searchParams.set('hours', hours);
            
            window.location = url;
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
        }, 30000);

        // Event listeners
        document.getElementById('tenantSelect').addEventListener('change', refreshDashboard);
        document.getElementById('hoursSelect').addEventListener('change', refreshDashboard);
    </script>
</body>
</html>
