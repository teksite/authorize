<?php

namespace Teksite\Authorize;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Teksite\Authorize\Console\AuthInstall;
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
        $this->bootCommands();
    }

    public function bootGates(): void
    {
        if(!config('auth.boot_gates', true)){
            return;
        }
        if ($this->app->runningInConsole()) return;

        if (!Schema::hasTable('auth_permissions')
            || !Schema::hasTable('auth_roles')
            || !Schema::hasTable('auth_roles')
        ) return;


        if (!cache()->has('allPermissionsGates')) cache()->forever('allPermissionsGates', Permission::query()->select(['title', 'id'])->pluck('title' ,'id')->toArray());

        $permissions = Permission::query()->select(['title', 'id'])->pluck('title' ,'id')->toArray();

        foreach ($permissions as $id=>$title) {
            Gate::define($title, function ($user) use ($title) {
                return $user->hasPermission($title);
            });
        }
    }


    private function bootCommands(): void
    {
        $this->commands([
            AuthInstall::class
        ]);
    }
}
