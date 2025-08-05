# TODO & Improvements for AF-MultiTenancy

## Refactoring & Code Improvements

### 1. `src\Http\Middleware\HomepageRedirectMiddleware.php`
- Extract tenant resolution logic into a private method (DRY).
- Remove duplicate config and domain checks.
- Use `View::exists` after registering the namespace for consistency.
- Add more granular error handling (e.g., fire an event if tenant not found).
- Consider using stancl/tenancy events/hooks for tenant resolution.
- Add support for custom error/maintenance pages if tenant is inactive or not found.

### 2. `src\Services\TenantService.php`
- Centralize all tenant creation, update, and deletion logic.
- Add events for tenant lifecycle (created, updated, deleted, homepage toggled).

#### Performance Improvements
- Use queue jobs for heavy operations (tenant creation, deletion, migrations, seeding) to avoid blocking requests.
- Add caching for tenant lookups and system stats (e.g., Redis).
- Use chunked/batched processing for `migrateAllTenants` and `seedAllTenants` to reduce memory usage.
- Optimize DB queries (e.g., eager load domains, avoid N+1).
- Use async database creation/deletion if supported by the DB driver.
- Add retry logic for transient DB errors (creation, deletion, migration).

#### Feature Additions
- Add per-tenant backup/restore methods (DB dump/restore).
- Add tenant cloning/duplication (copy structure and optionally data).
- Add tenant export/import (JSON/CSV for settings, users, etc.).
- Add per-tenant notification system (email, in-app).
- Add tenant-level audit logging (track changes, logins, actions).
- Add support for scheduled tasks per tenant (e.g., nightly jobs).
- Add hooks/events for all major actions (created, deleted, migrated, homepage created/removed).
- Add support for soft-deleting tenants (with restore option).
- Add API for tenant resource usage (disk, DB size, user count).
- Add validation and sanitization for all input parameters.
- Add support for multi-database drivers (PostgreSQL, SQLite, etc.).
- Add per-tenant config overrides (feature flags, limits).

### 3. `src\Models\Tenant.php`
- Add more scopes (e.g., `scopeActive`, `scopeWithHomepage`).
- Add helper methods for status, homepage, and domain management.

### 4. `src\TenancyServiceProvider.php`
- Register all custom middleware and events in a single place.
- Document all extension points for developers.

### 5. `routes\af-tenancy.php`
- Group routes by context (central, tenant, API).
- Use middleware groups for clarity.

### 6. `src\Http\Middleware\ApiAuthMiddleware.php`
- Allow for multiple API keys (array or DB-driven for per-tenant API keys).
- Support for Bearer token/JWT authentication (not just X-API-Key).
- Add logging for failed authentication attempts.
- Add rate limiting/throttling for API endpoints.
- Make error messages translatable.

### 7. `src\Http\Middleware\TenantMiddleware.php`
- Allow for custom error views (not just `tenancy::errors.*`).
- Add events for tenant status changes (blocked, suspended, etc.).
- Make status codes and error messages configurable.
- Optionally allow for “grace period” before blocking/suspending.
- Add logging for status changes and access denials.

### 8. `src\Http\Middleware\SmartDomainResolver.php`
- Refactor to use stancl/tenancy’s built-in domain resolution where possible.
- Add caching for domain-to-tenant lookups.
- Allow for custom hooks/events after tenant is resolved.
- Improve error handling for domain resolution failures.
- Add logging for domain resolution attempts and failures.

### 9. `src\Http\Middleware\CentralDomainMiddleware.php`
- Allow for dynamic central domain list (from DB or config).
- Add events for central domain access (for analytics/auditing).
- Add logging for denied access attempts.
- Make error messages and status codes configurable.

---

## Feature Ideas (Basic & Advanced)

### Basic Features
- Per-tenant custom error/maintenance pages.
- Tenant impersonation for admins.
- Per-tenant theming (CSS, assets).
- Tenant activity logging (logins, homepage visits).
- Tenant status pages (inactive, suspended, etc.).
- Multi-domain per tenant (add UI/API for management).
- Per-tenant API rate limiting.
- Tenant onboarding wizard (guided setup after creation).
- Centralized notifications/banners for tenants.

