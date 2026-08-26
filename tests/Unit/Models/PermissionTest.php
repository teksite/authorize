<?php

namespace Teksite\Authorize\Tests\Unit\Models;

use Illuminate\Support\Facades\Cache;
use Teksite\Authorize\GateServiceProvider;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_it_can_be_created_via_factory(): void
    {
        $permission = Permission::factory()->create(['title' => 'post.create']);

        $this->assertDatabaseHas('auth_permissions', ['title' => 'post.create']);
        $this->assertSame('post.create', $permission->title);
    }

    public function test_title_must_be_unique(): void
    {
        Permission::factory()->create(['title' => 'post.create']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Permission::factory()->create(['title' => 'post.create']);
    }

    public function test_create_rules_require_a_unique_title(): void
    {
        Permission::factory()->create(['title' => 'post.create']);

        $rules = Permission::rules('create');

        $validator = validator(['title' => 'post.create', 'description' => null], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    public function test_update_rules_ignore_the_current_record(): void
    {
        $permission = Permission::factory()->create(['title' => 'post.create']);

        $rules = Permission::rules('update', $permission->id);

        $validator = validator(['title' => 'post.create', 'description' => null], $rules);

        $this->assertTrue($validator->passes());
    }

    public function test_unknown_operation_returns_no_rules(): void
    {
        $this->assertSame([], Permission::rules('something-else'));
    }

    public function test_it_belongs_to_many_roles(): void
    {
        $permission = Permission::factory()->create();
        $role = Role::factory()->create();

        $role->permissions()->attach($permission);

        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_creating_a_permission_forgets_the_gate_cache(): void
    {
        Cache::put(GateServiceProvider::PERMISSIONS_CACHE_KEY, ['stale.permission']);

        Permission::factory()->create();

        $this->assertFalse(Cache::has(GateServiceProvider::PERMISSIONS_CACHE_KEY));
    }

    public function test_deleting_a_permission_forgets_the_gate_cache(): void
    {
        $permission = Permission::factory()->create();
        Cache::put(GateServiceProvider::PERMISSIONS_CACHE_KEY, [$permission->title]);

        $permission->delete();

        $this->assertFalse(Cache::has(GateServiceProvider::PERMISSIONS_CACHE_KEY));
    }
}
