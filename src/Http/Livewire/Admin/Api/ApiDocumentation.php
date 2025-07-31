<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin\Api;

use Livewire\Component;

class ApiDocumentation extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    
    public string $title = 'API Documentation';
    
    public string $selectedSection = 'authentication';
    
    public array $sections = [
        'authentication' => 'Authentication',
        'tenants' => 'Tenants',
        'domains' => 'Domains',
        'webhooks' => 'Webhooks',
        'errors' => 'Error Handling'
    ];

    public function selectSection(string $section): void
    {
        $this->selectedSection = $section;
    }

    public function getStatusColor(string $code): string
    {
        $statusCode = intval($code);
        
        if ($statusCode >= 200 && $statusCode < 300) {
            return 'success';
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            return 'info';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return 'warning';
        } else {
            return 'danger';
        }
    }

    public function getDocumentationContent(): array
    {
        return [
            'authentication' => [
                'title' => 'Authentication',
                'content' => [
                    'overview' => 'All API requests must be authenticated using an API key.',
                    'header' => 'Include your API key in the Authorization header:',
                    'example' => 'Authorization: Bearer YOUR_API_KEY',
                    'scopes' => [
                        'read' => 'Read access to resources',
                        'write' => 'Create and update resources',
                        'delete' => 'Delete resources'
                    ]
                ]
            ],
            'tenants' => [
                'title' => 'Tenants API',
                'content' => [
                    'overview' => 'Manage tenants through the REST API.',
                    'endpoints' => [
                        'GET /api/tenants' => 'List all tenants',
                        'POST /api/tenants' => 'Create a new tenant',
                        'GET /api/tenants/{id}' => 'Get tenant details',
                        'PUT /api/tenants/{id}' => 'Update tenant',
                        'DELETE /api/tenants/{id}' => 'Delete tenant'
                    ]
                ]
            ],
            'domains' => [
                'title' => 'Domains API',
                'content' => [
                    'overview' => 'Manage tenant domains through the REST API.',
                    'endpoints' => [
                        'GET /api/domains' => 'List all domains',
                        'POST /api/domains' => 'Create a new domain',
                        'GET /api/domains/{id}' => 'Get domain details',
                        'DELETE /api/domains/{id}' => 'Delete domain'
                    ]
                ]
            ],
            'webhooks' => [
                'title' => 'Webhooks',
                'content' => [
                    'overview' => 'Configure webhooks to receive real-time notifications.',
                    'events' => [
                        'tenant.created' => 'When a new tenant is created',
                        'tenant.updated' => 'When a tenant is updated',
                        'tenant.deleted' => 'When a tenant is deleted',
                        'domain.created' => 'When a new domain is added',
                        'domain.deleted' => 'When a domain is removed'
                    ]
                ]
            ],
            'errors' => [
                'title' => 'Error Handling',
                'content' => [
                    'overview' => 'The API uses conventional HTTP response codes.',
                    'codes' => [
                        '200' => 'OK - Everything worked as expected',
                        '400' => 'Bad Request - Invalid request parameters',
                        '401' => 'Unauthorized - Invalid API key',
                        '403' => 'Forbidden - Insufficient permissions',
                        '404' => 'Not Found - Resource does not exist',
                        '422' => 'Unprocessable Entity - Validation errors',
                        '500' => 'Internal Server Error - Something went wrong'
                    ]
                ]
            ]
        ];
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.api.api-documentation', [
            'documentation' => $this->getDocumentationContent()
        ]);
    }
}
