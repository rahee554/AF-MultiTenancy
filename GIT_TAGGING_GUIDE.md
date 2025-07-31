# 📌 Git Tagging Guide for AF-MultiTenancy

**Last Updated**: November 7, 2025  
**Package**: AF-MultiTenancy  
**Repository**: https://github.com/rahee554/AF-MultiTenancy

---

## 🎯 Overview

This guide ensures consistent git tagging for Composer package versioning. Using proper version tags is critical for package distribution via Composer.

---

## ✅ Tagging Standards

### **Tag Format**
```
X.Y.Z.W
```

Where:
- **X** = Major version (breaking changes)
- **Y** = Minor version (new features)
- **Z** = Patch version (bug fixes)
- **W** = Hotfix number (optional, for patch releases)

### **Current Release Series**
- **0.7.7.5** - Latest stable
- **0.7.7.4** - Previous stable
- **0.7.6.9** - Previous minor version
- **0.7.5.1** - Earlier versions

### **Tag Naming Rules**
✅ **DO**: Use numeric-only tags (e.g., `0.7.7.5`)  
❌ **DON'T**: Use v-prefixed tags (e.g., ~~v0.7.7.5~~)  
❌ **DON'T**: Mix v-prefixed and numeric tags in same release series

---

## 📝 Creating a New Release

### **Step 1: Prepare Code**
```bash
# Ensure code is on main branch
git checkout main
git pull origin main

# Make your changes
git add .
git commit -m "Feature: Description of changes"
```

### **Step 2: Determine Version**
```bash
# View recent tags
git tag -l | sort -V | tail -5

# Identify next version:
# - Bug fixes only → increment last number (0.7.7.4 → 0.7.7.5)
# - New features → increment Z (0.7.6.9 → 0.7.7.0)
# - Breaking changes → increment Y (0.6.9.3 → 0.7.0.0)
```

### **Step 3: Create Annotated Tag**
```bash
# Create annotated tag (recommended)
git tag -a 0.7.7.5 -m "Version 0.7.7.5 - Bug fixes and improvements"

# Verify tag was created
git show 0.7.7.5
```

### **Step 4: Push Tag to Remote**
```bash
# Push single tag
git push origin 0.7.7.5

# Or push all tags
git push origin --tags
```

### **Step 5: Verify on Packagist**
```bash
# Wait 1-2 minutes for Packagist to detect the tag
# Visit: https://packagist.org/packages/artflow-studio/tenancy
# Verify version appears in list
```

---

## 🧹 Cleaning Up Incorrect Tags

### **If You Created a Wrong Tag Locally:**
```bash
# Delete local tag
git tag -d 0.7.7.0

# Verify deletion
git tag -l
```

### **If You Pushed a Wrong Tag to Remote:**
```bash
# Delete remote tag
git push origin :refs/tags/0.7.7.0

# Or use simpler syntax (Git 1.7.0+)
git push origin --delete 0.7.7.0

# Verify deletion
git ls-remote --tags origin
```

### **If Multiple Tags Point to Same Commit:**
```bash
# List all tags pointing to current HEAD
git tag --points-at HEAD

# Delete unwanted duplicates
git tag -d 0.7.7.0
git push origin :refs/tags/0.7.7.0
```

---

## 📊 Current Tag Structure

### **Latest Releases (0.7.x series)**
```
0.7.7.5    ← Latest (Middlewares fixed)
0.7.7.4    ← Previous (Auth Fixes)
0.7.7.0    ← Earlier (Dir fixes)
0.7.6.9    ← Previous version (Updated Stale Session)
0.7.6.8    ← Previous version (Stale Session Cache fixed)
```

### **Clean Status**
✅ All tags use numeric format (no v-prefix)  
✅ No duplicate tags on same commit  
✅ Tags follow semantic versioning  
✅ Composer can resolve all versions

---

## 🔍 Verification Commands

### **List All Tags Sorted by Version**
```bash
git tag -l | sort -V
```

### **Show All Tags with Commit**
```bash
git log --oneline --decorate --all | grep tag
```

### **Check Tags Pointing to Current HEAD**
```bash
git tag --points-at HEAD
```

### **Show Tag Details**
```bash
git show 0.7.7.5
```

### **Find Commit for Tag**
```bash
git rev-list -n 1 0.7.7.5
```

