<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Exception;

class TenantPWAService
{
    /**
     * Enable PWA for a tenant
     */
    public function enablePWA(Tenant $tenant, array $config = []): bool
    {
        try {
            // Merge with default config
            $pwaConfig = array_merge($this->getDefaultPWAConfig($tenant), $config);
            
            // Create PWA directory structure
            $this->createPWAStructure($tenant);
            
            // Generate manifest.json
            $this->generateManifest($tenant, $pwaConfig);
            
            // Generate service worker
            $this->generateServiceWorker($tenant, $pwaConfig);
            
            // Update tenant record using model method
            $tenant->enablePWA($pwaConfig);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to enable PWA: {$e->getMessage()}");
        }
    }
    
    /**
     * Disable PWA for a tenant
     */
    public function disablePWA(Tenant $tenant, bool $removeFiles = false): bool
    {
        try {
            // Update tenant record using model method
            $tenant->disablePWA();
            
            // Optionally remove PWA files
            if ($removeFiles) {
                $this->removePWAFiles($tenant);
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to disable PWA: {$e->getMessage()}");
        }
    }
    
    /**
     * Get PWA status for a tenant
     */
    public function getPWAStatus(Tenant $tenant): array
    {
        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        $domainFolder = $this->getDomainFolderName($domain);
        $storagePath = $this->getPWAStoragePath($domainFolder);
        $publicPath = $this->getPWAPublicPath($domainFolder);
        
        return [
            'enabled' => $tenant->pwa_enabled,
            'config' => $tenant->pwa_config ?? [],
            'files' => [
                'manifest' => file_exists("{$storagePath}/manifest.json"),
                'service_worker' => file_exists("{$storagePath}/sw.js"),
                'offline_page' => file_exists("{$storagePath}/offline.html")
            ],
            'storage_path' => $storagePath,
            'public_path' => $publicPath,
            'domain' => $domain
        ];
    }
    
    /**
     * Get PWA storage path for a domain
     */
    protected function getPWAStoragePath(string $domainFolder): string
    {
        return storage_path("app/public/tenants/{$domainFolder}/pwa");
    }
    
    /**
     * Get PWA public URL path for a domain
     */
    protected function getPWAPublicPath(string $domainFolder): string
    {
        return "/storage/tenants/{$domainFolder}/pwa";
    }
    
    /**
     * Test PWA functionality for a tenant
     */
    public function testPWA(Tenant $tenant): array
    {
        $results = [
            'overall' => true,
            'tests' => []
        ];
        
        // Test 1: PWA Enabled in Database
        $results['tests']['pwa_enabled'] = [
            'name' => 'PWA Enabled',
            'passed' => $tenant->pwa_enabled,
            'message' => $tenant->pwa_enabled ? 'PWA is enabled' : 'PWA is disabled'
        ];
        
        if (!$tenant->pwa_enabled) {
            $results['overall'] = false;
            return $results;
        }
        
        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        $domainFolder = $this->getDomainFolderName($domain);
        $storagePath = $this->getPWAStoragePath($domainFolder);
        $publicPath = $this->getPWAPublicPath($domainFolder);
        
        // Test 2: Storage Directory Exists
        $storageExists = file_exists($storagePath);
        $results['tests']['storage_directory'] = [
            'name' => 'Storage Directory',
            'passed' => $storageExists,
            'message' => $storageExists ? "Directory exists: {$storagePath}" : "Directory missing: {$storagePath}"
        ];
        $results['overall'] = $results['overall'] && $storageExists;
        
        // Test 3: Manifest exists
        $manifestExists = file_exists("{$storagePath}/manifest.json");
        $results['tests']['manifest_exists'] = [
            'name' => 'Manifest File',
            'passed' => $manifestExists,
            'message' => $manifestExists ? 'manifest.json found' : 'manifest.json missing'
        ];
        $results['overall'] = $results['overall'] && $manifestExists;
        
        // Test 4: Manifest is valid JSON
        if ($manifestExists) {
            $manifestContent = file_get_contents("{$storagePath}/manifest.json");
            $manifestJson = json_decode($manifestContent, true);
            $manifestValid = $manifestJson !== null;
            
            $results['tests']['manifest_valid'] = [
                'name' => 'Manifest Valid JSON',
                'passed' => $manifestValid,
                'message' => $manifestValid ? 'Valid JSON' : 'Invalid JSON'
            ];
            $results['overall'] = $results['overall'] && $manifestValid;
            
            // Test 5: Manifest has required fields
            $requiredFields = ['name', 'start_url', 'display', 'icons'];
            $hasRequiredFields = true;
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($manifestJson[$field])) {
                    $hasRequiredFields = false;
                    $missingFields[] = $field;
                }
            }
            $results['tests']['manifest_required_fields'] = [
                'name' => 'Manifest Required Fields',
                'passed' => $hasRequiredFields,
                'message' => $hasRequiredFields ? 'All required fields present' : 'Missing: ' . implode(', ', $missingFields)
            ];
            $results['overall'] = $results['overall'] && $hasRequiredFields;
        }
        
