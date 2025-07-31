<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin\Api;

use Livewire\Component;

class ApiEndpoints extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    
    public string $title = 'API Endpoints';
    
    public array $endpoints = [];

    public function mount(): void
    {
        $this->loadEndpoints();
    }

    public function loadEndpoints(): void
    {
        // Define all available API endpoints
        $this->endpoints = [
            [
                'group' => 'Tenants',
                'routes' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/tenants',
                        'description' => 'List all tenants',
                        'parameters' => ['page', 'per_page', 'search'],
                        'response' => 'Paginated list of tenants'
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/tenants',
                        'description' => 'Create a new tenant',
                        'parameters' => ['name', 'domain', 'email'],
                        'response' => 'Created tenant object'
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/tenants/{id}',
                        'description' => 'Get tenant details',
                        'parameters' => ['id'],
                        'response' => 'Tenant object'
                    ],
                    [
                        'method' => 'PUT',
                        'path' => '/api/tenants/{id}',
                        'description' => 'Update tenant',
                        'parameters' => ['id', 'name', 'domain', 'email'],
                        'response' => 'Updated tenant object'
                    ],
                    [
                        'method' => 'DELETE',
                        'path' => '/api/tenants/{id}',
                        'description' => 'Delete tenant',
                        'parameters' => ['id'],
                        'response' => 'Success message'
                    ]
                ]
            ],
            [
                'group' => 'Domains',
                'routes' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/domains',
                        'description' => 'List all domains',
                        'parameters' => ['tenant_id', 'page', 'per_page'],
                        'response' => 'Paginated list of domains'
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/domains',
                        'description' => 'Create a new domain',
                        'parameters' => ['domain', 'tenant_id'],
                        'response' => 'Created domain object'
                    ]
                ]
            ],
            [
                'group' => 'System',
                'routes' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/health',
                        'description' => 'Health check endpoint',
                        'parameters' => [],
                        'response' => 'System status'
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/stats',
                        'description' => 'Get system statistics',
                        'parameters' => [],
                        'response' => 'Statistics object'
                    ]
                ]
            ]
        ];
    }

    public function getMethodColor(string $method): string
    {
        $colors = [
            'GET' => 'success',
            'POST' => 'primary',
            'PUT' => 'warning',
            'DELETE' => 'danger',
            'PATCH' => 'info'
        ];
        
        return $colors[$method] ?? 'secondary';
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.api.api-endpoints');
    }
}