### Advanced Features
- Tenant backup/restore (planned, see roadmap).
- Tenant templates/presets (planned).
- Advanced analytics (usage, performance, etc.).
- Per-tenant file storage isolation.
- Tenant resource quotas/limits.
- Bulk tenant operations (update, delete, migrate).
- Integration with Laravel Sanctum/Passport for multi-tenant API auth.
- Scheduled tenant tasks (e.g., nightly jobs per tenant).

---

## stancl/tenancy Usage Review
- Using stancl/tenancy correctly: tenant context, domain model, DB isolation, middleware stack.
- Improvements:
  - Use more stancl/tenancy events and built-in middleware for tenant context.
  - Rely on stancl/tenancy’s central domain config for central/tenant split.
  - Use stancl/tenancy’s hooks for custom logic (e.g., homepage, status).

---

## Docs & Testing
- Ensure all new features and refactors are documented in `/docs`.
- Add feature tests for homepage, tenant status, and error/maintenance pages.

---

## Command Improvements & Features

### 11. `src\Commands\InstallTenancyCommand.php`
- Add support for non-interactive/CI installation (all options via flags).
- Add rollback/cleanup logic if installation fails midway.
- Add pre-installation checks (PHP version, DB connection, required extensions).
- Add post-installation health check and summary.
- Allow custom stub/template publishing (user-defined stubs).
- Add option to skip certain steps (e.g., migrations, cache clear).
- Add logging for all steps and errors.
- Add support for multi-database drivers (PostgreSQL, SQLite).
- Add progress bar for long-running steps.
- Add dry-run mode (show what would be done, but don’t execute).

### 12. `src\Commands\TenantCommand.php`
- Add batch actions (activate/deactivate/delete multiple tenants).
- Add export/import for tenant data (JSON/CSV).
- Add tenant impersonation command for admins.
- Add more granular status management (suspend, archive, etc.).
- Add command to show tenant resource usage.
- Add command to backup/restore tenant data.
- Add command to clone/duplicate tenants.
- Add command to soft-delete/restore tenants.
- Add command to send notifications to tenants.

### 13. `src\Commands\CreateTestTenantsCommand.php`
- Add support for parallel/queued creation for large numbers.
- Add option to auto-delete test tenants after tests.
- Add option to seed test data with custom classes.
- Add reporting on test tenant creation (summary, errors).
- Add support for custom domain patterns.

### 14. `src\Commands\ComprehensiveTenancyTestCommand.php`
- Add more test scenarios (edge cases, error handling).
- Add performance benchmarks to test output.
- Add reporting (HTML/JSON output).
- Add option to run only specific tests.
- Add cleanup confirmation prompt.

### 15. `src\Commands\TestPerformanceCommand.php`
- Add support for distributed/load testing (across multiple servers).
- Add more detailed metrics (CPU, DB, network).
- Add option to save results to file or DB.
- Add support for custom test scenarios.
- Add reporting dashboard (optional).

### 16. `src\Commands\HealthCheckCommand.php`
- Add more health checks (cache, queue, mail, etc.).
- Add option for continuous health monitoring (watch mode).
- Add notification on health check failures (email, Slack, etc.).
- Add support for output in multiple formats (JSON, HTML).
- Add summary of recommended actions if issues found.

### 17. `database\migrations\9999_create_tenants_and_domains_tables.php`
#### Performance Improvements
- Add composite indexes for frequent queries (e.g., `status, last_accessed_at` together).
- Add index on `database` column for faster lookups.
- Consider partial indexes (if supported by DB) for common status values.
- Use `bigIncrements` for domain `id` for future-proofing (if many domains expected).
- Add unique constraint on `tenant_id, domain` in `domains` table for multi-domain safety.

#### Feature Additions
- Add soft deletes (`$table->softDeletes()`) for tenants and domains.
- Add `created_by`/`updated_by` columns for audit trails.
- Add `type` or `plan` column to tenants for SaaS plans/roles.
- Add `metadata` JSON column for extensibility.
- Add `expires_at` or `suspended_at` columns for lifecycle management.
- Add `is_primary` boolean to domains for primary domain tracking.
- Add `verified_at` column to domains for domain verification features.
- Add triggers or constraints for cascading deletes/updates if needed.
- Add support for multi-DB drivers (e.g., UUID columns for PostgreSQL).

---
