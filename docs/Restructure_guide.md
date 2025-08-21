# Restructure Guide — Add Livewire full-page components

Purpose

- Provide a minimal, repeatable plan to restructure the package so it cleanly hosts Livewire full-page components (Livewire v3) and publishes views/assets for host apps.
- Keep the package modular so consumers can opt into Livewire UI while the core tenancy logic remains framework-agnostic.

Checklist (what this guide covers)

- Proposed file/folder layout for Livewire components and views
- Service Provider changes: view namespace, route registration, command registration
- Publishing strategy for views, assets, and stubs
- Livewire v3-specific notes and component conventions
- Tenancy & context considerations (per-tenant rendering, cache, guard policies)
- Tests and CI suggestions
- Backwards compatibility and incremental migration plan

## High-level plan

1. Add a `src/Http/Livewire` namespace for components and `resources/views/livewire` for their Blade templates.
2. Register a view namespace and publishable resources in the package Service Provider.
3. Offer a small set of example full-page Livewire components (index/show/edit) to use as patterns.
4. Wire optional route registration (under a package `routes/admin.php`) that the host app can include or the package registers conditionally.
5. Provide `artisan` stub-generators or `make:` guidance for maintainers to add new components consistently.
6. Add tests that mount components and run through tenant-context behaviors.

## Suggested folder structure (package root)

- src/
  - Http/
    - Controllers/
    - Livewire/                # new: Livewire components namespace
      - Admin/
        - TenantsIndex.php     # Livewire component class
        - TenantShow.php
        - TenantEdit.php
- resources/
  - views/
    - livewire/
      - admin/
        - tenants-index.blade.php
        - tenant-show.blade.php
        - tenant-edit.blade.php
- routes/
  - admin.php                  # new: optional admin route definitions
- docs/
  - Restructure_guide.md

