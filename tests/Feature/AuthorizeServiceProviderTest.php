<?php

namespace Teksite\Authorize\Tests\Feature;

use Teksite\Authorize\AuthorizeServiceProvider;
use Teksite\Authorize\Console\AuthInstall;
use Teksite\Authorize\GateServiceProvider;
use Teksite\Authorize\Tests\TestCase;

class AuthorizeServiceProviderTest extends TestCase
{
    public function test_default_config_values_are_merged_without_a_published_config_file(): void
    {
        $this->assertTrue(config('authorize.cache_enabled'));
        $this->assertSame(3600, config('authorize.cache_ttl')); // overridden in TestCase::defineEnvironment
        $this->assertSame('administrator', config('authorize.super_admin_role'));
    }

    public function test_gate_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(GateServiceProvider::class));
    }

    public function test_authorize_install_command_is_registered(): void
    {
        $this->artisan('list')->assertSuccessful();

        $this->assertContains(
            'authorize:install',
            array_keys($this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all())
        );
    }

    public function test_config_file_is_publishable_under_the_correct_tags(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
            AuthorizeServiceProvider::class,
            'authorize-config'
        );

        $this->assertNotEmpty($paths);

        foreach ($paths as $from => $to) {
            $this->assertFileExists($from);
            $this->assertSame(
                'authorize.php',
                basename($to)
            );

            $this->assertSame(
                'config',
                basename(dirname($to))
            );        }
    }

    public function test_migrations_are_publishable_under_the_correct_tags(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
            AuthorizeServiceProvider::class,
            'authorize-migration'
        );

        $this->assertNotEmpty($paths);

        foreach ($paths as $from => $to) {
            $this->assertDirectoryExists($from);
        }
    }
}
