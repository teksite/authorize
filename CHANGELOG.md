# Changelog

All notable changes to `teksite/authorize` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.4.1] - 2026-11-17

### Fixed

- `composer.json` allowed `laravel/framework: ^12.0|^13.0` in 2.4.0, although the package requires Laravel 13
  (it uses the `#[Fillable]`, `#[Signature]` and `#[Description]` attributes, which are new in Laravel 13).
  The constraint is now `^13.0`. **Laravel 12 users must stay on the `1.x` series.**
- `RoleObserver` invalidated the whole authorization cache every time a role was created and on every role update,
  including description-only edits. It now invalidates only when a role's `title` or `hierarchy` changes, or when
  a role is deleted.

### Changed

- `composer.json`: removed `minimum-stability: dev` and `prefer-stable`, added `"type": "library"`, and removed the
  leading backslash from the provider class in `extra.laravel.providers`.
- README: the Cache Invalidation examples now match the signatures of `AuthorizationCache::forgetRole()` and
  `AuthorizationCache::forgetPermission()` (no arguments).
- `composer.lock` refreshed.

## [2.4.0] - 2026-11-15

### Security

- **`hasPermission()` and `hasRole()` failed open in "all" mode.** With `$any = false`, identifiers that do not exist
  (for example a typo) were dropped before counting, so `hasPermission(['posts.read', 'typo'], false)` returned `true`
  for a model that only owned `posts.read`. A requested item that is not owned, including one that does not exist,
  now makes the check fail.
- **Role/permission relation changes did not invalidate the cache.** `attach()`, `sync()` and `detach($ids)` on
  `$role->permissions()` or `$permission->roles()` fire no events, so revoked permissions stayed effective until
  `cache_ttl` expired (24 hours in 2.3.0, 30 days in 2.3.1). These changes now invalidate the cache through the new
  `PermissionRole` pivot model.
- **Stale values could be cached for the whole TTL.** A value computed while an invalidation happened could be written
  back afterwards. Cached values now carry a global epoch token and a value computed under an older epoch is never served.
- **Observers were not registered for users with a published config created before `observers` existed.**
  The service provider merged only the published `config/authorize.php` when it existed, so defaults added in newer
  versions (such as `observers`, introduced in 2.3.1) were missing and no cache invalidation happened at all.
  Package defaults are now always merged and a published config only overrides keys.
- **Orphaned pivot rows.** Polymorphic pivot tables cannot use foreign keys, so deleting a model left its
  `auth_role_models` and `auth_permission_models` rows behind. If a primary key was reused, the new model inherited
  the old roles and permissions. Rows are now detached when a model is deleted (on force delete for models using
  `SoftDeletes`). Controlled by the new `detach_on_delete` option.
- `AuthorizationCache::forgetMorphModel()` instantiated a class from the `model_type` value stored in the database.
  It no longer instantiates anything.

### Added

- `Teksite\Authorize\Models\PermissionRole` pivot model. It invalidates the cache when permissions are attached to or
  detached from roles.
- Config option `flush_on_save` (default `true`): set it to `false` to stop clearing a model's authorization cache on
  every `save()`.
- Config option `detach_on_delete` (default `true`).
- `AuthorizationCache::ttl()`, `AuthorizationCache::bump()`, `AuthorizationCache::remember()` and
  `AuthorizationCache::DEFAULT_TTL`.
- Regression tests for the issues above, plus tests asserting that warm `hasPermission()` / `hasRole()` checks and
  warm Gate booting run no database queries.

### Changed

- **Performance:** `hasPermission()`, `hasRole()` and `isSuperAdmin()` no longer run database queries when the cache is
  warm. Identifiers are matched against the cached `[id => title]` lists.
- **Performance:** invalidating roles, permissions or their relations is now O(1) (a single epoch change). Previously
  every holder of the role/permission was loaded from the database and its cache keys were deleted one by one.
- **Performance:** `GateServiceProvider` no longer checks the five package tables on every request. Tables are
  verified only on a cache miss.
- **Performance:** a model's three cache keys are removed with one `deleteMultiple()` call instead of three `forget()`
  calls. `getAllPermissions()` needs two plain queries on a cache miss instead of loading and hydrating relations.
- Role observer: the cache is invalidated only when `title` or `hierarchy` changes or the role is deleted. Permission
  observer: only when `title` changes or the permission is deleted. Description-only edits no longer flush anything.
- Default `cache_ttl` is `86400` (24 hours) again. It was raised to `2592000` in 2.3.1.
- `hierarchy()` stores a plain array in the cache instead of an Eloquent model, and computes min/max in PHP
  instead of mixing aggregates with relation pivot columns, which can fail with `ONLY_FULL_GROUP_BY` on MySQL.
- `GateServiceProvider::CACHE_TTL` and `PERMISSIONS_CACHE_KEY` now reference the constants of `AuthorizationCache`.
- `AuthorizationCache::forgetPermission()`, `forgetRole()` and `forgetPermissionRolePivot()` no longer take
  parameters. Callers that still pass arguments keep working, because PHP ignores extra arguments.
