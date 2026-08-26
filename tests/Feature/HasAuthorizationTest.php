<?php

namespace Teksite\Authorize\Tests\Feature;

use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Tests\Fixtures\TestAdmin;
use Teksite\Authorize\Tests\Fixtures\TestUser;
use Teksite\Authorize\Tests\TestCase;

class HasAuthorizationTest extends TestCase
{
    private function user(): TestUser
    {
        return TestUser::create(['name' => 'Ada', 'email' => fake()->unique()->safeEmail(),]);
    }


    public function test_sync_permissions_by_id(): void
    {
        $user = $this->user();
        $permission = Permission::factory()->create();

        $user->syncPermissions([$permission->id]);

        $this->assertTrue($user->hasPermission($permission->title));
    }

    public function test_sync_permissions_by_title(): void
    {
        $user = $this->user();
        $permission = Permission::factory()->create(['title' => 'post.publish']);

        $user->syncPermissions(['post.publish']);

        $this->assertTrue($user->hasPermission('post.publish'));
    }

    public function test_sync_permissions_by_model_instance(): void
    {
        $user = $this->user();
        $permission = Permission::factory()->create();

        $user->syncPermissions($permission);

        $this->assertTrue($user->hasPermission($permission->title));
    }

    public function test_sync_permissions_detaches_by_default(): void
    {
        $user = $this->user();
        [$first, $second] = Permission::factory()->count(2)->create();

        $user->syncPermissions([$first->id]);
        $user->syncPermissions([$second->id]);

        $this->assertFalse($user->hasPermission($first->title));
        $this->assertTrue($user->hasPermission($second->title));
    }

    public function test_sync_permissions_without_detaching_keeps_existing(): void
    {
        $user = $this->user();
        [$first, $second] = Permission::factory()->count(2)->create();

        $user->syncPermissions([$first->id]);
        $user->syncPermissions([$second->id], detaching: false);

        $this->assertTrue($user->hasPermission($first->title));
        $this->assertTrue($user->hasPermission($second->title));
    }

    public function test_unknown_permission_titles_are_silently_ignored(): void
    {
        $user = $this->user();

        $result = $user->syncPermissions(['does.not.exist']);

        $this->assertSame([], $result['attached'] ?? []);
        $this->assertFalse($user->hasPermission('does.not.exist'));
    }

    // --- Roles ----------------------------------------------------------------

    public function test_assign_role_by_id_title_or_instance(): void
    {
        $role = Role::factory()->create(['title' => 'editor']);

        $byId = $this->user();
        $byId->assignRole($role->id);
        $this->assertTrue($byId->hasRole('editor'));

        $byTitle = $this->user();
        $byTitle->assignRole('editor');
        $this->assertTrue($byTitle->hasRole('editor'));

        $byInstance = $this->user();
        $byInstance->assignRole($role);
        $this->assertTrue($byInstance->hasRole($role));
    }

    public function test_has_role_any_vs_all(): void
    {
        $editor = Role::factory()->create(['title' => 'editor']);
        $author = Role::factory()->create(['title' => 'author']);

        $user = $this->user();
        $user->assignRole([$editor->id]);

        $this->assertTrue($user->hasRole(['editor', 'author'], any: true));
        $this->assertFalse($user->hasRole(['editor', 'author'], any: false));

        $user->assignRole([$author->id], detaching: false);

        $this->assertTrue($user->hasRole(['editor', 'author'], any: false));
    }

    public function test_has_role_with_no_matching_roles_returns_false(): void
    {
        $user = $this->user();

        $this->assertFalse($user->hasRole('nonexistent-role'));
    }

    // --- Permissions via roles ---------------------------------------------

    public function test_has_permission_checks_permissions_inherited_from_roles(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);

        $user = $this->user();
        $user->assignRole($role);

