{{--
    ArtFlow Tenancy PWA Component
    
    Usage: @include('af-tenancy::components.pwa')
    
    This component automatically enables PWA functionality for tenants when:
    1. Tenant context is initialized
    2. PWA is enabled for the current tenant
    3. PWA files exist in storage
    
    No configuration needed - just include this in your layout <head> section!
--}}

@php
    // Get current tenant - works with both direct tenant() and passed $tenant variable
    $currentTenant = $tenant ?? (function_exists('tenant') ? tenant() : null);
    
    // Refresh tenant model to get latest PWA status from database
    if ($currentTenant) {
        $currentTenant->refresh();
    }
    
    // Only render PWA tags if tenant exists and has PWA enabled
    if (!$currentTenant || !$currentTenant->hasPWA()) {
        return;
    }
    
    // Get tenant domain and PWA configuration
    $tenantDomain = strtolower($currentTenant->domains()->first()->domain ?? 'unknown');
    $pwaConfig = $currentTenant->pwa_config ?? [];
    
    // PWA paths (using storage link)
    $manifestPath = "/storage/pwa/{$tenantDomain}/manifest.json";
    $serviceWorkerPath = "/storage/pwa/{$tenantDomain}/sw.js";
    
    // Check if manifest exists
    $manifestExists = file_exists(storage_path("app/public/pwa/{$tenantDomain}/manifest.json"));
    
    if (!$manifestExists) {
        return; // Don't render if PWA files don't exist
    }
@endphp

{{-- PWA Manifest Link --}}
<link rel="manifest" href="{{ $manifestPath }}">

{{-- Theme Colors --}}
<meta name="theme-color" content="{{ $pwaConfig['theme_color'] ?? '#667eea' }}">
<meta name="background-color" content="{{ $pwaConfig['background_color'] ?? '#ffffff' }}">

{{-- Mobile Web App Capable --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $currentTenant->name ?? config('app.name') }}">

{{-- MS Tile --}}
<meta name="msapplication-TileColor" content="{{ $pwaConfig['theme_color'] ?? '#667eea' }}">
<meta name="msapplication-tap-highlight" content="no">

{{-- Viewport (if not already set) --}}
@if(!isset($viewportSet))
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
@endif

{{-- Service Worker Registration Script --}}
<script>
    // ArtFlow Tenancy PWA - Service Worker Registration
    (function() {
        'use strict';
        
        // Configuration
        const config = {
            serviceWorkerUrl: '{{ $serviceWorkerPath }}',
            tenantDomain: '{{ $tenantDomain }}',
            tenantId: '{{ $currentTenant->id }}',
            debug: {{ config('app.debug') ? 'true' : 'false' }}
        };
        
        // Logger utility
        const log = {
            info: (...args) => config.debug && console.log('🔷 [PWA]', ...args),
            success: (...args) => config.debug && console.log('✅ [PWA]', ...args),
            error: (...args) => console.error('❌ [PWA]', ...args),
            warn: (...args) => console.warn('⚠️ [PWA]', ...args)
        };
        
        // Check browser support
        if (!('serviceWorker' in navigator)) {
            log.warn('Service Workers not supported in this browser');
            return;
        }
        
        // Register service worker when page loads
        window.addEventListener('load', function() {
            registerServiceWorker();
            setupPWAEvents();
        });
        
        /**
         * Register the service worker
         */
        function registerServiceWorker() {
            navigator.serviceWorker.register(config.serviceWorkerUrl, {
                scope: '/',
                updateViaCache: 'none' // Always check for updates
            })
            .then(function(registration) {
                log.success('Service Worker registered for tenant:', config.tenantDomain);
                log.info('Registration scope:', registration.scope);
                
                // Check for updates
                registration.addEventListener('updatefound', function() {
                    const newWorker = registration.installing;
                    log.info('New Service Worker found, updating...');
                    
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            log.success('New Service Worker installed! Refresh to activate.');
                            showUpdateNotification();
                        }
                    });
                });
                
                // Periodic update checks (every 1 hour)
                setInterval(function() {
                    registration.update().catch(function(error) {
                        log.warn('Update check failed:', error);
                    });
                }, 60 * 60 * 1000);
            })
            .catch(function(error) {
                log.error('Service Worker registration failed:', error);
                
                // Detailed error handling
                if (error.name === 'SecurityError') {
                    log.error('Security Error: PWA requires HTTPS or localhost');
                } else if (error.name === 'TypeError') {
                    log.error('Service Worker file not found or invalid');
                }
            });
        }
        
        /**
         * Setup PWA-related event listeners
         */
        function setupPWAEvents() {
            // Listen for Service Worker messages
            navigator.serviceWorker.addEventListener('message', function(event) {
                log.info('Message from Service Worker:', event.data);
                
                if (event.data.type === 'CACHE_UPDATED') {
                    log.success('Cache updated successfully');
                } else if (event.data.type === 'OFFLINE_MODE') {
                    showOfflineNotification();
                } else if (event.data.type === 'ONLINE_MODE') {
                    hideOfflineNotification();
                }
            });
            
            // Online/Offline detection
            window.addEventListener('online', function() {
                log.success('Back online!');
                hideOfflineNotification();
            });
            
            window.addEventListener('offline', function() {
                log.warn('You are offline. Some features may be limited.');
                showOfflineNotification();
            });
            
            // Before install prompt (for "Add to Home Screen")
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                deferredPrompt = e;
                log.info('PWA install prompt available');
                
                // Optionally show custom install button
                showInstallButton(deferredPrompt);
            });
            
            // App installed
            window.addEventListener('appinstalled', function() {
                log.success('PWA installed successfully!');
                deferredPrompt = null;
            });
        }
        
        /**
         * Show update notification
         */
        function showUpdateNotification() {
            if (config.debug) {
                const notification = document.createElement('div');
                notification.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#4CAF50;color:white;padding:15px 20px;border-radius:8px;z-index:9999;font-family:sans-serif;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
                notification.innerHTML = '🔄 Update available! <button onclick="location.reload()" style="margin-left:10px;background:white;color:#4CAF50;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-weight:bold;">Refresh</button>';
                document.body.appendChild(notification);
                
                setTimeout(() => notification.remove(), 10000);
            }
        }
        
        /**
         * Show offline notification
         */
        function showOfflineNotification() {
            let notification = document.getElementById('af-pwa-offline-notice');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'af-pwa-offline-notice';
                notification.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#ff9800;color:white;padding:10px;text-align:center;z-index:9999;font-family:sans-serif;box-shadow:0 2px 8px rgba(0,0,0,0.1);';
                notification.textContent = '📵 You are offline. Some features may be limited.';
                document.body.insertBefore(notification, document.body.firstChild);
            }
        }
        
        /**
         * Hide offline notification
         */
        function hideOfflineNotification() {
            const notification = document.getElementById('af-pwa-offline-notice');
            if (notification) {
                notification.remove();
            }
        }
        
        /**
         * Show install button (optional)
         */
        function showInstallButton(deferredPrompt) {
            // You can implement custom install UI here
            // For now, just log the availability
            log.info('Install prompt ready. Call deferredPrompt.prompt() to show.');
        }
        
        // Export for external use
        window.AFTenancyPWA = {
            register: registerServiceWorker,
            config: config,
            isSupported: 'serviceWorker' in navigator
        };
    })();
</script>

{{-- Preload critical PWA resources --}}
<link rel="preload" href="{{ $manifestPath }}" as="fetch" crossorigin="anonymous">