- `Role::permissions()` and `Permission::roles()` use `PermissionRole` as the pivot class
  (`$role->permissions->first()->pivot` is now a `PermissionRole`).
- The format of values stored in the authorization cache changed. Old entries are treated as cache misses and rebuilt
  automatically, no manual flush is needed.
- Documentation: README corrected and extended (hierarchy direction, TTL, config options, matching rules, cache
  invalidation, requirements and license). Added the `laravel acl` Composer keyword.

### Fixed

- Cache invalidation on role and permission deletion relied on pivot rows that database `ON DELETE CASCADE` had
  already removed, so nothing was flushed. Invalidation no longer depends on those rows.
- `forgetMorphModel()` and the targeted flushes crashed with morph-map aliases (`model_type = 'user'`).
- `GateServiceProvider` could cache an empty permission list after a failed database query.

### Removed

- The empty `creating`/`updating`/`saving`/`deleting` hook methods of `PermissionObserver` and `RoleObserver`.
- The unused `HasAuthorization::getAuthorizationCacheIdentifier()` method.

### Upgrade notes

- Permission and role **titles are matched exactly and case-sensitively**, regardless of database collation.
  Previously, on databases with case-insensitive collations (default MySQL), `hasRole('Administrator')` also matched a
  role titled `administrator`. Make sure `super_admin_role` matches the stored role title exactly.
- "All" mode (`$any = false`) is stricter, see Security above.
- Roles and direct permissions are detached when a model is deleted. Set `detach_on_delete` to `false` to keep the
  old behaviour.
- Changes that bypass Eloquent events are not detected automatically: `$role->permissions()->detach()` without
  arguments, query builder or raw SQL changes, database-level cascades, and `attach()`/`detach()` called directly on
  `$model->roles()` / `$model->permissions()`. Call `AuthorizationCache::forgetRole()`,
  `AuthorizationCache::forgetPermission()` or `$model->clearAuthorizationCache()` afterwards.
- **Do not install 2.4.0 on Laravel 12**, its Composer constraint was wrong. Fixed in 2.4.1.

## [2.3.1] - 2026-08-29

### Added

- `PermissionObserver` and `RoleObserver`, registered through the new `observers` config key.
- `authorize:install` now also publishes `config/authorize.php`.

### Changed

- Default `cache_ttl` raised from `86400` to `2592000` (30 days).
- `composer.json`: test classes are autoloaded only through `autoload-dev`, added keywords and `prefer-stable`.
- General cleanup and bug fixes.

## [2.3.0] - 2026-08-26

### Added

- Test suite (Orchestra Testbench).

### Changed

- Code refactoring.

## [2.2.5] - 2026-08-19

### Added

- Factories for the `Permission` and `Role` models.

### Fixed

- Role validation bug.

## [2.2.3] - 2026-06-02

### Fixed

- Assigning roles or permissions failed when an ID was numeric but not an integer (for example `"5"`).

## [2.2.2] - 2026-05-28

### Added

- Config file.

### Changed

- Optimized the `HasAuthorization` trait.

### Fixed

- Bug fixes.

## [2.1.0] - 2026-05-26

### Changed

- Refactored the `HasAuthorization` trait.
- The `boot_gates` config option decides whether Gates are loaded by the package or implemented by the application.

## [2.0.6] - 2026-05-26

### Added

- `users()` method on the `Permission` model.

## [2.0.3] - 2026-05-21

### Changed

- Code cleanup and fixes.

## [2.0.0] - 2026-04-25

### Changed

- **BREAKING:** requires Laravel 13.
- Code refactoring.

## [1.0.1] - 2025-07-07

### Fixed

- Wrong class reference in the `Permission` model (`role` changed to `Role`).

## [1.0.0] - 2025-05-05

### Added

- First release, supports Laravel 12.

[Unreleased]: https://github.com/teksite/authorize/compare/2.4.1...HEAD
[2.4.1]: https://github.com/teksite/authorize/compare/2.4.0...2.4.1
[2.4.0]: https://github.com/teksite/authorize/compare/2.3.1...2.4.0
[2.3.1]: https://github.com/teksite/authorize/compare/2.3.0...2.3.1
[2.3.0]: https://github.com/teksite/authorize/compare/2.2.5...2.3.0
[2.2.5]: https://github.com/teksite/authorize/compare/2.2.3...2.2.5
[2.2.3]: https://github.com/teksite/authorize/compare/2.2.2...2.2.3
[2.2.2]: https://github.com/teksite/authorize/compare/2.1.0...2.2.2
[2.1.0]: https://github.com/teksite/authorize/compare/2.0.6...2.1.0
[2.0.6]: https://github.com/teksite/authorize/compare/2.0.3...2.0.6
[2.0.3]: https://github.com/teksite/authorize/compare/2.0.0...2.0.3
[2.0.0]: https://github.com/teksite/authorize/compare/1.0.1...2.0.0
[1.0.1]: https://github.com/teksite/authorize/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/teksite/authorize/releases/tag/1.0.0