Notes
- The package already uses PSR-4 autoloading (`ArtflowStudio\Tenancy\` -> `src/`). Add the `src/Http/Livewire` folder and namespace to follow existing conventions.
- Blade templates live inside `resources/views/livewire/admin/...`. The package should `loadViewsFrom(__DIR__.'/../../resources/views', 'af-tenancy')` and publish these views so host apps can override them via `resource_path('views/vendor/af-tenancy')`.

Practical alignment with current package layout
- The package currently contains `src/Http/Controllers` and `resources/views/admin/tenants`. Place Livewire views under `resources/views/livewire/admin` rather than mixing them into `views/admin` to keep the admin UI separate and namespaced.
- Ensure `routes/af-tenancy.php` remains intact; create `routes/admin.php` for optional Livewire admin routes and publish it with a tag (e.g., `af-tenancy-routes`).

## Service Provider changes

1. Register view namespace and publishable resources in the package's service provider (e.g., `PackageServiceProvider`):

- load views from the package: `$this->loadViewsFrom(__DIR__.'/../../resources/views', 'af-tenancy');`
- publish them so projects can override: `$this->publishes([__DIR__.'/../../resources/views' => resource_path('views/vendor/af-tenancy')], 'af-tenancy-views');`

2. Register routes conditionally (admin routes) in `boot()` by checking a config key `config('artflow-tenancy.enable_admin_ui', false)` or via an opt-in publish step. Keep routes namespaced and prefixed (e.g., `tenancy.admin`).

3. Register Livewire components (Livewire v3 supports auto-discovery but explicit registration is still useful):

- If you want explicit registration, in `boot()` call Livewire::component('af-tenancy::admin.tenants-index', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex::class);

## Routing & URL scaffolding

- Create `routes/admin.php` and publish it. Example route block:

```php
Route::middleware(['web', 'auth', 'can:manage-tenants'])
    ->prefix(config('artflow-tenancy.admin.prefix', 'admin/tenants'))
    ->name('tenancy.admin.')
    ->group(function () {
        Route::get('/', \App\Http\Livewire\TenantsIndex::class)->name('index');
        Route::get('/{tenant}', \App\Http\Livewire\TenantShow::class)->name('show');
    });
```

- Keep package routes opt-in or gated behind config so consumers can choose to use them or register their own.

## Livewire v3 notes & component conventions

- Prefer full-page components for admin flows; each component should have a small `render()` that returns the package view:

```php
public function render()
{
    return view('af-tenancy::livewire.admin.tenants-index');
}
```

- Keep component logic thin: delegate heavy work to services (e.g., `TenantService`) to keep components testable.
- Use per-tenant context (`tenant->run(...)` or stancl tenancy helpers) when performing DB work.
- Use `mount()` or route-parameters for initial bootstrapping data.

## Tenancy considerations

- Per-tenant caching: when writing cache keys inside components, namespace by tenant id: `tenant:{$tenant->id}:key`.
- DB connections: in components that trigger migrations/seeding, make sure commands run in tenant context and long-running operations are queued (don't block Livewire requests).
- Authorization: add policy checks (`can:manage-tenants`) before rendering admin components.

## Publishing & installation UX

- Update the package `af-tenancy:install` flow to optionally publish views and routes and to add recommended env variables related to Livewire usage (if any).
- Provide a `--with-ui` flag on the installer to publish the admin UI skeleton.
- Document the exact `vendor:publish` tag names: `af-tenancy-views`, `af-tenancy-routes`, `af-tenancy-stubs`.

## Stubs & scaffolding

- Provide stubs for a Livewire component (class + blade) in `stubs/livewire` so maintainers can copy them into the host app.
- Optionally provide an artisan command (within the package) to scaffold a new Livewire admin component pre-wired with tenancy context.

## Testing guidance

- Add feature tests that mount Livewire components using Laravel's Livewire testing helpers.
- Use tenant factories and `tenant->run()` in tests to assert tenant-scoped behavior.
- Add a CI stage that runs `phpunit` and includes a test that verifies published view files exist after the installer command runs.

## Backwards compatibility & incremental rollout

- Don't enable admin UI by default. Add config flag `artflow-tenancy.enable_admin_ui` (default `false`).
- Provide a clear publish & enable flow in the README:
  1. `php artisan af-tenancy:install --with-ui`
  2. `php artisan vendor:publish --tag=af-tenancy-views`
  3. Add route include `require base_path('routes/tenancy-admin.php');` to `routes/web.php` or let the package register them when `enable_admin_ui` is true.

## Observability, performance & long-running tasks

- Heavy operations (migrations, seed, backup) must be queued. Livewire actions should dispatch Jobs and show progress via polling or notifications.
- Use Redis for tenant cached lookup and Livewire `broadcast` or `notifications` for async job updates.
- Add metrics instrumentation (timings, failures) to TenantService and commands used by UI.

## Example minimal Livewire component (file plan)

- `src/Http/Livewire/Admin/TenantsIndex.php`
  - Dependencies: inject `TenantService`
  - Methods: mount(), loadMore(), openTenant($id), render()

- `resources/views/livewire/admin/tenants-index.blade.php`
  - Uses `@livewireStyles` / `@livewireScripts` will be handled by host app layout; the package view is partial inside the admin layout.

## Security, Policies & Guards

- Use Laravel policies (`TenantPolicy`) to control CRUD actions.
- Ensure components use `authorize` or `can` checks in `mount()` where possible.

## CI / Tests to add

- Unit tests for component methods (data formatting, basic validation)
- Feature tests mounting the component and asserting renders and interactions
- Smoke test that runs `af-tenancy:install --with-ui` and checks expected files are published

## Example commands to scaffold (developer instructions)

- Helper developer commands to create package components:
  - `php artisan make:livewire "ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex"` (or create files manually inside package)
  - Publish views and routes via `vendor:publish` tags defined in the service provider

## Migration & release notes for package maintainers

- Release as minor bump and document the opt-in nature of the admin UI.
- Provide migration notes: "This change only adds files and a config flag; it does not modify DB schema."

## Appendix: Quick checklist for implementers

- [ ] Create `src/Http/Livewire` and sample components
- [ ] Create `resources/views/livewire/admin` templates
- [ ] Update package Service Provider: load/publish views, register routes optionally
- [ ] Add route file `routes/admin.php` and publish tag
- [ ] Add `--with-ui` flag to `af-tenancy:install` and wire vendor:publish calls
- [ ] Add tests for Livewire components and installer
- [ ] Document enabling steps in README & docs

---

If you want, I can now:
- Scaffold the component files and the Service Provider changes in the package (I will create the minimal files and update provider to register/publish views and routes), or
- Generate a small example Livewire component and Blade view under the package so you can iterate visually.

Which action should I take next? If you want me to scaffold, confirm and I'll apply the file changes.
