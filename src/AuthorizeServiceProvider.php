<?php

namespace Teksite\Authorize;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Teksite\Authorize\Models\Permission;


class AuthorizeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->bootGates();
        $this->loadMigrations();
    }

    public function bootGates(): void
    {
        if (Schema::hasTable('auth_permissions') && Schema::hasTable('auth_roles')) {
            if (!cache()->has('allPermissionsGates')) cache()->forever('allPermissionsGates', Permission::query()->select('title', 'id')->get());
            $permissions = Permission::query()->select('title', 'id')->get();//cache()->get('allPermissionsGates');
            foreach ($permissions as $permission) {
                Gate::define($permission->title, function ($user) use ($permission) {
                    return $user->hasPermission($permission->title);
                });
            }
        }
    }

    /**
     * @return void
     */
    private function loadMigrations(): void
    {
        $migrationPath = __DIR__ . '/Migrations';

        if (is_dir($migrationPath))  $this->loadMigrationsFrom($migrationPath);
    }
}
