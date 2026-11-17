# Teksite Authorize

A flexible and model-independent authorization package for Laravel.

Teksite Authorize provides a simple way to manage:

- Permissions
- Roles
- Role hierarchy
- Direct model permissions
- Model roles
- Role-based permissions
- Laravel Gates
- Authorization caching
- Super administrator access
- Polymorphic authorization relationships

The package is designed to work with **any Eloquent model**, not only `User`.

---

## Features

- Model-independent authorization
- Polymorphic roles
- Polymorphic permissions
- Direct permissions for any Eloquent model
- Role-based permissions
- Multiple roles per model
- Multiple permissions per model
- Permission and role lookup by ID or title
- Laravel Gate integration
- Super administrator support
- Role hierarchy
- Authorization cache with automatic invalidation
- Artisan installation command
- Factory support
- Validation rule helpers

---

## Requirements

- PHP `^8.3`
- Laravel `^13.0`

The package uses modern features such as typed class constants and PHP attributes on Eloquent models and Artisan commands, so these minimum versions are required.

---

## Installation

Install the package through Composer:

```bash
composer require teksite/authorize
```

Then run the installation command:

```bash
php artisan authorize:install
```

This command:

- copies the authorization migrations into `database/migrations`
- publishes the configuration file to `config/authorize.php`

Existing files are never overwritten.

After that, run:

```bash
php artisan migrate
```

---

## Configuration

The package configuration is available at:

```text
config/authorize.php
```

Package defaults are always merged, so a published config file only needs to contain the keys you want to override. Keys added in newer package versions keep working without re-publishing.

Example:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Boot Gates
    |--------------------------------------------------------------------------
    */

    'boot_gates' => true,

    'boot_gates_in_console' => false,


    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache_enabled' => true,

    'cache_ttl' => 86400, // 24 hours

    'flush_on_save' => true,


    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */

    'detach_on_delete' => true,


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    'super_admin_role' => 'administrator',


    /*
    |--------------------------------------------------------------------------
    | Observers
    |--------------------------------------------------------------------------
    */

    'observers' => [
        'permission' => \Teksite\Authorize\Observers\PermissionObserver::class,
        'role'       => \Teksite\Authorize\Observers\RoleObserver::class,
    ],

];
```

### Configuration options

#### `boot_gates`

Determines whether permissions should be registered as Laravel Gates.

```php
'boot_gates' => true,
```

Set to `false` if you do not want the package to register Gates automatically.

---

#### `boot_gates_in_console`

Determines whether authorization Gates should be booted while Laravel is running in console mode.

Default:

```php
'boot_gates_in_console' => false,
```

This avoids unnecessary database access when running Artisan commands.

> **Important:** queue workers, scheduled tasks and Artisan commands all run in console mode. With the default value, Gate checks (`Gate::allows()`, `can()`) inside queued jobs and commands will **deny** permission abilities because the Gates are not registered. Set this option to `true` if your jobs or commands rely on permission Gates. Direct calls such as `$user->hasPermission()` are not affected.

---

#### `cache_enabled`

Enables or disables authorization caching.

```php
'cache_enabled' => true,
```

When disabled, authorization data is loaded directly from the database.

---

#### `cache_ttl`

Defines the authorization cache lifetime in seconds.

```php
'cache_ttl' => 86400,
```

The default value is 24 hours (`86400` seconds).

---

#### `flush_on_save`

Determines whether a model's authorization cache is cleared every time the model is saved.

```php
'flush_on_save' => true,
```

This is the default for backward compatibility. A model's authorization cache key does not depend on its attributes, so for models that are saved very often (for example, updating `last_login_at` on every login) you can set it to `false` to avoid needless cache operations.

Changes made through the package API (`assignRole()`, `syncPermissions()`, role/permission changes) always invalidate the cache regardless of this option.

---

#### `detach_on_delete`

Determines whether roles and direct permissions of a model are detached when the model is deleted.

```php
'detach_on_delete' => true,
```

Polymorphic pivot tables cannot use foreign keys, so without this cleanup the rows of a deleted model would remain. If a primary key is ever reused, the new model would silently inherit the old model's roles and permissions.

For models that use `SoftDeletes`, assignments are kept on soft delete and detached only on force delete.

---

#### `super_admin_role`

Defines the role that should bypass permission checks.

```php
'super_admin_role' => 'administrator',
```

If the authenticated or authorized model has this role, permission checks return `true`.

Set it to `null` if you do not want to use a super administrator role.

---

#### `observers`

Maps the `permission` and `role` models to the observers that keep the authorization cache in sync. Set an entry to `null` to disable it, or point it to your own class (preferably one that extends the package observer).

---

# Database Structure

The package creates five tables.

## `auth_permissions`

Stores permissions.

| Column | Description |
|---|---|
| `id` | Permission ID |
| `title` | Unique permission name |
| `description` | Optional description |
| `created_at` | Creation timestamp |
| `updated_at` | Update timestamp |

Example:

```text
posts.read
posts.create
posts.update
posts.delete
```

> Avoid purely numeric titles (for example `"5"`). Numeric strings are interpreted as IDs when checking permissions.

---

## `auth_roles`

Stores roles.

| Column | Description |
|---|---|
| `id` | Role ID |
| `title` | Unique role name |
| `description` | Optional description |
| `hierarchy` | Role hierarchy level (**lower number = higher authority**) |
| `created_at` | Creation timestamp |
| `updated_at` | Update timestamp |

Example roles:

```text
administrator
manager
editor
author
```

> The database default for `hierarchy` is `15`, which is a **high-authority** value. Always set `hierarchy` explicitly when creating roles.

---

## `auth_permission_role`

Connects permissions to roles.

A role can have many permissions, and a permission can belong to many roles.

---

## `auth_permission_models`

Connects permissions directly to any Eloquent model using a polymorphic relationship.

---

## `auth_role_models`

Connects roles to any Eloquent model using a polymorphic relationship.

---

# Basic Usage

## Add `HasAuthorization` to a Model

Any Eloquent model can use the authorization system.

For example:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Teksite\Authorize\Traits\HasAuthorization;

class User extends Model
{
    use HasAuthorization;
}
```

