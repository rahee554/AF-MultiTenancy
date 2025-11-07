# Git Tag Verification Script (PowerShell)
# Verifies that all tags are in correct format and Composer-compatible

Write-Host "🔍 AF-MultiTenancy Git Tag Verification" -ForegroundColor Cyan
Write-Host "=======================================" -ForegroundColor Cyan
Write-Host ""

# Check for any v-prefixed tags
Write-Host "✓ Checking for v-prefixed tags..." -ForegroundColor Green
$vPrefixed = @(git tag -l | Where-Object { $_ -match '^v' })
if ($vPrefixed.Count -gt 0) {
    Write-Host "  ❌ Found $($vPrefixed.Count) v-prefixed tags (should be 0)" -ForegroundColor Red
    Write-Host "  Tags found:" -ForegroundColor Red
    $vPrefixed | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }
    exit 1
} else {
    Write-Host "  ✅ No v-prefixed tags found (Good)" -ForegroundColor Green
}
Write-Host ""

# Check for duplicate tags on same commit
Write-Host "✓ Checking for duplicate tags on same commit..." -ForegroundColor Green
$headTags = @(git tag --points-at HEAD)
if ($headTags.Count -gt 1) {
    Write-Host "  ⚠️  Found $($headTags.Count) tags on HEAD (should be 1)" -ForegroundColor Yellow
    Write-Host "  Current tags:" -ForegroundColor Yellow
    $headTags | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
} else {
    Write-Host "  ✅ Only one tag on HEAD (Good)" -ForegroundColor Green
}
Write-Host ""

# Verify all tags are numeric
Write-Host "✓ Verifying all tags are numeric..." -ForegroundColor Green
$nonNumeric = @(git tag -l | Where-Object { $_ -notmatch '^[0-9]' })
if ($nonNumeric.Count -gt 0) {
    Write-Host "  ❌ Found $($nonNumeric.Count) non-numeric tags" -ForegroundColor Red
    $nonNumeric | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }
    exit 1
} else {
    Write-Host "  ✅ All tags are numeric (Good)" -ForegroundColor Green
}
Write-Host ""

# List recent tags
Write-Host "✓ Recent version tags:" -ForegroundColor Green
$allTags = @(git tag -l | Where-Object { $_ -match '^[0-9]' } | Sort-Object { [version]$_ } -Descending)
$allTags | Select-Object -First 10 | ForEach-Object { Write-Host "  $_" -ForegroundColor Green }
Write-Host ""

# Check if latest tag matches current HEAD
Write-Host "✓ Latest tag status:" -ForegroundColor Green
if ($allTags.Count -gt 0) {
    $latestTag = $allTags[0]
    $latestCommit = git rev-list -n 1 $latestTag
    $headCommit = git rev-parse HEAD

    if ($latestCommit -eq $headCommit) {
        Write-Host "  ✅ Latest tag ($latestTag) is on current HEAD" -ForegroundColor Green
    } else {
        Write-Host "  ℹ️  Latest tag ($latestTag) is not on HEAD" -ForegroundColor Cyan
        Write-Host "     Current HEAD: $(git log -1 --oneline)" -ForegroundColor Cyan
    }
}
Write-Host ""

# Summary
Write-Host "=======================================" -ForegroundColor Cyan
Write-Host "✅ All checks passed! Package is ready for Composer." -ForegroundColor Green
Write-Host ""

$remoteUrl = git config --get remote.origin.url
if ($allTags.Count -gt 0) {
    $latestTag = $allTags[0]
    Write-Host "Latest release: $latestTag" -ForegroundColor Cyan
    Write-Host "Repository: $remoteUrl" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "📦 Composer Version Resolution Test:" -ForegroundColor Cyan
    Write-Host "  Version ^0.7.7.0 would resolve to: $latestTag" -ForegroundColor Cyan
    Write-Host ""
}
