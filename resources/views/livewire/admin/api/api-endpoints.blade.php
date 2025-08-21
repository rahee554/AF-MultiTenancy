@extends('artflow-tenancy::layout.app')

@push('page-title')
    {{ $title }}
@endpush

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h1 class="h3 mb-0">API Endpoints</h1>
                    <p class="text-muted mb-0">Available REST API endpoints and their documentation</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Endpoints -->
    @foreach($endpoints as $group)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ $group['group'] }} API</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Method</th>
                                        <th>Endpoint</th>
                                        <th>Description</th>
                                        <th>Parameters</th>
                                        <th>Response</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['routes'] as $route)
                                        <tr>
                                            <td>
                                                <span class="badge bg-{{ $this->getMethodColor($route['method']) }}">
                                                    {{ $route['method'] }}
                                                </span>
                                            </td>
                                            <td>
                                                <code class="bg-light px-2 py-1 rounded">{{ $route['path'] }}</code>
                                            </td>
                                            <td>{{ $route['description'] }}</td>
                                            <td>
                                                @if(empty($route['parameters']))
                                                    <span class="text-muted">None</span>
                                                @else
                                                    @foreach($route['parameters'] as $param)
                                                        <code class="bg-light px-1 rounded me-1">{{ $param }}</code>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td>{{ $route['response'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Base URL Information -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">API Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Base URL</h6>
                            <p><code class="bg-light px-2 py-1 rounded">{{ url('/api') }}</code></p>
                            
                            <h6>Authentication</h6>
                            <p>Include your API key in the Authorization header:</p>
                            <pre class="bg-light p-3 rounded"><code>Authorization: Bearer YOUR_API_KEY</code></pre>
                        </div>
                        <div class="col-md-6">
                            <h6>Content Type</h6>
                            <p>All requests should include:</p>
                            <pre class="bg-light p-3 rounded"><code>Content-Type: application/json</code></pre>
                            
                            <h6>Rate Limiting</h6>
                            <p>API requests are limited to <strong>60 requests per minute</strong> per API key.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Add method to get color for HTTP methods
    window.getMethodColor = function(method) {
        const colors = {
            'GET': 'success',
            'POST': 'primary',
            'PUT': 'warning',
            'DELETE': 'danger',
            'PATCH': 'info'
        };
        return colors[method] || 'secondary';
    };
</script>
@endpush
@endsection
