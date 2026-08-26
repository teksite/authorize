<?php

namespace Teksite\Authorize\Tests\Unit\Models;

use Illuminate\Support\Facades\Cache;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;
use Teksite\Authorize\Tests\Fixtures\TestUser;
use Teksite\Authorize\Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_it_can_be_created_via_factory(): void
    {
        $role = Role::factory()->create(['title' => 'editor', 'hierarchy' => 40]);

        $this->assertDatabaseHas('auth_roles', ['title' => 'editor', 'hierarchy' => 40]);
    }

    public function test_title_must_be_unique(): void
    {
        Role::factory()->create(['title' => 'editor']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Role::factory()->create(['title' => 'editor']);
    }

    public function test_create_rules_require_permissions_and_hierarchy(): void
    {
        $validator = validator([], Role::rules('create'));

        $this->assertTrue($validator->fails());
        foreach (['title', 'permissions', 'hierarchy'] as $field) {
            $this->assertArrayHasKey($field, $validator->errors()->toArray());
        }
    }

    public function test_it_belongs_to_many_permissions(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $role->permissions()->attach($permission);

        $this->assertTrue($role->permissions->contains($permission));
    }

    public function test_saving_a_role_forgets_caches_of_assigned_models(): void
    {
        $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@example.com']);
        $role = Role::factory()->create();
        $user->roles()->attach($role);

        Cache::put(AuthorizationCache::roleKey($user), ['cached-role']);

        $role->description = 'updated';
        $role->save();

        $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
    }

    public function test_deleting_a_role_forgets_caches_of_assigned_models(): void
    {
        $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@example.com']);
        $role = Role::factory()->create();
        $user->roles()->attach($role);

        Cache::put(AuthorizationCache::roleKey($user), ['cached-role']);

        $role->delete();

        $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
    }
}
