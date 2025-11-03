# PWA Commands Documentation

## Overview

The PWA (Progressive Web App) module provides comprehensive commands to enable, disable, test, and manage PWA functionality for your multi-tenant Laravel application. Each tenant can have PWA enabled/disabled independently with custom configurations.

## Available Commands

### 1. Enable PWA (`tenant:pwa:enable`)

Enable PWA functionality for one or more tenants.

#### Usage

```bash
# Interactive mode - select tenant from list
php artisan tenant:pwa:enable --interactive

# Enable for specific tenant
php artisan tenant:pwa:enable --tenant=1

# Enable for all tenants
php artisan tenant:pwa:enable --all

# Enable with custom configuration
php artisan tenant:pwa:enable --tenant=1 --cache-strategy=cache-first --theme-color=#4CAF50
```

#### Options

- `--tenant=ID` - Tenant ID to enable PWA for
- `--all` - Enable PWA for all tenants
- `--interactive` - Interactive mode to select tenant
- `--cache-strategy=STRATEGY` - Cache strategy (network-first|cache-first|stale-while-revalidate)
- `--theme-color=COLOR` - Theme color for PWA (hex format)
- `--background-color=COLOR` - Background color for PWA (hex format)

#### What It Does

1. Creates PWA directory structure (`public/pwa/{domain}/`)
2. Generates `manifest.json` with tenant-specific settings
3. Creates service worker (`sw.js`) with selected caching strategy
4. Generates offline page (`offline.html`)
5. Creates icons directory
6. Updates tenant database record (`pwa_enabled = true`)
7. Stores PWA configuration in `pwa_config` JSON column

#### Example Output

```
🚀 Enable PWA for Tenant(s)

📋 Select tenant(s) to enable PWA for:

Select tenant to enable PWA:
  [0] ID: 1 | Al-Emaan Travels | tenancy1.local | ❌ PWA Disabled
  [1] ID: 2 | Demo Corp | demo.local | ✅ PWA Enabled
  [2] 🌐 Enable for ALL tenants
  [3] ❌ Cancel

🔧 Enabling PWA for: Al-Emaan Travels (tenancy1.local)

⚙️  PWA Configuration:
 App Name [Al-Emaan Travels]:
 Short Name (max 12 chars) [Al-Emaan Tra]:
 Description [Progressive Web App for Al-Emaan Travels]:
 Cache Strategy [network-first]:
  [0] network-first
  [1] cache-first
  [2] stale-while-revalidate
 Theme Color (hex) [#667eea]:
 Background Color (hex) [#ffffff]:
 Display Mode [standalone]:
  [0] standalone
  [1] fullscreen
  [2] minimal-ui
  [3] browser

✅ PWA enabled successfully!

📋 PWA Information:
+----------------+-----------------------------------------------+
| Property       | Value                                         |
+----------------+-----------------------------------------------+
| Tenant         | Al-Emaan Travels                              |
| Domain         | tenancy1.local                                |
| Status         | ✅ Enabled                                    |
| Manifest       | ✅ Generated                                  |
| Service Worker | ✅ Generated                                  |
| Offline Page   | ✅ Generated                                  |
| Path           | /path/to/public/pwa/tenancy1_local            |
+----------------+-----------------------------------------------+

💡 Next Steps:
   1. Add PWA meta tags to your layout (see documentation)
   2. Test PWA: php artisan tenant:pwa:test --tenant=1
   3. Check status: php artisan tenant:pwa:status --tenant=1
```

---

### 2. Disable PWA (`tenant:pwa:disable`)

Disable PWA functionality for one or more tenants.

#### Usage

```bash
# Interactive mode
php artisan tenant:pwa:disable --interactive

# Disable for specific tenant
php artisan tenant:pwa:disable --tenant=1

# Disable and remove PWA files
php artisan tenant:pwa:disable --tenant=1 --remove-files

# Disable for all enabled tenants
php artisan tenant:pwa:disable --all
```

#### Options