---

## 🚀 Composer Integration

### **How Composer Uses Git Tags**
1. User requests: `composer require artflow-studio/tenancy:^0.7.7.4`
2. Composer queries Packagist
3. Packagist queries GitHub API for tags
4. GitHub returns all matching version tags
5. Composer selects latest matching version
6. Package is downloaded and installed

### **Version Constraint Examples**
```php
// Exact version
"artflow-studio/tenancy": "0.7.7.4"

// Latest patch
"artflow-studio/tenancy": "0.7.7.*"

// Latest minor
"artflow-studio/tenancy": "^0.7.7"

// Caret (compatible versions)
"artflow-studio/tenancy": "^0.7"

// Latest version
"artflow-studio/tenancy": "*"
```

---

## ⚠️ Common Issues & Solutions

### **Issue: "found artflow-studio/tenancy[0.7.7.0] but it does not match constraint"**

**Cause**: Package has no tags higher than 0.7.7.0 that match the constraint

**Solution**:
1. Verify tag exists: `git tag -l | grep "^0\.7\.7"`
2. Ensure tag is pushed: `git push origin --tags`
3. Wait 2 minutes for Packagist to sync
4. Clear Composer cache: `composer clear-cache`

### **Issue: "Multiple tags on same commit causing confusion"**

**Cause**: Old v-prefixed tags mixed with numeric tags

**Solution**:
1. List tags on HEAD: `git tag --points-at HEAD`
2. Delete duplicates: `git tag -d v0.7.7.0`
3. Push deletions: `git push origin :refs/tags/v0.7.7.0`
4. Verify: `git tag --points-at HEAD`

### **Issue: Composer can't find version above 0.7.7.0**

**Cause**: Tag points to wrong commit or tag naming inconsistent

**Solution**:
1. Verify current commit: `git log -1 --oneline`
2. Create correct tag: `git tag -a 0.7.7.5 -m "Version 0.7.7.5"`
3. Push tag: `git push origin 0.7.7.5`
4. Clear cache: `composer clear-cache`

---

## 📋 Release Checklist

Before creating a release tag:

- [ ] Code reviewed and tested
- [ ] All commits pushed to main branch
- [ ] CHANGELOG.md updated with version notes
- [ ] Version number decided (X.Y.Z.W format)
- [ ] No v-prefix in tag name
- [ ] Annotated tag created with message
- [ ] Tag pushed to remote repository
- [ ] Composer cache cleared: `composer clear-cache`
- [ ] Version appears on Packagist within 2 minutes
- [ ] Test installation: `composer require artflow-studio/tenancy:^X.Y.Z`

---

## 🔄 Automation Scripts

### **PowerShell Script: List Versions**
```powershell
# List all versions sorted (PowerShell)
git tag -l | Where-Object { $_ -match '^[0-9]' } | Sort-Object { [version]$_ } -Descending
```

### **Bash Script: Create Release**
```bash
#!/bin/bash

VERSION=$1
MESSAGE=$2

if [ -z "$VERSION" ]; then
  echo "Usage: ./release.sh 0.7.7.5 'Version message'"
  exit 1
fi

git tag -a "$VERSION" -m "$MESSAGE"
git push origin "$VERSION"
echo "✅ Released version $VERSION"
```

---

## 📞 Support

For tagging issues, refer to:
- Git Tagging Docs: https://git-scm.com/book/en/v2/Git-Basics-Tagging
- Composer Versioning: https://getcomposer.org/doc/articles/versions.md
- Packagist Docs: https://packagist.org/about

---

## 📝 Version History

| Version | Date | Status | Notes |
|---------|------|--------|-------|
| 0.7.7.5 | Nov 7, 2025 | Latest | Middlewares fixed |
| 0.7.7.4 | Nov 7, 2025 | Stable | Auth Fixes |
| 0.7.7.0 | Nov 7, 2025 | Stable | Dir fixes |
| 0.7.6.9 | Nov 7, 2025 | Archive | Updated Stale Session |
| 0.7.6.8 | Nov 7, 2025 | Archive | Stale Session Cache fixed |

---

**Last Cleaned**: November 7, 2025  
**Status**: ✅ All tags cleaned and standardized  
**Composer Ready**: ✅ Yes
