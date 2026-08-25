
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
- Authorization cache
- Cache invalidation helpers
- Artisan installation command
- Factory support
- Validation rule helpers

---

## Requirements

This package requires a Laravel application using Eloquent ORM.

The package uses modern Laravel features such as PHP attributes, so make sure your Laravel and PHP versions support the features used by your installed package version.

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

This command creates the required authorization migrations inside:

```text
database/migrations
```

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

    'cache_ttl' => 86400,


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    'super_admin_role' => 'administrator',

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

This is useful for avoiding unnecessary database access when running Artisan commands.

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

The default value is 24 hours.

---

#### `super_admin_role`

Defines the role that should bypass permission checks.

```php
'super_admin_role' => 'administrator',
```

If the authenticated or authorized model has this role, permission checks return `true`.

Set it to `null` if you do not want to use a super administrator role.

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

---

## `auth_roles`

Stores roles.

| Column | Description |
|---|---|
| `id` | Role ID |
| `title` | Unique role name |
| `description` | Optional description |
| `hierarchy` | Role hierarchy level |
| `created_at` | Creation timestamp |
| `updated_at` | Update timestamp |

Example roles:

```text
administrator
manager
editor
author
```

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
    'hierarchy' => 30,
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

Roles have a `hierarchy` value.

For example:

```text
administrator = 100
manager       = 70
editor        = 40
author        = 20
```

A lower hierarchy value can access a higher hierarchy value according to the package's hierarchy comparison logic.

The model's minimum hierarchy can be retrieved with:

```php
$user->hierarchy();
```

The maximum hierarchy:

```php
$user->hierarchy(false, true);
```

Both values:

```php
$user->hierarchy(false, true);
```

or:

```php
$user->hierarchy(false, true);
```

returns:

```php
[
    'min' => ...,
    'max' => ...,
]
```

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

`null` means the target model does not have a hierarchy value.

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
    'hierarchy' => 40,
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
1. Is the requested permission valid?
2. Is the model a super administrator?
3. Does the model have the permission directly?
4. Does any assigned role provide the permission?
5. Return the authorization result.
```

---

# Cache Invalidation

The package automatically clears relevant model caches when authorization-related models are changed.

Examples include:

- Permission created
- Permission updated
- Permission deleted
- Role saved
- Role deleted
- Authorization model saved

The package also provides cache helper methods for relationship/pivot changes.

For custom direct manipulation of authorization pivot relationships, make sure the relevant authorization cache is invalidated.

For example, when changing role permissions directly:

```php
$role->permissions()->sync($permissionIds);
```

you should ensure affected authorization model caches are cleared appropriately.

For package-level integrations, the methods available in `AuthorizationCache` can be used for this purpose.

---

# Artisan Command

Install authorization migrations:

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

Existing migration files are not overwritten.

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

Authorization data will then be resolved directly without using the package cache.

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

with hierarchy values such as:

```text
administrator = 100
manager       = 70
editor        = 50
author        = 30
viewer        = 10
```

The exact hierarchy values are application-dependent.

---

#  Summary

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



# License

This package is open-source software.

Add your project's license information here.

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