        $this->assertTrue($user->hasPermission($permission->title));
    }

    public function test_get_all_permissions_merges_direct_and_role_permissions(): void
    {
        $direct = Permission::factory()->create(['title' => 'direct.perm']);
        $viaRole = Permission::factory()->create(['title' => 'role.perm']);

        $role = Role::factory()->create();
        $role->permissions()->attach($viaRole);

        $user = $this->user();
        $user->syncPermissions([$direct->id]);
        $user->assignRole($role);

        $titles = array_values($user->getAllPermissions(onlyIds: false));

        $this->assertContains('direct.perm', $titles);
        $this->assertContains('role.perm', $titles);
    }

    public function test_get_direct_permissions_excludes_role_permissions(): void
    {
        $direct = Permission::factory()->create(['title' => 'direct.perm']);
        $viaRole = Permission::factory()->create(['title' => 'role.perm']);

        $role = Role::factory()->create();
        $role->permissions()->attach($viaRole);

        $user = $this->user();
        $user->syncPermissions([$direct->id]);
        $user->assignRole($role);

        $titles = array_values($user->getDirectPermissions());

        $this->assertContains('direct.perm', $titles);
        $this->assertNotContains('role.perm', $titles);
    }

    public function test_get_permissions_by_roles_groups_by_role_title(): void
    {
        $role = Role::factory()->create(['title' => 'editor']);
        $permission = Permission::factory()->create(['title' => 'post.edit']);
        $role->permissions()->attach($permission);

        $user = $this->user();
        $user->assignRole($role);

        $grouped = $user->getPermissionsByRoles();

        $this->assertArrayHasKey('editor', $grouped);
        $this->assertContains('post.edit', array_values($grouped['editor']));
    }

    // --- Super admin ----------------------------------------------------------

    public function test_super_admin_bypasses_permission_checks(): void
    {
        config(['authorize.super_admin_role' => 'administrator']);

        $adminRole = Role::factory()->create(['title' => 'administrator']);
        $user = $this->user();
        $user->assignRole($adminRole);

        $this->assertTrue($user->hasPermission('anything.at.all'));
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_regular_user_is_not_super_admin(): void
    {
        $user = $this->user();

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasPermission('anything.at.all'));
    }

    // --- Hierarchy --------------------------------------------------------------

    public function test_hierarchy_returns_null_when_no_roles_assigned(): void
    {
        $user = $this->user();

        $this->assertNull($user->hierarchy());
    }

    public function test_hierarchy_returns_min_and_max_across_roles(): void
    {
        $low = Role::factory()->create(['hierarchy' => 10]);
        $high = Role::factory()->create(['hierarchy' => 50]);

        $user = $this->user();
        $user->assignRole([$low->id, $high->id]);

        $this->assertSame(10.0, (float)$user->hierarchy(min: true, max: false));
        $this->assertSame(50.0, (float)$user->hierarchy(min: false, max: true));
        $this->assertSame(
            ['min' => 10.0, 'max' => 50.0],
            array_map('floatval', $user->hierarchy(min: true, max: true))
        );
    }

    public function test_can_access_model_by_hierarchy(): void
    {
        $juniorRole = Role::factory()->create(['hierarchy' => 50]);
        $seniorRole = Role::factory()->create(['hierarchy' => 10]);

        $junior = $this->user();
        $junior->assignRole($juniorRole);

        $senior = TestUser::create(['name' => 'Grace', 'email' => 'grace@example.com']);
        $senior->assignRole($seniorRole);

        // Lower hierarchy number = higher authority in this package's convention.
        $this->assertTrue($senior->canAccessModelByHierarchy($junior));
        $this->assertFalse($junior->canAccessModelByHierarchy($senior));
    }

    public function test_can_access_model_by_hierarchy_returns_false_without_own_role(): void
    {
        $target = $this->user();
        $target->assignRole(Role::factory()->create());

        $noRoleUser = TestUser::create(['name' => 'NoRole', 'email' => 'norole@example.com']);

        $this->assertFalse($noRoleUser->canAccessModelByHierarchy($target));
    }

    public function test_can_access_role_by_hierarchy(): void
    {
        $userRole = Role::factory()->create(['hierarchy' => 30]);
        $targetRole = Role::factory()->create(['hierarchy' => 60, 'title' => 'low-authority']);

        $user = $this->user();
        $user->assignRole($userRole);

        $this->assertTrue($user->canAccessRoleByHierarchy('low-authority'));
        $this->assertTrue($user->canAccessRoleByHierarchy($targetRole->id));
        $this->assertTrue($user->canAccessRoleByHierarchy($targetRole));
    }

    public function test_can_access_role_by_hierarchy_returns_null_for_unknown_role(): void
    {
        $user = $this->user();
        $user->assignRole(Role::factory()->create(['hierarchy' => 30]));

        $this->assertNull($user->canAccessRoleByHierarchy('does-not-exist'));
    }

    // --- Model independence ------------------------------------------------

    public function test_authorization_works_identically_for_a_non_user_model(): void
    {
        $admin = TestAdmin::create(['name' => 'System Admin']);
        $permission = Permission::factory()->create(['title' => 'system.manage']);

        $admin->syncPermissions([$permission->id]);

        $this->assertTrue($admin->hasPermission('system.manage'));
    }

    public function test_two_different_model_types_sharing_the_same_id_do_not_share_permissions(): void
    {
        $user = $this->user(); // id = 1
        $admin = TestAdmin::create(['name' => 'Admin One']); // id = 1

        $permission = Permission::factory()->create();
        $user->syncPermissions([$permission->id]);

        $this->assertTrue($user->hasPermission($permission->title));
        $this->assertFalse($admin->hasPermission($permission->title));
    }

    // --- Cache lifecycle -----------------------------------------------------

    public function test_clear_authorization_cache_removes_cached_values(): void
    {
        $user = $this->user();
        $user->warmAuthorizationCache();

        $user->clearAuthorizationCache();

        // A fresh read after clearing must hit the database again without error.
        $this->assertIsArray($user->getAllPermissions());
    }

    public function test_permission_changes_are_reflected_after_cache_invalidation(): void
    {
        $user = $this->user();
        $permission = Permission::factory()->create();

        $this->assertFalse($user->hasPermission($permission->title));

        $user->syncPermissions([$permission->id]);

        // syncPermissions() should itself invalidate the cache warmed above.
        $this->assertTrue($user->hasPermission($permission->title));
    }
}
