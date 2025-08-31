<?php

namespace ArtflowStudio\Tenancy\Http\Controllers;

use Illuminate\Routing\Controller;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Facades\Tenancy;

class TenantViewController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Display tenant dashboard with comprehensive statistics.
     */
    public function dashboard()
    {
        // Redirect to Livewire-powered admin dashboard
        return redirect()->route('tenancy.admin.dashboard');
    }

    /**
     * Display tenant listing.
     */
    public function index()
    {
        // Redirect to Livewire-powered tenants index
        return redirect()->route('tenancy.admin.index');
    }

    /**
     * Show create tenant form.
     */
    public function create()
    {
        return view('admin.tenants.create');
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'domain' => 'required|string|unique:domains,domain|max:255|regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/',
            'database_suffix' => 'nullable|string|max:100',
            'status' => 'in:active,inactive,suspended,blocked',
            'notes' => 'nullable|string',
            'run_migrations' => 'nullable|boolean',
            'run_seeders' => 'nullable|boolean',
        ]);

        try {
            // Generate database name from suffix or auto-generate
            $databaseSuffix = $request->input('database_suffix');
            if (!$databaseSuffix) {
                $slug = \Illuminate\Support\Str::slug($request->input('name'), '_');
                $randomSuffix = substr(md5(microtime()), 0, 8);
                $databaseSuffix = $slug . '_' . $randomSuffix;
            }
            $databaseName = 'tenant_' . $databaseSuffix;
            
            $tenant = $this->tenantService->createTenant(
                $request->input('name'),
                $request->input('domain'),
                $request->input('status', 'active'),
                $databaseName,
                $request->input('notes')
            );
            
            $operations = [];
            
            // Run migrations if requested
            if ($request->boolean('run_migrations', false)) {
                $this->migrateTenantSafely($tenant);
                $operations[] = 'migrations';
            }
            
            // Run seeders if requested
            if ($request->boolean('run_seeders', false)) {
                $this->seedTenantSafely($tenant);
                $operations[] = 'seeders';
            }
            
            $message = "Tenant '{$tenant->name}' created successfully!";
            if (!empty($operations)) {
                $message .= ' (' . implode(' and ', $operations) . ' completed)';
            }
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', $message);
            
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to create tenant: ' . $e->getMessage()]);
        }
    }

    /**
     * Show a specific tenant.
     */
    public function show(string $id)
    {
        // Redirect to Livewire-powered tenant view
        return redirect()->route('tenancy.admin.tenants.show', ['tenant' => $id]);
    }

    /**
     * Show edit tenant form.
     */
    public function edit($uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
        
        return view('admin.tenants.edit', compact('tenant'));
    }

    /**
     * Update tenant.
     */
    public function update(Request $request, $uuid)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'status' => 'in:active,inactive,suspended,blocked',
            'notes' => 'nullable|string',
        ]);

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $tenant->update($request->only(['name', 'status', 'notes']));
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', 'Tenant updated successfully');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to update tenant: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete tenant.
     */
    public function destroy($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Delete tenant using service
            $this->tenantService->deleteTenant($tenant);
            
            return redirect()->route('admin.tenants.index')
                           ->with('success', 'Tenant deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete tenant: ' . $e->getMessage()]);
        }
    }

    /**
     * Reset tenant database.
     */
    public function resetTenant($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            // Ensure we're working with the correct tenant database
            $tenant->makeCurrent();
            
            // Run fresh migrations ONLY for tenant database
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            
            // End tenancy context
            Tenancy::end();
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', 'Tenant database reset successfully');
            
        } catch (\Exception $e) {
            Tenancy::end();
            return back()->withErrors(['error' => 'Reset failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Migrate tenant database.
     */
    public function migrateTenant($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $this->migrateTenantSafely($tenant);
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', 'Tenant migrations completed successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Migration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Seed tenant database.
     */
    public function seedTenant($uuid)
    {
        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $this->seedTenantSafely($tenant);
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', 'Tenant seeding completed successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Seeding failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Update tenant status.
     */
    public function updateStatus(Request $request, $uuid)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended,blocked'
        ]);

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            $oldStatus = $tenant->status;
            $tenant->status = $request->status;
            $tenant->save();
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', "Tenant status updated from {$oldStatus} to {$request->status}");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update status: ' . $e->getMessage()]);
        }
    }

    /**
     * Add domain to tenant.
     */
    public function addDomain(Request $request, $uuid)
    {
        $request->validate([
            'domain' => 'required|string|unique:domains,domain'
        ]);

        try {
            $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
            
            \Stancl\Tenancy\Database\Models\Domain::create([
                'domain' => $request->domain,
                'tenant_id' => $tenant->id,
            ]);
            
            return redirect()->route('admin.tenants.show', $tenant->uuid)
                           ->with('success', 'Domain added successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to add domain: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove domain.
     */
    public function removeDomain($domainId)
    {
        try {
            $domain = \Stancl\Tenancy\Database\Models\Domain::findOrFail($domainId);
            $tenantUuid = $domain->tenant->uuid;
            $domain->delete();
            
            return redirect()->route('admin.tenants.show', $tenantUuid)
                           ->with('success', 'Domain removed successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove domain: ' . $e->getMessage()]);
        }
    }

    /**
     * Safely migrate tenant database
     */
    private function migrateTenantSafely(Tenant $tenant)
    {
        try {
            // Make sure tenant database exists
            if (!$this->checkTenantDatabaseExists($tenant)) {
                DB::statement("CREATE DATABASE IF NOT EXISTS `{$tenant->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Switch to tenant context
            $tenant->makeCurrent();
            
            // Run migrations for tenant database only
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            
        } finally {
            // Always end tenancy context
            Tenancy::end();
        }
    }

    /**
     * Safely seed tenant database
     */
    private function seedTenantSafely(Tenant $tenant)
    {
        try {
            // Switch to tenant context
            $tenant->makeCurrent();
            
            // Run seeders for tenant database only
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            
        } finally {
            // Always end tenancy context
            Tenancy::end();
        }
    }

    /**
     * Check if tenant database exists
     */
    private function checkTenantDatabaseExists(Tenant $tenant): bool
    {
        try {
            $databases = DB::select('SHOW DATABASES');
            $databaseNames = array_column($databases, 'Database');
            return in_array($tenant->database_name, $databaseNames);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get tenant statistics
     */
    private function getTenantStatistics(Tenant $tenant): array
    {
        try {
            if (!$this->checkTenantDatabaseExists($tenant)) {
                return [
                    'database_exists' => false,
                    'migrations_run' => 0,
                    'pending_migrations' => 0,
                    'database_size' => '0 MB',
                    'table_count' => 0,
                    'last_migration' => null,
                ];
            }

            $tenant->makeCurrent();
            
            // Check migrations
            $migrationsRun = 0;
            $pendingMigrations = 0;
            
            if (Schema::hasTable('migrations')) {
                $migrationsRun = DB::table('migrations')->count();
            }
            
            // Get database size
            $dbSize = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS DB_SIZE_MB 
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$tenant->database_name]);
            
            $databaseSize = ($dbSize[0]->DB_SIZE_MB ?? 0) . ' MB';
            
            // Get table count
            $tableCount = count(DB::select("SHOW TABLES"));
            
            Tenancy::end();
            
            return [
                'database_exists' => true,
                'migrations_run' => $migrationsRun,
                'pending_migrations' => $pendingMigrations,
                'database_size' => $databaseSize,
                'table_count' => $tableCount,
                'last_migration' => $migrationsRun > 0 ? now()->format('c') : null,
            ];
            
        } catch (\Exception $e) {
            Tenancy::end();
            return [
                'database_exists' => false,
                'error' => $e->getMessage(),
                'migrations_run' => 0,
                'pending_migrations' => 0,
                'database_size' => '0 MB',
                'table_count' => 0,
            ];
        }
    }

    // Helper methods from original controller
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_type' => config('database.default'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_limit' => ini_get('memory_limit'),
            'server_time' => now()->format('c'),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Handle tenant homepage display
     */
    public function tenantHomepage(Request $request)
    {
        // Get current tenant using stancl/tenancy service container binding
        $tenant = null;
        try {
            $tenant = app('tenant');
        } catch (\Exception $e) {
            // No tenant context available, redirect to fallback
            $fallbackRedirect = config('artflow-tenancy.homepage.fallback_redirect', '/login');
            return redirect($fallbackRedirect);
        }

        if (!$tenant || !method_exists($tenant, 'hasHomepage')) {
            $fallbackRedirect = config('artflow-tenancy.homepage.fallback_redirect', '/login');
            return redirect($fallbackRedirect);
        }

        // If tenant doesn't have homepage enabled, redirect to configured fallback
        if (!$tenant->hasHomepage()) {
            $fallbackRedirect = config('artflow-tenancy.homepage.fallback_redirect', '/login');
            return redirect($fallbackRedirect);
        }

        // If tenant has homepage, try to load custom homepage view
        $domain = $request->getHost();
        $viewPath = config('artflow-tenancy.homepage.view_path', 'tenants');
        $customViewPath = "{$viewPath}.{$domain}.home";
        
        // Check if custom tenant homepage view exists
        if (view()->exists($customViewPath)) {
            return view($customViewPath, [
                'tenant' => $tenant,
                'domain' => $domain
            ]);
        }
        
        // Fallback to default tenant homepage if exists
        if (view()->exists("{$viewPath}.home")) {
            return view("{$viewPath}.home", [
                'tenant' => $tenant,
                'domain' => $domain
            ]);
        }

        // If no homepage views exist, redirect to fallback
        $fallbackRedirect = config('artflow-tenancy.homepage.fallback_redirect', '/login');
        return redirect($fallbackRedirect);
    }

    private function getTotalConnections(): int { return 4; }
    private function getPersistentConnections(): int { return 2; }
    private function getCacheSize(): string { return '15.2 MB'; }
    private function getActiveUsersPerTenant(): array { return []; }
    private function getDatabaseSizes(): array { return []; }
    private function getMigrationStatusForAllTenants(): array { return []; }
    private function getLastActivity(): string { return '2 hours ago'; }

    /**
     * Display system health check.
     */
    public function health()
    {
        $healthChecks = [
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'storage' => $this->checkStorageAccess(),
            'tenancy' => $this->checkTenancyStatus(),
            'migrations' => $this->checkMigrations(),
        ];

        $overallStatus = collect($healthChecks)->every(fn($check) => $check['status'] === 'ok') ? 'healthy' : 'unhealthy';

        return view('artflow-tenancy::health', [
            'health_checks' => $healthChecks,
            'overall_status' => $overallStatus,
            'timestamp' => now(),
        ]);
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkCacheConnection(): array
    {
        try {
            cache()->put('health_check', 'ok', 60);
            $value = cache()->get('health_check');
            return ['status' => $value === 'ok' ? 'ok' : 'error', 'message' => 'Cache is working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache connection failed: ' . $e->getMessage()];
        }
    }

    private function checkStorageAccess(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            \Storage::disk('local')->put($testFile, 'test');
            \Storage::disk('local')->delete($testFile);
            return ['status' => 'ok', 'message' => 'Storage access working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage access failed: ' . $e->getMessage()];
        }
    }

    private function checkTenancyStatus(): array
    {
        try {
            $tenantCount = Tenant::count();
            return ['status' => 'ok', 'message' => "Tenancy system operational ({$tenantCount} tenants)"];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Tenancy system error: ' . $e->getMessage()];
        }
    }

    private function checkMigrations(): array
    {
        try {
            // Check if migrations table exists and has records
            if (Schema::hasTable('migrations')) {
                $migrationCount = DB::table('migrations')->count();
                return ['status' => 'ok', 'message' => "{$migrationCount} migrations applied"];
            }
            return ['status' => 'warning', 'message' => 'No migrations table found'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Migration check failed: ' . $e->getMessage()];
        }
    }
}
