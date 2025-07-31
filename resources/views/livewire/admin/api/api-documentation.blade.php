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
                    <h1 class="h3 mb-0">API Documentation</h1>
                    <p class="text-muted mb-0">Complete guide to using the Tenancy REST API</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Documentation</h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($sections as $key => $label)
                        <a href="#" class="list-group-item list-group-item-action {{ $selectedSection === $key ? 'active' : '' }}"
                           wire:click="selectSection('{{ $key }}')">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ $documentation[$selectedSection]['title'] }}</h5>
                </div>
                <div class="card-body">
                    @switch($selectedSection)
                        @case('authentication')
                            <h6>Overview</h6>
                            <p>{{ $documentation['authentication']['content']['overview'] }}</p>
                            
                            <h6>Header Format</h6>
                            <p>{{ $documentation['authentication']['content']['header'] }}</p>
                            <pre class="bg-light p-3 rounded"><code>{{ $documentation['authentication']['content']['example'] }}</code></pre>
                            
                            <h6>API Scopes</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Scope</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($documentation['authentication']['content']['scopes'] as $scope => $description)
                                            <tr>
                                                <td><code>{{ $scope }}</code></td>
                                                <td>{{ $description }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('tenants')
                        @case('domains')
                            <h6>Overview</h6>
                            <p>{{ $documentation[$selectedSection]['content']['overview'] }}</p>
                            
                            <h6>Available Endpoints</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Endpoint</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($documentation[$selectedSection]['content']['endpoints'] as $endpoint => $description)
                                            <tr>
                                                <td><code>{{ $endpoint }}</code></td>
                                                <td>{{ $description }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('webhooks')
                            <h6>Overview</h6>
                            <p>{{ $documentation['webhooks']['content']['overview'] }}</p>
                            
                            <h6>Available Events</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($documentation['webhooks']['content']['events'] as $event => $description)
                                            <tr>
                                                <td><code>{{ $event }}</code></td>
                                                <td>{{ $description }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('errors')
                            <h6>Overview</h6>
                            <p>{{ $documentation['errors']['content']['overview'] }}</p>
                            
                            <h6>HTTP Status Codes</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($documentation['errors']['content']['codes'] as $code => $description)
                                            <tr>
                                                <td>
                                                    <span class="badge bg-{{ $this->getStatusColor($code) }}">{{ $code }}</span>
                                                </td>
                                                <td>{{ $description }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break
                    @endswitch
                </div>
            </div>

            <!-- Example Response -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">Example Response</h6>
                </div>
                <div class="card-body">
                    @switch($selectedSection)
                        @case('tenants')
                            <pre class="bg-light p-3 rounded"><code>{
  "data": [
    {
      "id": "9d4f8c2e-1234-4567-8901-123456789abc",
      "name": "Acme Corporation",
      "domain": "acme.example.com",
      "email": "admin@acme.example.com",
      "status": "active",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-18T14:22:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42
  }
}</code></pre>
                            @break

                        @case('errors')
                            <pre class="bg-light p-3 rounded"><code>{
  "error": {
    "type": "validation_error",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "domain": ["The domain has already been taken."]
    }
  }
}</code></pre>
                            @break

                        @default
                            <p class="text-muted">Select a section to see example responses.</p>
                    @endswitch
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
