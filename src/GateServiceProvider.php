<?php

namespace Teksite\Authorize;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Teksite\Authorize\Models\Permission;


class GateServiceProvider extends ServiceProvider
{

    public const int CACHE_TTL = 2592000;

    public const string PERMISSIONS_CACHE_KEY = 'authorize:permissions:gates';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {

        if (!$this->shouldBootGates()) return;

        $permissions = $this->loadPermissions();

        if ($permissions === []) return;

        $this->registerGates($permissions);
    }

    protected function shouldBootGates(): bool
    {
        if (!config('authorize.boot_gates', true)) return false;

        if ($this->app->runningInConsole() && !config('authorize.boot_gates_in_console', false)) return false;

        return $this->validateDatabaseTables();
    }


    /**
     * Validate required database tables exist.
     */
    protected function validateDatabaseTables(): bool
    {
        $requiredTables = ['auth_permissions', 'auth_permission_models', 'auth_roles', 'auth_permission_role', 'auth_role_models'];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                Log::debug('Authorize: Table not found', ['table' => $table]);
                return false;
            }
        }
        return true;
    }

    /**
     * Load permissions with caching and error handling.
     */
    protected function loadPermissions(): array
    {
        try {

            if (!config('authorize.cache_enabled', true)) {
                return $this->fetchPermissionsFromDatabase();
            }

            return Cache::remember(
                self::PERMISSIONS_CACHE_KEY,
                config('authorize.cache_ttl', self::CACHE_TTL),
                fn() => $this->fetchPermissionsFromDatabase()
            );

        } catch (\Exception $e) {
            Log::error('Authorize: Failed to load permissions', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return [];
        }
    }

    /**
     * Fetch permissions directly from database.
     */
    protected function fetchPermissionsFromDatabase(): array
    {
        try {
            return Permission::query()->select(['title'])
                             ->pluck('title')
                             ->filter()
                             ->values()
                             ->toArray();
        } catch (\Exception $e) {
            Log::error('Authorize: Database query failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Register multiple gates efficiently.
     */
    protected function registerGates(array $permissions): void
    {
        $permissionMap = array_fill_keys($permissions, true);

        Gate::before(function ($subject, string $ability) use ($permissionMap) {

            if (!isset($permissionMap[$ability])) return null;

            $superAdminRole = config('authorize.super_admin_role');

            if ($superAdminRole && method_exists($subject, 'hasRole') && $subject->hasRole($superAdminRole)) return true;

            return null;
        });

        foreach ($permissions as $permission) {
            Gate::define($permission, function ($subject) use ($permission): bool {
                try {
                    if (!method_exists($subject, 'hasPermission')) return false;

                    return $subject->hasPermission($permission);
                } catch (\Throwable $e) {
                    Log::error('Authorize: Gate check failed.',
                        [
                            'permission' => $permission,
                            'subject'    => $subject::class,
                            'subject_id' => method_exists(
                                $subject,
                                'getAuthIdentifier'
                            )
                                ? $subject->getAuthIdentifier()
                                : null,
                            'error'      => $e->getMessage(),
                        ]
                    );
                    return false;
                }
            });
        }
    }

}
