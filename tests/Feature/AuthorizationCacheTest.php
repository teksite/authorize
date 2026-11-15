<?php

namespace Teksite\Authorize\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Teksite\Authorize\AuthorizeServiceProvider;
use Teksite\Authorize\GateServiceProvider;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Support\AuthorizationCache;
use Teksite\Authorize\Tests\Fixtures\TestUser;
use Teksite\Authorize\Tests\TestCase;

class AuthorizationCacheTest extends TestCase
{
    private function user(string $email = 'ada@example.com'): TestUser
    {
        return TestUser::create(['name' => 'Ada', 'email' => $email]);
    }

    // --- Security regressions -------------------------------------------------

    public function test_has_permission_all_mode_fails_when_a_requested_permission_does_not_exist(): void
    {
        $user = $this->user();
        $user->syncPermissions([Permission::factory()->create(['title' => 'post.read'])->id]);

        $this->assertTrue($user->hasPermission(['post.read'], any: false));
        $this->assertFalse($user->hasPermission(['post.read', 'does.not.exist'], any: false));
        $this->assertTrue($user->hasPermission(['post.read', 'does.not.exist'], any: true));
    }

    public function test_has_role_all_mode_fails_when_a_requested_role_does_not_exist(): void
    {
        $user = $this->user();
        $user->assignRole(Role::factory()->create(['title' => 'editor']));

        $this->assertTrue($user->hasRole(['editor'], any: false));
        $this->assertFalse($user->hasRole(['editor', 'typo-role'], any: false));
    }

    public function test_changing_role_permissions_through_the_relation_invalidates_holder_caches(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);

        $user = $this->user();
        $user->assignRole($role);

        $this->assertTrue($user->hasPermission($permission->title)); // warms the cache

        $role->permissions()->sync([]);

        $this->assertFalse($user->hasPermission($permission->title));
    }

    public function test_a_value_computed_before_an_invalidation_is_never_served_afterwards(): void
    {
        $key = 'authorize:test:race';

        $first = AuthorizationCache::remember($key, function () {
            AuthorizationCache::bump(); // invalidation lands while we compute

            return ['stale'];
        });

        $this->assertSame(['stale'], $first);
        $this->assertSame(['fresh'], AuthorizationCache::remember($key, fn () => ['fresh']));
    }

    public function test_targeted_flush_works_with_a_morph_map_alias(): void
    {
        Relation::morphMap(['user' => TestUser::class]);

        try {
            $user = $this->user();
            $role = Role::factory()->create();
            $user->assignRole($role);

            Cache::put(AuthorizationCache::roleKey($user), ['x']);

            AuthorizationCache::forgetRoleModels($role->id);

            $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_deleting_a_model_detaches_its_roles_and_permissions(): void
    {
        $user = $this->user();
        $id = $user->getKey();
        $user->assignRole(Role::factory()->create());
        $user->syncPermissions([Permission::factory()->create()->id]);

        $user->delete();

        $this->assertDatabaseMissing('auth_role_models', ['model_id' => $id]);
        $this->assertDatabaseMissing('auth_permission_models', ['model_id' => $id]);
    }

    public function test_package_defaults_are_merged_even_when_a_published_config_exists(): void
    {
        $path = config_path('authorize.php');
        file_put_contents($path, "<?php\nreturn ['cache_ttl' => 10];\n");

        try {
            config(['authorize' => ['cache_ttl' => 10]]);

            $provider = new AuthorizeServiceProvider($this->app);
            (new \ReflectionMethod($provider, 'registerConfigs'))->invoke($provider);

            $this->assertSame(10, config('authorize.cache_ttl'));
            $this->assertNotEmpty(config('authorize.observers'));
        } finally {
            @unlink($path);
        }
    }

    // --- Cache behaviour ------------------------------------------------------

    public function test_role_and_permission_changes_are_reflected_after_invalidation(): void
    {
        $user = $this->user();
        $role = Role::factory()->create(['title' => 'editor']);
        $user->assignRole($role);
        $this->assertSame([$role->id => 'editor'], $user->getDirectRoles()); // warm

        $role->delete();

        $this->assertSame([], $user->getDirectRoles());

        $permission = Permission::factory()->create();
        $user->syncPermissions([$permission->id]);
        $this->assertSame([$permission->id], $user->getAllPermissions()); // warm

        DB::table('auth_permission_models')->delete(); // changed behind Eloquent's back
        AuthorizationCache::forgetPermission($permission);

        $this->assertSame([], $user->getAllPermissions());
    }

    public function test_role_description_change_does_not_flush_but_hierarchy_change_does(): void
    {
        $user = $this->user();
        $role = Role::factory()->create(['hierarchy' => 20]);
        $user->assignRole($role);
        $user->warmAuthorizationCache();

        $role->update(['description' => 'x']);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $user->getDirectRoles();
        $this->assertSame([], DB::getQueryLog()); // still served from cache

        $role->update(['hierarchy' => 99]);

        $this->assertSame(99.0, $user->hierarchy());
    }

    // --- Performance regressions ----------------------------------------------

    public function test_warm_authorization_checks_do_not_touch_the_database(): void
    {
        $user = $this->user();
        $user->syncPermissions([Permission::factory()->create(['title' => 'post.read'])->id]);

        $user->hasPermission('post.read');  // warm
        $user->hasPermission('post.nope');  // warm roles bucket via isSuperAdmin()

        DB::enableQueryLog();
        DB::flushQueryLog();

        $user->hasPermission('post.read');
        $user->hasPermission('post.nope');
        $user->hasRole('administrator');

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_booting_gates_with_a_warm_cache_runs_no_queries(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);
        (new GateServiceProvider($this->app))->boot(); // warm

        DB::enableQueryLog();
        DB::flushQueryLog();

        (new GateServiceProvider($this->app))->boot();

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_default_cache_ttl_is_24_hours(): void
    {
        $defaults = require __DIR__ . '/../../src/config/authorize.php';

        $this->assertSame(86400, $defaults['cache_ttl']);
        $this->assertSame(86400, AuthorizationCache::DEFAULT_TTL);
        $this->assertTrue($defaults['flush_on_save']);
        $this->assertTrue($defaults['detach_on_delete']);
    }

    public function test_saving_a_model_flushes_its_cache_by_default(): void
    {
        $user = $this->user();
        $user->warmAuthorizationCache();
        $this->assertTrue(Cache::has(AuthorizationCache::roleKey($user)));

        $user->update(['name' => 'Grace']);

        $this->assertFalse(Cache::has(AuthorizationCache::roleKey($user)));
    }

    public function test_flush_on_save_can_be_disabled(): void
    {
        config(['authorize.flush_on_save' => false]);

        $user = $this->user();
        $user->warmAuthorizationCache();

        $user->update(['name' => 'Grace']);

        $this->assertTrue(Cache::has(AuthorizationCache::roleKey($user)));
    }

    public function test_assign_role_still_invalidates_when_flush_on_save_is_disabled(): void
    {
        config(['authorize.flush_on_save' => false]);

        $user = $this->user();
        $role = Role::factory()->create(['title' => 'editor']);
        $this->assertFalse($user->hasRole('editor')); // warm

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('editor'));
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
}