- `--tenant=ID` - Tenant ID to disable PWA for
- `--all` - Disable PWA for all tenants with PWA enabled
- `--interactive` - Interactive mode to select tenant
- `--remove-files` - Remove PWA files from disk (manifest, service worker, offline page)

#### What It Does

1. Updates tenant database record (`pwa_enabled = false`)
2. Optionally removes PWA directory and all files
3. Keeps PWA configuration in database for future re-enabling

---

### 3. Check PWA Status (`tenant:pwa:status`)

View PWA status, configuration, and file existence for tenants.

#### Usage

```bash
# Interactive mode
php artisan tenant:pwa:status --interactive

# Check status for specific tenant
php artisan tenant:pwa:status --tenant=1

# Show status for all tenants
php artisan tenant:pwa:status --all
```

#### Options

- `--tenant=ID` - Tenant ID to check status for
- `--all` - Show status for all tenants
- `--interactive` - Interactive mode to select tenant

#### Example Output (Single Tenant)

```
📊 PWA Status for: Al-Emaan Travels (tenancy1.local)

+-------------+-------------------------------------+
| Property    | Value                               |
+-------------+-------------------------------------+
| Tenant ID   | 1                                   |
| Tenant Name | Al-Emaan Travels                    |
| Domain      | tenancy1.local                      |
| PWA Enabled | ✅ Yes                              |
| PWA Path    | /path/to/public/pwa/tenancy1_local  |
+-------------+-------------------------------------+

📁 PWA Files:
+--------------------------+-----------+
| File                     | Status    |
+--------------------------+-----------+
| manifest.json            | ✅ Exists |
| sw.js (Service Worker)   | ✅ Exists |
| offline.html             | ✅ Exists |
+--------------------------+-----------+

⚙️  PWA Configuration:
+------------------+--------------------------------------------------+
| Setting          | Value                                            |
+------------------+--------------------------------------------------+
| name             | Al-Emaan Travels                                 |
| short_name       | Al-Emaan Tra                                     |
| cache_strategy   | network-first                                    |
| theme_color      | #667eea                                          |
| background_color | #ffffff                                          |
| display          | standalone                                       |
+------------------+--------------------------------------------------+

✅ PWA is fully configured and operational
```

#### Example Output (All Tenants)

```
📊 PWA Status for All Tenants (5 total)

+----+-------------------+------------------+--------------+------------------+----------------+
| ID | Name              | Domain           | PWA Status   | Health           | Cache Strategy |
+----+-------------------+------------------+--------------+------------------+----------------+
| 1  | Al-Emaan Travels  | tenancy1.local   | ✅ Enabled   | ✅ Healthy       | network-first  |
| 2  | Demo Corp         | demo.local       | ✅ Enabled   | ⚠️  Missing Files| cache-first    |
| 3  | Test Tenant       | test.local       | ❌ Disabled  | ❌ N/A           | N/A            |
| 4  | Beta Corp         | beta.local       | ✅ Enabled   | ✅ Healthy       | network-first  |
| 5  | Alpha Inc         | alpha.local      | ❌ Disabled  | ❌ N/A           | N/A            |
+----+-------------------+------------------+--------------+------------------+----------------+

📈 Summary:
+-----------------+-------+
| Metric          | Count |
+-----------------+-------+
| Total Tenants   | 5     |
| ✅ PWA Enabled  | 3     |
| ❌ PWA Disabled | 2     |
| ✅ Healthy      | 2     |
| ⚠️  Unhealthy   | 1     |
+-----------------+-------+
```

---

### 4. Test PWA (`tenant:pwa:test`)

Run comprehensive tests on PWA functionality to ensure everything is working correctly.

#### Usage

```bash
# Interactive mode
php artisan tenant:pwa:test --interactive

# Test specific tenant
php artisan tenant:pwa:test --tenant=1

# Test all enabled tenants
php artisan tenant:pwa:test --all

# Verbose output showing all test details
php artisan tenant:pwa:test --all --verbose
```

#### Options

- `--tenant=ID` - Tenant ID to test PWA for
- `--all` - Test PWA for all enabled tenants
- `--interactive` - Interactive mode to select tenant
- `--verbose` - Show detailed test output for failures

