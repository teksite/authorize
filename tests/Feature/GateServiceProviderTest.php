<?php

namespace Teksite\Authorize\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Teksite\Authorize\GateServiceProvider;
use Teksite\Authorize\Models\Permission;
use Teksite\Authorize\Models\Role;
use Teksite\Authorize\Tests\Fixtures\TestUser;
use Teksite\Authorize\Tests\TestCase;

class GateServiceProviderTest extends TestCase
{
    /**
     * Re-run GateServiceProvider::boot() against the current, already
     * seeded application/database.
     *
     * We deliberately avoid Testbench's refreshApplication() here: that
     * rebuilds the app (and, with the in-memory sqlite connection this
     * suite uses, wipes the database along with it), which would erase
     * the very Permission/Role rows each test just created. Booting a
     * fresh provider instance against the *same* app re-registers gates
     * against current data without touching the connection.
     */
    private function bootGates(): void
    {
        (new GateServiceProvider($this->app))->boot();
    }

    private function user(): TestUser
    {
        return TestUser::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    }

    public function test_a_permission_row_is_registered_as_a_gate(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);

        $this->bootGates();

        $this->assertTrue(Gate::has('post.publish'));
    }

    public function test_gate_allows_when_the_user_has_the_permission(): void
    {
        $permission = Permission::factory()->create(['title' => 'post.publish']);
        $this->bootGates();

        $user = $this->user();
        $user->syncPermissions([$permission->id]);

        $this->assertTrue(Gate::forUser($user)->allows('post.publish'));
    }

    public function test_gate_denies_when_the_user_lacks_the_permission(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);
        $this->bootGates();

        $user = $this->user();

        $this->assertFalse(Gate::forUser($user)->allows('post.publish'));
    }

    public function test_super_admin_role_bypasses_every_registered_gate(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);
        $adminRole = Role::factory()->create(['title' => 'administrator']);
        $this->bootGates();

        $user = $this->user();
        $user->assignRole($adminRole);

        $this->assertTrue(Gate::forUser($user)->allows('post.publish'));
    }

    public function test_gates_are_not_registered_when_boot_gates_is_disabled(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);
        config(['authorize.boot_gates' => false]);

        $this->bootGates();

        $this->assertFalse(Gate::has('post.publish'));
    }

    public function test_validate_database_tables_returns_false_when_a_table_is_missing(): void
    {
        Schema::drop('auth_permissions');

        $provider = new GateServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'validateDatabaseTables');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($provider));
    }

    public function test_permissions_are_cached_after_first_boot(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);

        $this->bootGates();

        $this->assertTrue(Cache::has(GateServiceProvider::PERMISSIONS_CACHE_KEY));
        $this->assertContains('post.publish', Cache::get(GateServiceProvider::PERMISSIONS_CACHE_KEY));
    }

    public function test_stale_cached_permission_list_is_used_until_invalidated(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);
        $this->bootGates();

        // Simulate another process creating a permission without going
        // through Eloquent's `created` cache-busting hook.
        Cache::forget(GateServiceProvider::PERMISSIONS_CACHE_KEY);
        Cache::put(GateServiceProvider::PERMISSIONS_CACHE_KEY, ['post.publish']);
        Permission::query()->insert(['title' => 'post.archive', 'created_at' => now(), 'updated_at' => now()]);

        $this->bootGates();

        $this->assertFalse(Gate::has('post.archive'));
    }

    public function test_gate_check_failure_inside_has_permission_is_caught_and_denies_access(): void
    {
        Permission::factory()->create(['title' => 'post.publish']);
        $this->bootGates();

        $subject = new class extends TestUser {
            public function hasPermission(string|int|array|Permission $permissions, bool $any = true): bool
            {
                throw new \RuntimeException('boom');
            }
        };
        $subject->name = 'Broken Subject';
        $subject->email = 'broken@example.com';
        $subject->save();

        $this->assertFalse(Gate::forUser($subject)->allows('post.publish'));
    }
}
