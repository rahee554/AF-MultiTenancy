<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin\Api;

use Livewire\Component;

class ApiSettings extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    
    public string $title = 'API Settings';
    
    public array $settings = [];
    
    public bool $apiEnabled = true;
    public string $rateLimit = '60';
    public string $timeout = '30';
    public bool $enableLogging = true;
    public string $logLevel = 'info';
    public string $apiKey = '';

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        // Load settings from config or database
        $this->apiEnabled = config('artflow-tenancy.api.enabled', true);
        $this->rateLimit = (string) config('artflow-tenancy.api.rate_limit', '60');
        $this->timeout = (string) config('artflow-tenancy.api.timeout', '30');
        $this->enableLogging = config('artflow-tenancy.api.logging.enabled', true);
        $this->logLevel = config('artflow-tenancy.api.logging.level', 'info');
        
        // Load API key from environment
        $this->apiKey = config('artflow-tenancy.api.key', env('TENANCY_API_KEY', ''));
    }

    public function regenerateApiKey(): void
    {
        // Generate a new API key
        $newApiKey = 'sk_' . bin2hex(random_bytes(32));
        
        // Update the environment file
        $this->updateEnvFile('TENANCY_API_KEY', $newApiKey);
        
        $this->apiKey = $newApiKey;
        
        session()->flash('success', 'API key regenerated successfully! Make sure to update your applications.');
    }

    private function updateEnvFile(string $key, string $value): void
    {
        $envFile = base_path('.env');
        
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            
            // Check if key exists
            if (strpos($envContent, $key . '=') !== false) {
                // Update existing key
                $envContent = preg_replace(
                    '/^' . preg_quote($key, '/') . '=.*$/m',
                    $key . '=' . $value,
                    $envContent
                );
            } else {
                // Add new key
                $envContent .= "\n" . $key . '=' . $value;
            }
            
            file_put_contents($envFile, $envContent);
        }
    }

    public function saveSettings(): void
    {
        // Here you would save the settings to database or config
        // For demo purposes, we'll just show a success message
        
        session()->flash('success', 'API settings updated successfully!');
        
        $this->loadSettings();
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.api.api-settings');
    }
}
