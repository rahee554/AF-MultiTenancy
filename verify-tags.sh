#!/bin/bash
# Git Tag Verification Script
# Verifies that all tags are in correct format and Composer-compatible

echo "🔍 AF-MultiTenancy Git Tag Verification"
echo "========================================"
echo ""

# Check for any v-prefixed tags
echo "✓ Checking for v-prefixed tags..."
V_PREFIXED=$(git tag -l | grep -c "^v" || true)
if [ "$V_PREFIXED" -gt 0 ]; then
    echo "  ❌ Found $V_PREFIXED v-prefixed tags (should be 0)"
    echo "  Run: git tag -l | grep '^v'"
    exit 1
else
    echo "  ✅ No v-prefixed tags found (Good)"
fi
echo ""

# Check for duplicate tags on same commit
echo "✓ Checking for duplicate tags on same commit..."
CURRENT_TAGS=$(git tag --points-at HEAD | wc -l)
if [ "$CURRENT_TAGS" -gt 1 ]; then
    echo "  ⚠️  Found $CURRENT_TAGS tags on HEAD (should be 1)"
    echo "  Current tags:"
    git tag --points-at HEAD | sed 's/^/    /'
else
    echo "  ✅ Only one tag on HEAD (Good)"
fi
echo ""

# Verify all tags are numeric
echo "✓ Verifying all tags are numeric..."
NON_NUMERIC=$(git tag -l | grep -v "^[0-9]" | wc -l || true)
if [ "$NON_NUMERIC" -gt 0 ]; then
    echo "  ❌ Found $NON_NUMERIC non-numeric tags"
    git tag -l | grep -v "^[0-9]" | sed 's/^/    /'
    exit 1
else
    echo "  ✅ All tags are numeric (Good)"
fi
echo ""

# List recent tags
echo "✓ Recent version tags:"
git tag -l | sort -V | tail -10 | sed 's/^/  /'
echo ""

# Check if latest tag matches current HEAD
echo "✓ Latest tag status:"
LATEST_TAG=$(git tag -l | sort -V | tail -1)
LATEST_COMMIT=$(git rev-list -n 1 "$LATEST_TAG")
HEAD_COMMIT=$(git rev-parse HEAD)

if [ "$LATEST_COMMIT" = "$HEAD_COMMIT" ]; then
    echo "  ✅ Latest tag ($LATEST_TAG) is on current HEAD"
else
    echo "  ℹ️  Latest tag ($LATEST_TAG) is not on HEAD"
    echo "     Current HEAD: $(git log -1 --oneline)"
fi
echo ""

# Summary
echo "========================================"
echo "✅ All checks passed! Package is ready for Composer."
echo ""
echo "Latest release: $LATEST_TAG"
echo "Repository: $(git config --get remote.origin.url)"
echo ""