        // Test 6: Service Worker exists
        $swExists = file_exists("{$storagePath}/sw.js");
        $results['tests']['service_worker_exists'] = [
            'name' => 'Service Worker File',
            'passed' => $swExists,
            'message' => $swExists ? 'sw.js found' : 'sw.js missing'
        ];
        $results['overall'] = $results['overall'] && $swExists;
        
        // Test 7: Service Worker syntax check
        if ($swExists) {
            $swContent = file_get_contents("{$storagePath}/sw.js");
            $hasCacheName = str_contains($swContent, 'CACHE_NAME');
            $hasInstallEvent = str_contains($swContent, 'install');
            $hasFetchEvent = str_contains($swContent, 'fetch');
            
            $swValid = $hasCacheName && $hasInstallEvent && $hasFetchEvent;
            $results['tests']['service_worker_valid'] = [
                'name' => 'Service Worker Valid',
                'passed' => $swValid,
                'message' => $swValid ? 'Has required events' : 'Missing required events'
            ];
            $results['overall'] = $results['overall'] && $swValid;
        }
        
        // Test 8: Offline page exists
        $offlineExists = file_exists("{$storagePath}/offline.html");
        $results['tests']['offline_page_exists'] = [
            'name' => 'Offline Page',
            'passed' => $offlineExists,
            'message' => $offlineExists ? 'offline.html found' : 'offline.html missing'
        ];
        $results['overall'] = $results['overall'] && $offlineExists;
        
        // Test 9: Storage Link Exists
        $storageLinkExists = file_exists(public_path('storage'));
        $results['tests']['storage_link'] = [
            'name' => 'Storage Link',
            'passed' => $storageLinkExists,
            'message' => $storageLinkExists ? 'storage link exists' : 'Run: php artisan storage:link'
        ];
        
        // Test 10: PWA Config in Database
        $hasConfig = !empty($tenant->pwa_config);
        $results['tests']['pwa_config'] = [
            'name' => 'PWA Configuration',
            'passed' => $hasConfig,
            'message' => $hasConfig ? 'Configuration stored' : 'No configuration'
        ];
        
