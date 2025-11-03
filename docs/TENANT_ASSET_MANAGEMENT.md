# Tenant Asset Management Guide

## 🎯 Overview

This guide covers two important features:
1. **Preview Mode**: Testing tenant homepages on localhost with correct asset URLs
2. **Git Tracking**: Managing tenant public assets in version control

---

## 1️⃣ Preview Mode for Tenant Homepages

### Problem
When testing tenant homepages on localhost (e.g., `http://localhost/tenant-home?home=al-emaan`), the `af_tenant_asset()` functions would try to find a tenant with domain "localhost" instead of the actual tenant domain.

### Solution
The `af_tenant_asset()`, `af_tenant_pwa_asset()`, and `af_tenant_seo_asset()` functions now support **preview mode** via session.

### How It Works

**Step 1: Route Setup**
The route `/tenant-home` automatically:
- Accepts a `?home={folder}` or `?site={folder}` parameter
- Finds the matching tenant domain (e.g., "al-emaan" → "al-emaan.pk")
- Stores the domain in session as `preview_tenant_domain`
- Renders the tenant's home view

**Step 2: Asset Loading**
When `af_tenant_asset()` is called in the view:
1. Checks if we're in preview mode (session has `preview_tenant_domain`)
2. Uses the preview domain instead of the current request domain
3. Generates correct URLs like `/storage/tenants/al-emaan.pk/assets/logo.png`

### Usage Example

```php
// In your tenant's home.blade.php view:
<link rel="stylesheet" href="{{ af_tenant_asset('assets/styles.css') }}">
<img src="{{ af_tenant_asset('assets/images/logo.png') }}" alt="Logo">
```

**Access on localhost:**
```
http://localhost:7777/tenant-home?home=al-emaan
```

**Result:**
- View loads from: `resources/views/tenants/al-emaan/home.blade.php`
- Assets load from: `/storage/tenants/al-emaan.pk/assets/...`

### Supported Query Parameters
- `?home=al-emaan` - Matches folder name "al-emaan" to domain "al-emaan.pk"
- `?site=tenant1` - Alternative parameter name
- `?home=tenant1.local` - Exact domain match

---

## 2️⃣ Git Tracking for Tenant Assets

### Problem
By default, Laravel's `storage/` directory is excluded from git. But for production tenants, you may want to track their public assets (logos, CSS, PWA files, etc.).

### Solution
Custom `.gitignore` in `storage/app/public/tenants/` with selective tracking rules.

### Directory Structure
```
storage/app/public/tenants/
├── .gitignore          ← Controls what's tracked
├── .gitkeep
├── tenant1.local/
│   ├── assets/
│   ├── pwa/
│   └── seo/
└── al-emaan.pk/
    ├── assets/
    ├── pwa/
    └── seo/
```

### Using the Command

**Track All Assets for a Tenant:**
```bash
php artisan tenant:git:track al-emaan.pk --type=all
```

**Track Only Assets Folder:**
```bash
php artisan tenant:git:track al-emaan.pk --type=assets
```

**Track Only PWA Files:**
```bash
php artisan tenant:git:track al-emaan.pk --type=pwa
```

**Track Only SEO Files:**
```bash
php artisan tenant:git:track al-emaan.pk --type=seo
```

**Untrack a Tenant:**
```bash
php artisan tenant:git:track al-emaan.pk --untrack
```

### Manual Tracking (Without Command)

Edit `storage/app/public/tenants/.gitignore`:

**Example 1: Track Everything for Production Tenant**
```gitignore
# Production tenant: al-emaan.pk
!al-emaan.pk/
!al-emaan.pk/**
```

**Example 2: Track Only Assets**
```gitignore
# Production tenant: al-emaan.pk (assets only)
!al-emaan.pk/
!al-emaan.pk/assets/
!al-emaan.pk/assets/**
```

**Example 3: Track Specific File Types Globally**
```gitignore
# Track all CSS files across all tenants
!**/assets/*.css

# Track all PNG images
!**/assets/images/*.png

# Track all PWA manifests
!**/pwa/manifest.json
```

### Git Workflow

After running the command or editing `.gitignore`:

```bash
# 1. Add the tenant assets
git add storage/app/public/tenants/al-emaan.pk/

# 2. Add the updated .gitignore
git add storage/app/public/tenants/.gitignore

# 3. Commit
git commit -m "Track tenant assets for al-emaan.pk"

# 4. Push
git push origin main
```

---

## 📋 Best Practices

### Preview Mode
1. ✅ **Use for Development**: Perfect for testing tenant views locally
2. ✅ **Works with All Helpers**: `af_tenant_asset()`, `af_tenant_pwa_asset()`, `af_tenant_seo_asset()`
3. ⚠️ **Security**: Route is localhost-only by default
4. ⚠️ **Sessions**: Requires session middleware to work

### Git Tracking
1. ✅ **Track Production Tenants**: Track assets for live production tenants
2. ✅ **Selective Tracking**: Only track what's necessary (logos, brand assets)
3. ❌ **Don't Track Test Data**: Avoid tracking test tenant assets
4. ❌ **Don't Track User Uploads**: Keep dynamic user uploads out of git
5. ✅ **Use .gitkeep**: Ensures directory structure is preserved

### Recommended Tracking Strategy

**Track These:**
- ✅ Brand logos and assets
- ✅ Custom CSS/JS for tenants
- ✅ PWA manifests and icons
- ✅ SEO files (robots.txt, sitemaps)
- ✅ Static tenant-specific images

**Don't Track These:**
- ❌ User-uploaded content
- ❌ Generated thumbnails
- ❌ Temporary files
- ❌ Cache files
- ❌ Test tenant data

---

## 🔧 Troubleshooting

### Preview Mode Not Working
1. Clear cache: `php artisan cache:clear`
2. Clear session: `php artisan session:flush`
3. Check session middleware is enabled for the route
4. Verify tenant exists in database

### Assets Not Loading
1. Verify storage symlink: `php artisan storage:link`
2. Check file exists: `ls storage/app/public/tenants/{domain}/assets/`
3. Check file permissions (755 for folders, 644 for files)
4. Verify .gitignore isn't excluding the file

### Git Not Tracking Files
1. Check `.gitignore` rules (order matters!)
2. Use `git check-ignore -v storage/app/public/tenants/{domain}/file.css` to debug
3. Remember: More specific rules override general rules
4. Clear git cache: `git rm -r --cached storage/app/public/tenants/`

---

## 📚 Related Documentation

- [Tenant Directory Management](./TENANT_DIRECTORIES.md)
- [Storage Symlinks](./STORAGE_SYMLINKS.md)
- [Artflow Tenancy Helpers](./HELPERS.md)
