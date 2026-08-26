<?php

namespace Teksite\Authorize\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Teksite\Authorize\AuthorizeServiceProvider;
use Teksite\Authorize\Tests\Fixtures\TestUser;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register the package service provider(s) so Testbench boots them
     * exactly like a real Laravel application would.
     */
    protected function getPackageProviders($app): array
    {
        return [
            AuthorizeServiceProvider::class,
        ];
    }

    /**
     * Configure the fixture application: sqlite in-memory DB, the
     * fixture "users" model as the auth provider, and package defaults.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);

        $app['config']->set('authorize.super_admin_role', 'administrator');
        $app['config']->set('authorize.cache_enabled', true);
        $app['config']->set('authorize.cache_ttl', 3600);
        $app['config']->set('authorize.boot_gates', true);
        $app['config']->set('authorize.boot_gates_in_console', true);

        // Array cache: fast, in-memory, isolated per test run.
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Load the fixture "users"/"admins" tables plus the package's own
     * tables (built from the raw .stub files, since the package ships
     * them unpublished until `authorize:install` is run).
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');

        $stubPath = __DIR__ . '/../src/Migrations/stub';

        (require $stubPath . '/0001_01_01_100100__create_permissions_table.stub')->up();
        (require $stubPath . '/0001_01_01_100101_create_roles_table.stub')->up();
    }
}