#### Tests Performed

1. **PWA Enabled** - Checks if PWA is enabled in database
2. **Manifest File** - Verifies manifest.json exists
3. **Manifest Valid JSON** - Validates JSON syntax
4. **Service Worker File** - Verifies sw.js exists
5. **Offline Page** - Verifies offline.html exists
6. **Manifest Required Fields** - Checks for required fields (name, short_name, start_url, display)

#### Example Output (Single Tenant)

```
🧪 Testing PWA for: Al-Emaan Travels (tenancy1.local)

+--------------------------+---------+-----------------------------+
| Test                     | Status  | Details                     |
+--------------------------+---------+-----------------------------+
| PWA Enabled              | ✅ Pass | PWA is enabled              |
| Manifest File            | ✅ Pass | manifest.json found         |
| Manifest Valid JSON      | ✅ Pass | Valid JSON                  |
| Service Worker File      | ✅ Pass | sw.js found                 |
| Offline Page             | ✅ Pass | offline.html found          |
| Manifest Required Fields | ✅ Pass | All required fields present |
+--------------------------+---------+-----------------------------+

✅ All PWA tests passed!

💡 Your PWA is fully functional and ready for use.
```

#### Example Output (All Tenants with Failures)

```
🧪 Testing PWA for 3 tenant(s)...

🧪 Testing Al-Emaan Travels (tenancy1.local)...
🧪 Testing Demo Corp (demo.local)...
   ❌ Manifest File: manifest.json missing
   ❌ Service Worker File: sw.js missing
🧪 Testing Beta Corp (beta.local)...

📊 Test Results Summary:
+----+------------------+------------------+--------------+-----------------+
| ID | Name             | Domain           | Tests Passed | Overall Status  |
+----+------------------+------------------+--------------+-----------------+
| 1  | Al-Emaan Travels | tenancy1.local   | 6/6          | ✅ Passed       |
| 2  | Demo Corp        | demo.local       | 4/6          | ❌ Failed       |
| 4  | Beta Corp        | beta.local       | 6/6          | ✅ Passed       |
+----+------------------+------------------+--------------+-----------------+

📈 Summary:
+--------------+-------+
| Metric       | Count |
+--------------+-------+
| Total Tested | 3     |
| ✅ Passed    | 2     |
| ❌ Failed    | 1     |
+--------------+-------+
```

---

## PWA Configuration

PWA settings are stored in two places:

### 1. Global Configuration (`config/artflow-tenancy.php`)

```php
'pwa' => [
    'enabled' => true,
    'manifest' => [
        'display' => 'standalone',
        'orientation' => 'any',
        'theme_color' => '#667eea',
        'background_color' => '#ffffff',
    ],
    'service_worker' => [
        'cache_strategy' => 'network-first',
        'available_strategies' => [
            'network-first',
            'cache-first',
            'stale-while-revalidate',
        ],
    ],
    // ... more settings
],
```

### 2. Per-Tenant Configuration (Database)

Stored in `tenants` table:
- `pwa_enabled` (boolean) - Whether PWA is enabled
- `pwa_config` (JSON) - Tenant-specific PWA configuration

```json
{
  "name": "Al-Emaan Travels",
  "short_name": "Al-Emaan Tra",
  "description": "Progressive Web App for Al-Emaan Travels",
  "theme_color": "#667eea",
  "background_color": "#ffffff",
  "cache_strategy": "network-first",
  "display": "standalone"
}
```

---

## Cache Strategies

### Network First (Default)
Best for: Dynamic content, APIs, real-time data
- Tries network first
- Falls back to cache if offline
- Updates cache with fresh data

### Cache First
Best for: Static assets, images, fonts
- Serves from cache immediately
- Updates cache in background
- Fastest response time

### Stale While Revalidate
Best for: Balance of speed and freshness
- Serves stale cache immediately
- Fetches fresh data in background
- Updates cache for next request

---

## File Structure

When PWA is enabled for a tenant, the following structure is created:

