<?php

namespace Teksite\Authorize\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Teksite\Authorize\GateServiceProvider;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;
use Teksite\Authorize\Tests\Fixtures\TestUser;
use Teksite\Authorize\Tests\TestCase;

class AuthorizationCacheTest extends TestCase
{
    private function user(): TestUser
    {
        return TestUser::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    }

    public function test_forget_model_clears_all_three_cache_buckets(): void
    {
        $user = $this->user();

        Cache::put(AuthorizationCache::permissionKey($user), ['x']);
        Cache::put(AuthorizationCache::roleKey($user), ['x']);
        Cache::put(AuthorizationCache::hierarchyKey($user), ['x']);

        AuthorizationCache::forgetModel($user);

        $this->assertFalse(Cache::has(AuthorizationCache::permissionKey($user)));
        $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
        $this->assertFalse(Cache::has(AuthorizationCache::hierarchyKey($user)));
    }

    public function test_cache_keys_are_namespaced_by_morph_type_so_different_models_never_collide(): void
    {
        $user = $this->user();

        $this->assertStringContainsString($user->getMorphClass(), AuthorizationCache::permissionKey($user));
        $this->assertStringContainsString((string) $user->getKey(), AuthorizationCache::permissionKey($user));
    }

    public function test_forget_permission_invalidates_gate_cache_and_directly_assigned_models(): void
    {
        $user = $this->user();
        $permission = Permission::factory()->create();
        $user->syncPermissions([$permission->id], detaching: false);

        Cache::put(GateServiceProvider::PERMISSIONS_CACHE_KEY, ['stale']);
        Cache::put(AuthorizationCache::permissionKey($user), ['stale']);

        AuthorizationCache::forgetPermission($permission);

        $this->assertFalse(Cache::has(GateServiceProvider::PERMISSIONS_CACHE_KEY));
        $this->assertFalse(Cache::has(AuthorizationCache::permissionKey($user)));
    }

    public function test_forget_permission_invalidates_models_reached_through_a_role(): void
    {
        $user = $this->user();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);
        $user->assignRole($role);

        Cache::put(AuthorizationCache::permissionKey($user), ['stale']);

        AuthorizationCache::forgetPermission($permission);

        $this->assertFalse(Cache::has(AuthorizationCache::permissionKey($user)));
    }

    public function test_forget_role_invalidates_directly_assigned_models(): void
    {
        $user = $this->user();
        $role = Role::factory()->create();
        $user->assignRole($role);

        Cache::put(AuthorizationCache::roleKey($user), ['stale']);

        AuthorizationCache::forgetRole($role);

        $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
    }

    public function test_forget_permission_role_pivot_invalidates_both_sides(): void
    {
        $user = $this->user();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);
        $user->assignRole($role);

        Cache::put(AuthorizationCache::permissionKey($user), ['stale']);
        Cache::put(AuthorizationCache::roleKey($user), ['stale']);

        AuthorizationCache::forgetPermissionRolePivot($permission->id, $role->id);

        $this->assertFalse(Cache::has(AuthorizationCache::permissionKey($user)));
        $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
    }
}
