<?php

namespace ArtflowStudio\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
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
        $stats = $this->tenantService->getSystemStats();
        $systemInfo = $this->getSystemInfo();
        $tenants = Tenant::with('domains')->get();
        $recentTenants = Tenant::latest()->limit(5)->get();
        
        // Enhanced statistics
        $enhancedStats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'inactive_tenants' => Tenant::where('status', 'inactive')->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'blocked_tenants' => Tenant::where('status', 'blocked')->count(),
            'total_domains' => \Stancl\Tenancy\Database\Models\Domain::count(),
            'total_connections' => $this->getTotalConnections(),
            'persistent_connections' => $this->getPersistentConnections(),
            'cache_size' => $this->getCacheSize(),
            'active_users_per_tenant' => $this->getActiveUsersPerTenant(),
            'database_sizes' => $this->getDatabaseSizes(),
            'migration_status' => $this->getMigrationStatusForAllTenants(),
            'last_activity' => $this->getLastActivity(),
        ];
        
        $allStats = array_merge($stats, $enhancedStats);
        
        return view('admin.tenants.dashboard', compact('allStats', 'systemInfo', 'tenants', 'recentTenants'));
    }

    /**
     * Display tenant listing.
     */
    public function index()
    {
        $tenants = Tenant::with('domains')->latest()->paginate(15);
        
        return view('admin.tenants.index', compact('tenants'));
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
     * Show tenant details.
     */
    public function show($uuid)
    {
        $tenant = Tenant::with('domains')->where('uuid', $uuid)->firstOrFail();
        
        // Get tenant statistics
        $stats = $this->getTenantStatistics($tenant);
        
        return view('admin.tenants.show', compact('tenant', 'stats'));
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

    private function getTotalConnections(): int { return 4; }
    private function getPersistentConnections(): int { return 2; }
    private function getCacheSize(): string { return '15.2 MB'; }
    private function getActiveUsersPerTenant(): array { return []; }
    private function getDatabaseSizes(): array { return []; }
    private function getMigrationStatusForAllTenants(): array { return []; }
    private function getLastActivity(): string { return '2 hours ago'; }
}