The package is not limited to `User`.

For example:

```php
class Admin extends Model
{
    use HasAuthorization;
}
```

Or:

```php
class Customer extends Model
{
    use HasAuthorization;
}
```

Or:

```php
class Employee extends Model
{
    use HasAuthorization;
}
```

As long as the model is an Eloquent model, it can use the Trait.

---

# Permissions

## Creating a Permission

```php
use Teksite\Authorize\Models\Permission;

$permission = Permission::create([
    'title' => 'posts.read',
    'description' => 'Read posts',
]);
```

---

## Finding a Permission

By ID:

```php
$permission = Permission::find(1);
```

By title:

```php
$permission = Permission::where('title', 'posts.read')->first();
```

---

# Roles

## Creating a Role

```php
use Teksite\Authorize\Models\Role;

$role = Role::create([
    'title' => 'editor',
    'description' => 'Post editor',
    'hierarchy' => 50,
]);
```

---

## Assigning Permissions to a Role

```php
$role->permissions()->sync([
    $permission->id,
]);
```

Multiple permissions can be assigned:

```php
$role->permissions()->sync([
    $readPermission->id,
    $createPermission->id,
    $updatePermission->id,
]);
```

Changes made through the `permissions()` relationship (`attach`, `sync`, `detach` with IDs) automatically invalidate the authorization cache. See [Cache Invalidation](#cache-invalidation).

---

# Assigning Roles to a Model

```php
$user->assignRole('editor');
```

The role can also be specified by ID:

```php
$user->assignRole(1);
```

Or by Role model:

```php
$user->assignRole($role);
```

Multiple roles:

```php
$user->assignRole([
    'editor',
    'author',
]);
```

By default, `assignRole()` replaces the existing roles.

To keep existing roles:

```php
$user->assignRole('editor', false);
```

> **Warning:** unknown titles and IDs are silently ignored. With the default replace behavior, `assignRole('edtior')` (typo) results in an empty list and **removes all existing roles** of the model. The same applies to `syncPermissions()`.

---

# Assigning Direct Permissions

Permissions can be assigned directly to a model without using a role.

```php
$user->syncPermissions('posts.delete');
```

By ID:

```php
$user->syncPermissions(5);
```

Using a Permission model:

```php
$user->syncPermissions($permission);
```

Multiple permissions:

```php
$user->syncPermissions([
    'posts.read',
    'posts.create',
    'posts.update',
]);
```

By default, existing direct permissions are replaced.

To keep existing permissions:

```php
$user->syncPermissions([
    'posts.read',
    'posts.create',
], false);
```

---

# Matching Rules

`hasRole()` and `hasPermission()` accept an ID, a title, a model instance, or an array of those.

- Numeric strings (`"5"`) are treated as IDs.
- Titles are matched **exactly and case-sensitively** (`'Editor'` does not match `'editor'`), regardless of the database collation.
- By default, **any** requested item is enough.
- With `$any = false`, **every** requested item must be owned. An item that does not exist (for example a typo) counts as not owned, so the check fails.

---

# Checking Roles

Check whether a model has a role:

```php
$user->hasRole('editor');
```

By ID:

```php
$user->hasRole(1);
```

Using a Role model:

```php
$user->hasRole($role);
```

Multiple roles:

```php
$user->hasRole([
    'editor',
    'author',
]);
```

By default, `hasRole()` checks whether **any** requested role exists.

To require all roles:

```php
$user->hasRole([
    'editor',
    'author',
], false);
```

---

# Checking Permissions

Check a permission:

```php
$user->hasPermission('posts.read');
```

By ID:

```php
$user->hasPermission(1);
```

Using a Permission model:

```php
$user->hasPermission($permission);
```

Multiple permissions:

```php
$user->hasPermission([
    'posts.read',
    'posts.update',
]);
```

By default, `hasPermission()` checks whether **any** requested permission exists.

To require all permissions:

```php
$user->hasPermission([
    'posts.read',
    'posts.update',
], false);
```

---

# Permission Sources

A model can receive permissions from two sources:

1. Direct permissions
2. Permissions inherited from roles

For example:

```text
User
 ├── Direct Permissions
 │   ├── posts.delete
 │   └── users.read
 │
 └── Roles
     └── editor
         ├── posts.read
         ├── posts.create
         └── posts.update
```

`hasPermission()` considers both sources.

---

# Getting Permissions

## Get All Permissions

By default, only permission IDs are returned:

```php
$user->getAllPermissions();
```

Example:

```php
[
    1,
    2,
    3,
]
```

To get permission titles:

```php
$user->getAllPermissions(false);
```

Example:

```php
[
    1 => 'posts.read',
    2 => 'posts.update',
    3 => 'posts.delete',
]
```

---

## Get Direct Permissions

This only returns permissions directly assigned to the model.

```php
$user->getDirectPermissions();
```

Example:

```php
[
    1 => 'posts.delete',
    2 => 'users.read',
]
```

Role permissions are not included.

---

## Get Permissions Through Roles

```php
$user->getPermissionsByRoles();
```

Example:

```php
[
    'editor' => [
        1 => 'posts.read',
        2 => 'posts.create',
        3 => 'posts.update',
    ],
]
```

---

# Getting Roles

Get role IDs:

```php
$user->getDirectRoles(true);
```

Example:

```php
[
    1,
    2,
]
```

Get roles with their titles:

```php
$user->getDirectRoles();
```

Example:

```php
[
    1 => 'editor',
    2 => 'author',
]
```

---

# Super Administrator

The package supports a configurable super administrator role.

Configuration:

```php
'super_admin_role' => 'administrator',
```

If a model has this role:

```php
$user->hasRole('administrator');
```

then:

```php
$user->hasPermission('anything');
```

will return:

```php
true
```

You can also check it directly:

```php
$user->isSuperAdmin();
```

The bypass also applies to Laravel Gates, but only for abilities that are registered permissions.

---

# Laravel Gates

The package automatically registers every permission as a Laravel Gate.

For example, if the database contains:

```text
posts.read
posts.create
posts.update
posts.delete
```

you can use:

```php
Gate::allows('posts.read');
```

Or:

```php
Gate::authorize('posts.update');
```

In Blade:

```blade
@can('posts.delete')
    <button>Delete</button>
@endcan
```

The Gate internally uses the model's:

```php
hasPermission()
```

method.

Notes:

- The list of permission names is cached (see `cache_ttl`) and is refreshed automatically when a permission is created, renamed or deleted.
- Gates are registered when the application boots. In long-running workers (for example Laravel Octane), restart the workers after adding new permissions.
- Gates are not booted in console mode by default. See [`boot_gates_in_console`](#boot_gates_in_console).

---

# Route Authorization

Because permissions are registered as Laravel Gates, Laravel's normal authorization features can be used.

Example:

```php
Route::get('/posts', function () {
    //
})->middleware('can:posts.read');
```

---

# Role Hierarchy

Roles have a `hierarchy` value. **A lower number means higher authority.**

For example:

```text
administrator = 10
manager       = 30
editor        = 50
author        = 70
viewer        = 90
```

A model can access (manage) another model or role only when its own hierarchy number is **strictly lower** than the target's. Equal values are not enough.

The model's strongest (minimum) hierarchy value can be retrieved with:

```php
$user->hierarchy();
```

The weakest (maximum) value:

```php
$user->hierarchy(false, true);
```

Both values:

```php
$user->hierarchy(true, true);
```

returns:

```php
[
    'min' => ...,
    'max' => ...,
]
```

`null` is returned when the model has no roles.

When a model has several roles, the minimum value (the strongest role) is used for comparisons.

---

# Comparing Model Hierarchy

A model can be compared against another authorization model:

```php
$user->canAccessModelByHierarchy($anotherUser);
```

Possible results:

```php
true
```

```php
false
```

or:

```php
null
```

`false` is also returned when the current model has no roles. `null` means the target model does not have a hierarchy value.

The target model must use:

```php
HasAuthorization
```

---

# Comparing Against a Role

A model can also be compared against a Role:

```php
$user->canAccessRoleByHierarchy('manager');
```

By ID:

```php
$user->canAccessRoleByHierarchy(2);
```

Using a Role model:

```php
$user->canAccessRoleByHierarchy($role);
```

`null` is returned when the role does not exist.

---

# Authorization Cache

Authorization data is cached by default.

The package caches:

- Model permissions
- Model roles
- Model hierarchy
- Permission Gate list

Cache can be configured using:

```php
'cache_enabled' => true,
'cache_ttl' => 86400,
```

---

# Clearing Authorization Cache

Clear all authorization caches for a model:

```php
$user->clearAuthorizationCache();
```

---

# Warming Authorization Cache

You can pre-load authorization information:

```php
$user->warmAuthorizationCache();
```

This loads:

- Permissions
- Roles
- Hierarchy

into the authorization cache.

---

# Cache Architecture

The package creates model-specific cache keys using the model's morph class and primary key.

Example:

```text
authorize:permissions:App\Models\User:1
authorize:roles:App\Models\User:1
authorize:hierarchy:App\Models\User:1
```

This prevents collisions between different model types that have the same primary key.

For example:

```text
User #1
Admin #1
```

will have different authorization cache keys.

Each cached value also carries a global *epoch* token (stored under `authorize:epoch`). Changes that affect many models at once (a role or permission is changed, or a role/permission relation changes) simply replace the epoch, which invalidates every cached value in a single cheap operation. Outdated entries are ignored and then expire by TTL. A value that was being computed while an invalidation happened can never be served afterwards.

Because of this, authorization data is always at most as stale as the last invalidation, and never longer than `cache_ttl`.

---

# Polymorphic Authorization

One of the main features of the package is that authorization is not tied to a specific model.

For example:

```php
$user->assignRole('editor');

$admin->assignRole('administrator');

$customer->syncPermissions('orders.read');

$employee->syncPermissions([
    'reports.read',
    'reports.create',
]);
```

All of these models can use the same authorization system.

This is achieved through Laravel polymorphic relationships.

---

# Model Relationships

Models using `HasAuthorization` receive:

```php
$user->roles();
```

and:

```php
$user->permissions();
```

Both relationships are polymorphic.

---

# Role Relationships

A Role has many permissions:

```php
$role->permissions;
```

A Role can be assigned to many models through the polymorphic relation.

The pivot of the role/permission relation is the `Teksite\Authorize\Models\PermissionRole` model.

---

# Permission Relationships

A Permission belongs to many roles:

```php
$permission->roles;
```

A Permission can also be directly assigned to many models.

---

# Validation Rules

Both `Permission` and `Role` provide suggested validation rules.

## Permission

Create:

```php
Permission::rules('create');
```

Update:

```php
Permission::rules('update', $permission->id);
```

---

## Role

Create:

```php
Role::rules('create');
```

Update:

```php
Role::rules('update', $role->id);
```

The Role rules include validation for permissions and hierarchy.

---

# Factories

The package provides factories for Permission and Role.

Permission factory:

```php
Permission::factory()->create();
```

Multiple permissions:

```php
Permission::factory()->count(10)->create();
```

Role factory:

```php
Role::factory()->create();
```

Multiple roles:

```php
Role::factory()->count(5)->create();
```

---

# Example

A complete example:

```php
use App\Models\User;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;

$read = Permission::create([
    'title' => 'posts.read',
]);

$create = Permission::create([
    'title' => 'posts.create',
]);

$update = Permission::create([
    'title' => 'posts.update',
]);

$editor = Role::create([
    'title' => 'editor',
    'description' => 'Can manage posts',
    'hierarchy' => 50,
]);

$editor->permissions()->sync([
    $read->id,
    $create->id,
    $update->id,
]);

$user = User::find(1);

$user->assignRole($editor);

$user->hasRole('editor');

$user->hasPermission('posts.read');

$user->getAllPermissions();
```

---

# Direct Permission Example

Roles are not mandatory.

A model can receive permissions directly:

```php
$user->syncPermissions([
    'posts.read',
    'posts.update',
]);
```

Then:

```php
$user->hasPermission('posts.read');
```

returns:

```php
true
```

---

# Authorization Flow

The authorization flow can be summarized as:

```text
Model
  |
  +-- Direct Permissions
  |
  +-- Roles
        |
        +-- Permissions
```

When checking:

```php
$model->hasPermission('posts.update');
```

the package checks:

```text
1. Do the requested identifiers (ID, title, model) resolve to a valid list?
2. Does the model own the permission(s), directly or through a role?
   (any / all, depending on the $any argument)
3. If not, is the model a super administrator?
4. Return the authorization result.
```

---

# Cache Invalidation

The package clears the relevant caches automatically in these situations:

| Change | What happens |
|---|---|
| `assignRole()` / `syncPermissions()` | That model's cache is cleared |
| A model using `HasAuthorization` is saved | That model's cache is cleared (see `flush_on_save`) |
| A model using `HasAuthorization` is deleted | Its cache is cleared and its roles/permissions are detached (see `detach_on_delete`) |
| A Role's `title` or `hierarchy` changes, or a Role is deleted | All authorization caches are invalidated |
| A Permission's `title` changes, or a Permission is deleted | All authorization caches and the Gate list are invalidated |
| A Permission is created | The Gate list is refreshed |
| `attach()`, `sync()` or `detach($ids)` on `$role->permissions()` or `$permission->roles()` | All authorization caches are invalidated |

Changing only the `description` of a role or permission does not clear any cache.

The following changes bypass Eloquent events, so they are **not** detected automatically:

- `$role->permissions()->detach()` called without arguments
- Changes made with the query builder or raw SQL
- Rows removed by database-level cascades
- Calling `attach()` / `detach()` directly on `$model->roles()` or `$model->permissions()`

After such changes, clear the cache manually:

```php
use Teksite\Authorize\Support\AuthorizationCache;

// a role or permission changed
AuthorizationCache::forgetRole();
AuthorizationCache::forgetPermission();

// a single model changed
$user->clearAuthorizationCache();
```

---

# Artisan Command

Install authorization migrations and the configuration file:

```bash
php artisan authorize:install
```

The command creates:

```text
create_permissions_table.php
create_roles_table.php
```

inside:

```text
database/migrations
```

and publishes:

```text
config/authorize.php
```

Existing files are not overwritten.

---

# Disabling Gate Booting

If you do not want automatic Laravel Gate registration:

```php
'boot_gates' => false,
```

The model authorization methods such as:

```php
hasPermission()
hasRole()
```

remain available.

---

# Disabling Cache

To disable authorization caching:

```php
'cache_enabled' => false,
```

Authorization data will then be resolved directly from the database without using the package cache.

---

# Recommended Permission Naming

It is recommended to use a consistent permission naming convention.

For example:

```text
users.read
users.create
users.update
users.delete

posts.read
posts.create
posts.update
posts.delete

orders.read
orders.create
orders.update
orders.delete
```

This makes permissions easier to organize and use with Laravel Gates.

---

# Recommended Role Structure

A typical application could use:

```text
administrator
manager
editor
author
viewer
```

with hierarchy values such as (lower number = higher authority):

```text
administrator = 10
manager       = 30
editor        = 50
author        = 70
viewer        = 90
```

The exact hierarchy values are application-dependent.

---

# Summary

## Model Methods

```php
assignRole()
syncPermissions()

hasRole()
hasPermission()

getAllPermissions()
getDirectPermissions()
getPermissionsByRoles()

getDirectRoles()

isSuperAdmin()

hierarchy()

canAccessModelByHierarchy()
canAccessRoleByHierarchy()

clearAuthorizationCache()
warmAuthorizationCache()
```

---

## Model Relationships

```php
roles()
permissions()
```

---

## Permission Methods

```php
Permission::rules()
Permission::factory()
$permission->roles()
```

---

## Role Methods

```php
Role::rules()
Role::factory()
$role->permissions()
```

---

# License

This package is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

# Contributing

Contributions, bug reports, feature requests, and pull requests are welcome.

Before submitting a pull request, make sure that:

- The code follows Laravel conventions.
- Existing functionality is not broken.
- New functionality is covered by tests where appropriate.
- Authorization cache behavior is considered for authorization-related changes.

---

# Security

If you discover a security vulnerability, please report it privately to the package maintainer instead of opening a public issue.

---

# Credits

Developed by Teksite.

Package:

```text
teksite/authorize
```

A model-independent authorization system for Laravel.


# Contact
- [teksite.net](https://teksite.net)
- [laratek.net](https://laratek.net)
- [laratek.ir](https://laratek.ir)
