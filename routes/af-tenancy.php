<?php

use ArtflowStudio\Tenancy\Models\Domain;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;

/**
 * Local tenant home route (localhost only)
 * Example: http://localhost:8000/tenant-home?url=al-emaan.pk
 */
Route::get('/tenant-home', function (\Illuminate\Http\Request $request) {
    // Ensure this route only works on localhost
    if (! in_array($request->getHost(), ['127.0.0.1', 'localhost'])) {
        abort(403, 'Access restricted to localhost only.');
    }

    // Get ?url= parameter (domain name)
    $urlParam = $request->query('url');

    if (empty($urlParam)) {
        abort(404, 'Missing tenant domain (use ?url=al-emaan.pk)');
    }

    // Sanitize domain name (prevent traversal)
    $domainName = preg_replace('/[^A-Za-z0-9._-]/', '', $urlParam);

    if (empty($domainName)) {
        abort(404, 'Invalid domain name.');
    }

    // Try to find the tenant by exact domain match
    $domain = Domain::where('domain', strtolower($domainName))->first();

    if (! $domain) {
        // Try partial match for convenience (al-emaan matches al-emaan.pk)
        $domain = Domain::where('domain', 'like', "%{$domainName}%")->first();
    }

    if (! $domain) {
        abort(404, "Domain '{$domainName}' not found in system.");
    }

    // Initialize tenancy context for this request
    tenancy()->initialize($domain->tenant);

    // Locate tenant view using domain folder
    $domainFolder = strtolower($domain->domain);
    $viewFile = resource_path("views/tenants/{$domainFolder}/home.blade.php");

    if (! file_exists($viewFile)) {
        abort(404, "View file '{$viewFile}' not found.");
    }

    return view()->file($viewFile);
})->name('tenant.local.home');