```
public/pwa/
└── {domain_folder}/          # e.g., tenancy1_local/
    ├── manifest.json          # PWA manifest
    ├── sw.js                  # Service worker
    ├── offline.html           # Offline fallback page
    └── icons/                 # PWA icons directory
        ├── icon-72x72.png
        ├── icon-96x96.png
        ├── icon-128x128.png
        ├── icon-144x144.png
        ├── icon-152x152.png
        ├── icon-192x192.png
        ├── icon-384x384.png
        └── icon-512x512.png
```

---

## Integration with Your Application

### 1. Add PWA Meta Tags to Layout

```blade
@if(tenant() && tenant()->pwa_enabled)
<link rel="manifest" href="/pwa/{{ str_replace('.', '_', tenant()->domains()->first()->domain) }}/manifest.json">
<meta name="theme-color" content="{{ tenant()->pwa_config['theme_color'] ?? '#667eea' }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
@endif
```

### 2. Register Service Worker

```blade
@if(tenant() && tenant()->pwa_enabled)
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/pwa/{{ str_replace('.', '_', tenant()->domains()->first()->domain) }}/sw.js')
            .then(reg => console.log('Service Worker registered:', reg))
            .catch(err => console.error('Service Worker registration failed:', err));
    });
}
</script>
@endif
```

### 3. Handle PWA-specific Requests

The `TenantPWAMiddleware` automatically handles:
- CSRF token refresh (419 errors)
- Session renewal notifications
- 403 Forbidden errors with cache clearing
- PWA-specific response headers

---

## Error Handling

The PWA module handles common errors gracefully:

### CSRF Token Mismatch (419)
- Automatically generates new CSRF token
- Adds `X-PWA-New-Token` header to response
- Client can retry request with new token

### Forbidden Access (403)
- Clears all caches
- Notifies client to refresh
- Adds `X-PWA-Auth-Error` header

### Session Expiration
- Adds `X-PWA-Session-Remaining` header with minutes remaining
- Notifies when session is about to expire (at 90% of lifetime)

---

## Best Practices

1. **Test After Enabling** - Always run `tenant:pwa:test` after enabling PWA
2. **Use Network-First for Dynamic Content** - APIs, dashboards, user-specific data
3. **Use Cache-First for Static Assets** - CSS, JS, images, fonts
4. **Monitor PWA Status** - Regularly check status with `tenant:pwa:status --all`
5. **Enable for Production Tenants** - Only enable PWA for active, production tenants
6. **Keep Files Updated** - Re-enable PWA if you update configurations

---

## Troubleshooting

### PWA not working after enabling
```bash
# Check status
php artisan tenant:pwa:status --tenant=1

# Run tests
php artisan tenant:pwa:test --tenant=1

# Regenerate files
php artisan tenant:pwa:enable --tenant=1
```

### Service Worker not registering
1. Check browser console for errors
2. Ensure HTTPS is enabled (required for service workers in production)
3. Verify service worker file exists and is accessible
4. Check for JavaScript errors on page

### Offline page not showing
1. Verify offline.html exists
2. Check service worker cache configuration
3. Test in browser DevTools (Network tab, offline mode)

---

## Advanced Usage

### Batch Enable PWA for Multiple Tenants

```bash
# Enable for all tenants at once
php artisan tenant:pwa:enable --all

# With custom theme color
php artisan tenant:pwa:enable --all --theme-color=#4CAF50
```

### Automated Testing

```bash
# Add to CI/CD pipeline
php artisan tenant:pwa:test --all

# Exit with non-zero if any tests fail
if [ $? -ne 0 ]; then
    echo "PWA tests failed!"
    exit 1
fi
```

### Monitor PWA Health

```bash
# Schedule regular health checks
# In Laravel scheduler (app/Console/Kernel.php)
$schedule->command('tenant:pwa:status --all')->daily();
```

---

## Support & Documentation

For more information:
- Full Documentation: `/docs/pwa`
- Package Repository: `artflow-studio/tenancy`
- Issue Tracker: GitHub Issues

---

**Version:** 1.0.0  
**Last Updated:** October 22, 2025