        return $results;
    }
    
    /**
     * Regenerate PWA files for a tenant
     */
    public function regeneratePWA(Tenant $tenant): bool
    {
        if (!$tenant->pwa_enabled) {
            throw new Exception('PWA is not enabled for this tenant');
        }
        
        $config = $tenant->pwa_config ?? [];
        
        // Regenerate manifest
        $this->generateManifest($tenant, $config);
        
        // Regenerate service worker
        $this->generateServiceWorker($tenant, $config);
        
        return true;
    }
    
    /**
     * Create PWA directory structure
     */
    private function createPWAStructure(Tenant $tenant): void
    {
        $domain = $tenant->domains()->first()->domain ?? 'tenant';
        $domainFolder = $this->getDomainFolderName($domain);
        $storagePath = $this->getPWAStoragePath($domainFolder);
        
        // Ensure storage link exists
        $this->ensureStorageLink();
        
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        // Create icons directory
        $iconsPath = "{$storagePath}/icons";
        if (!file_exists($iconsPath)) {
            mkdir($iconsPath, 0755, true);
        }
    }
    
    /**
     * Ensure storage link exists
     */
    private function ensureStorageLink(): void
    {
        $publicStoragePath = public_path('storage');
        $storagePath = storage_path('app/public');
        
        // Check if link already exists
        if (file_exists($publicStoragePath)) {
            return;
        }
        
        // Create symbolic link
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Create junction
                exec("mklink /J \"" . $publicStoragePath . "\" \"" . $storagePath . "\"");
            } else {
                // Unix: Create symlink
                symlink($storagePath, $publicStoragePath);
            }
        } catch (Exception $e) {
            // If symlink fails, user needs to run storage:link manually
            // Log but don't fail
        }
    }
    
    /**
     * Generate manifest.json
     */
    private function generateManifest(Tenant $tenant, array $config): void
    {
        $domain = $tenant->domains()->first()->domain ?? 'tenant';
        $domainFolder = $this->getDomainFolderName($domain);
        $storagePath = $this->getPWAStoragePath($domainFolder);
        
        $manifest = [
            'name' => $config['name'] ?? $tenant->name,
            'short_name' => $config['short_name'] ?? substr($tenant->name, 0, 12),
            'description' => $config['description'] ?? "Progressive Web App for {$tenant->name}",
            'start_url' => $config['start_url'] ?? '/',
            'scope' => $config['scope'] ?? '/',
            'display' => $config['display'] ?? 'standalone',
            'theme_color' => $config['theme_color'] ?? '#667eea',
            'background_color' => $config['background_color'] ?? '#ffffff',
            'orientation' => $config['orientation'] ?? 'any',
            'icons' => $config['icons'] ?? $this->getDefaultIcons(),
            'categories' => $config['categories'] ?? ['business', 'productivity'],
            'prefer_related_applications' => false
        ];
        
        File::put("{$storagePath}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * Generate service worker
     */
    private function generateServiceWorker(Tenant $tenant, array $config): void
    {
        $domain = $tenant->domains()->first()->domain ?? 'tenant';
        $domainFolder = $this->getDomainFolderName($domain);
        $storagePath = $this->getPWAStoragePath($domainFolder);
        
        $cacheName = $config['cache_name'] ?? "tenant-{$tenant->id}-v1";
        $cacheStrategy = $config['cache_strategy'] ?? 'network-first';
        $cacheUrls = $config['cache_urls'] ?? ['/'];
        
        $sw = $this->getServiceWorkerTemplate($cacheName, $cacheStrategy, $cacheUrls);
        
        File::put("{$storagePath}/sw.js", $sw);
        
        // Generate offline page
        $this->generateOfflinePage($tenant, $storagePath);
    }
    
    /**
     * Generate offline page
     */
    private function generateOfflinePage(Tenant $tenant, string $storagePath): void
    {
        $offlineHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - {$tenant->name}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        button {
            padding: 12px 24px;
            font-size: 1rem;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        button:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📡 You're Offline</h1>
        <p>It looks like you've lost your internet connection. Don't worry, we'll reconnect automatically when you're back online.</p>
        <button onclick="window.location.reload()">🔄 Try Again</button>
    </div>
    <script>
        // Auto-reload when back online
        window.addEventListener('online', function() {
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        });
    </script>
</body>
</html>
HTML;
        
        File::put("{$storagePath}/offline.html", $offlineHtml);
    }
    
    /**
     * Get service worker template
     */
    private function getServiceWorkerTemplate(string $cacheName, string $strategy, array $cacheUrls): string
    {
        $cacheUrlsJson = json_encode($cacheUrls, JSON_UNESCAPED_SLASHES);
        
        return <<<JS
// Service Worker for Multi-Tenant PWA
// Cache Name: {$cacheName}
// Strategy: {$strategy}

const CACHE_NAME = '{$cacheName}';
const OFFLINE_URL = '/offline.html';
const urlsToCache = {$cacheUrlsJson};

// Install Event - Cache essential resources
self.addEventListener('install', event => {
    console.log('[SW] Installing service worker...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Caching essential resources');
                return cache.addAll(urlsToCache);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate Event - Clean up old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating service worker...');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event - Handle requests with {$strategy} strategy
self.addEventListener('fetch', event => {
    const { request } = event;
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests
    if (!request.url.startsWith(self.location.origin)) {
        return;
    }
    
    event.respondWith(
        handleFetch(request)
    );
});

// Handle fetch with strategy
async function handleFetch(request) {
    try {
        if ('{$strategy}' === 'network-first') {
            return await networkFirst(request);
        } else if ('{$strategy}' === 'cache-first') {
            return await cacheFirst(request);
        } else if ('{$strategy}' === 'stale-while-revalidate') {
            return await staleWhileRevalidate(request);
        } else {
            return await networkFirst(request);
        }
    } catch (error) {
        console.error('[SW] Fetch failed:', error);
        return caches.match(OFFLINE_URL);
    }
}

// Network First Strategy
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        
        // Clone response and cache it
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
        }
        
        throw error;
    }
}

// Cache First Strategy
async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        if (request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
        }
        throw error;
    }
}

// Stale While Revalidate Strategy
async function staleWhileRevalidate(request) {
    const cachedResponse = await caches.match(request);
    
    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            const cache = caches.open(CACHE_NAME);
            cache.then(c => c.put(request, networkResponse.clone()));
        }
        return networkResponse;
    }).catch(() => cachedResponse || caches.match(OFFLINE_URL));
    
    return cachedResponse || fetchPromise;
}

