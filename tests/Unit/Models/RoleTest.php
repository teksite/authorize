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
    public function test_changing_a_role_hierarchy_is_reflected_for_assigned_models(): void
    {
        $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@example.com']);
        $role = Role::factory()->create(['hierarchy' => 20]);
        $user->roles()->attach($role);

        $this->assertSame(20.0, $user->hierarchy()); // warms the cache

        $role->hierarchy = 40;
        $role->save();

        $this->assertSame(40.0, $user->hierarchy());
    }

    public function test_deleting_a_role_is_reflected_for_assigned_models(): void
    {
        $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@example.com']);
        $role = Role::factory()->create(['title' => 'editor']);
        $user->roles()->attach($role);

        $this->assertSame([$role->id => 'editor'], $user->getDirectRoles()); // warms the cache

        $role->delete();

        $this->assertSame([], $user->getDirectRoles());
    }


}
