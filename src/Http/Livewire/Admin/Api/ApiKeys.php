<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin\Api;

use Livewire\Component;

class ApiKeys extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    public string $title = 'API Keys Management';
    
    public array $apiKeys = [];
    public string $newKeyName = '';
    public array $newKeyPermissions = [];
    
    public function mount(): void
    {
        $this->loadApiKeys();
    }

    public function loadApiKeys(): void
    {
        // Demo data - replace with actual API key management
        $this->apiKeys = [
            [
                'id' => 1,
                'name' => 'Production API Key',
                'key' => 'tk_' . str_repeat('*', 40),
                'permissions' => ['read', 'write'],
                'created_at' => '2024-01-15',
                'last_used' => '2024-01-18',
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'Development API Key',
                'key' => 'tk_' . str_repeat('*', 40),
                'permissions' => ['read'],
                'created_at' => '2024-01-10',
                'last_used' => '2024-01-17',
                'status' => 'active'
            ]
        ];
    }

    public function generateApiKey(): void
    {
        if (empty($this->newKeyName)) {
            session()->flash('error', 'Please provide a name for the API key.');
            return;
        }

        // Generate new API key (demo)
        $newKey = [
            'id' => count($this->apiKeys) + 1,
            'name' => $this->newKeyName,
            'key' => 'tk_' . bin2hex(random_bytes(20)),
            'permissions' => $this->newKeyPermissions,
            'created_at' => now()->format('Y-m-d'),
            'last_used' => 'Never',
            'status' => 'active'
        ];

        $this->apiKeys[] = $newKey;
        
        session()->flash('success', 'API key generated successfully!');
        
        $this->reset(['newKeyName', 'newKeyPermissions']);
    }

    public function revokeKey(int $keyId): void
    {
        foreach ($this->apiKeys as &$key) {
            if ($key['id'] === $keyId) {
                $key['status'] = 'revoked';
                break;
            }
        }
        
        session()->flash('success', 'API key revoked successfully!');
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.api.api-keys');
    }
}
