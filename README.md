
# Extra laravel Package

## About
This package is developed to manage accessibility of users to parts of laravel apps .
The related model (like User) can have permissions directly or through its roles.

### Author
Sina Zangiband

### Contact
- Website: [laratek.ir](https://laratek.ir)
- Alternate Website: [teksite.net](https://teksite.net)

---

## Installation

### Step 1: Install via Composer
Run the following command in your CLI:

```bash
composer require teksite\authorize
```
### Step 2: Migration
run the migrate command 

```bash
php artisan migrate
````

### Step 3: Define gates in the AppServiceProvider
For laravel 5 or higher version, GatesServiceProvider is discovered automatically, so you don't need to do anything

#### Laravel 5.x and earlier
If you are using Laravel 5.x or earlier, register the service provider in the `config/app.php` file under the `providers` array:

```php
'providers' => [
    // Other Service Providers
    Teksite\Authorize\AuthorizeServiceProvider::class,
];
```

> **Note:** This step is not required for newer versions of Laravel (5.x and above).

---

## Use
Add trait `\Teksite\Authorize\TraitsHasAuthorization` to any model that you want to implement authorization on it such as User model.

### Using in blades
it can be used by own laravel directive for example for users with `can` directive:
```bladehtml
@can('permission')
    \\ your codes
@endcan
```
or

```bladehtml
@canany(['permission1' , permission2])
    \\ your codes
@endcanany
```
in the above code the permission is checked for the current (logged in) user if he/she has the permissions or his/her roles have the permission.

### syncing Permissions
to add/remove permissions directly to a User instances use
```php
$user->syncPermissions(permissions: $permissions , detaching:$detaching  ])
```
* $permissions : should be arrays of permissions id or Permission instances.
* $detaching : should be a boolean parameter to determine new permissions should be appended to previous permissions or overwrite them.
* return void

### assign Roles
to assign roles directly to a User instances use
```php
$user->assignRole(permissions: $roles ,detaching: $detaching  ]) :void
```
* $roles : should be arrays of roles id or Role instances.
* $detaching : should be a boolean parameter to determine new permissions should be appended to previous permissions or overwrite them.
* return void

### check having roles
to check a user has roles
```php
$user->hasRole(roles: $roles ,any : $any = true  ])
```
* $roles : should be array of roles id/title or Role instances.
* $any : should be a boolean parameter to determine if user has all roles or at least has one.
* return boolean

### check having permissions
to check a user has a permission directly or through its role

```php
$user->hasPermission(permissions: $permission])
```
* $permission : should be permission id or permission title.
* return void

### get all permissions
to have all permissions of a user instance
```php
$user->allPermissions()
```
* return collection|array


## Add Role and Permission to the app

### permission entries
```php
    protected $table = 'auth_permissions';

    protected $fillable = ['title', 'description'];
```
#### suggested rules
use `Permission::rules()` or
```
[
 'title' => 'required|string|max:255|unique:auth_permissions,title',
 'description' => 'nullable|string|max:255',
]
```
### role entries
```php
    protected $table='auth_roles';

    protected $fillable =['title', 'description' ,'hierarchy'];
```
#### suggested rules
use `Role::rules()` or
```
[
  'title'=>'required|string|max:255|unique:auth_roles,title',
  'description'=>'nullable|string|max:255',
  'permissions'=>'array|required',
  'permissions.*'=>'exists:auth_permissions,id',
  'hierarchy'=>'required','numeric',
];
```







---
Feel free to reach out if you have any questions or need assistance with this package!