// Handle CSRF Token Refresh
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'REFRESH_CSRF') {
        console.log('[SW] Refreshing CSRF token');
        // Clear cache for pages with CSRF tokens
        caches.open(CACHE_NAME).then(cache => {
            cache.keys().then(keys => {
                keys.forEach(key => {
                    if (key.url.includes('/api/') || key.url.includes('/livewire/')) {
                        cache.delete(key);
                    }
                });
            });
        });
    }
});

// Handle Session Renewal
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SESSION_RENEWED') {
        console.log('[SW] Session renewed, clearing cached authenticated pages');
        caches.open(CACHE_NAME).then(cache => {
            cache.keys().then(keys => {
                keys.forEach(key => {
                    // Clear dashboard, profile, and other authenticated pages
                    if (key.url.includes('/dashboard') || 
                        key.url.includes('/profile') ||
                        key.url.includes('/account')) {
                        cache.delete(key);
                    }
                });
            });
        });
    }
});

// Handle 403 Errors (Forbidden)
async function handle403(response) {
    if (response.status === 403) {
        console.warn('[SW] 403 Forbidden - clearing cache and notifying client');
        
        // Clear all caches
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames.map(name => caches.delete(name)));
        
        // Notify all clients
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'AUTH_ERROR',
                status: 403,
                message: 'Access forbidden. Please refresh and log in again.'
            });
        });
    }
    
    return response;
}

// Handle 419 Errors (CSRF Token Mismatch)
async function handle419(response) {
    if (response.status === 419) {
        console.warn('[SW] 419 CSRF Token Mismatch - requesting new token');
        
        // Notify client to refresh CSRF token
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'CSRF_ERROR',
                status: 419,
                message: 'Session expired. Refreshing...'
            });
        });
    }
    
    return response;
}

console.log('[SW] Service Worker loaded successfully');
JS;
    }
    
    /**
     * Remove PWA files
     */
    private function removePWAFiles(Tenant $tenant): void
    {
        $domain = $tenant->domains()->first()->domain ?? 'tenant';
        $domainFolder = $this->getDomainFolderName($domain);
        $storagePath = $this->getPWAStoragePath($domainFolder);
        
        if (file_exists($storagePath)) {
            File::deleteDirectory($storagePath);
        }
    }
    
    /**
     * Get default PWA config
     */
    private function getDefaultPWAConfig(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'short_name' => substr($tenant->name, 0, 12),
            'description' => "Progressive Web App for {$tenant->name}",
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => '#667eea',
            'background_color' => '#ffffff',
            'orientation' => 'any',
            'cache_strategy' => 'network-first',
            'cache_urls' => ['/'],
            'cache_name' => "tenant-{$tenant->id}-v1"
        ];
    }
    
    /**
     * Get default icons array
     */
    private function getDefaultIcons(): array
    {
        return [
            [
                'src' => '/pwa/icons/icon-72x72.png',
                'sizes' => '72x72',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-96x96.png',
                'sizes' => '96x96',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-128x128.png',
                'sizes' => '128x128',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-144x144.png',
                'sizes' => '144x144',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-152x152.png',
                'sizes' => '152x152',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-384x384.png',
                'sizes' => '384x384',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/pwa/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ]
        ];
    }
    
    /**
     * Convert domain to folder name
     */
    private function getDomainFolderName(string $domain): string
    {
        // Use exact domain name as folder name
        return strtolower($domain);
    }
}
