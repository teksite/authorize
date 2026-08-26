<?php

namespace Teksite\Authorize\Tests\Feature;

use Illuminate\Support\Facades\File;
use Teksite\Authorize\Tests\TestCase;

class AuthInstallCommandTest extends TestCase
{
    private string $permissionMigration;
    private string $roleMigration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissionMigration = database_path('migrations/0001_01_01_100100_create_permissions_table.php');
        $this->roleMigration = database_path('migrations/0001_01_01_100101_create_roles_table.php');

        File::deleteDirectory(database_path('migrations'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(database_path('migrations'));

        parent::tearDown();
    }

    public function test_it_copies_both_migration_files_into_the_application(): void
    {
        $this->artisan('authorize:install')->assertSuccessful();

        $this->assertFileExists($this->permissionMigration);
        $this->assertFileExists($this->roleMigration);
    }

    public function test_copied_migrations_are_valid_and_runnable(): void
    {
        $this->artisan('authorize:install');

        $migration = require $this->permissionMigration;

        $this->assertTrue(method_exists($migration, 'up'));
        $this->assertTrue(method_exists($migration, 'down'));
    }

    public function test_it_does_not_overwrite_an_existing_migration(): void
    {
        File::makeDirectory(database_path('migrations'), 0755, true);
        File::put($this->permissionMigration, '<?php // custom, already edited by the user');

        $this->artisan('authorize:install');

        $this->assertStringContainsString(
            'custom, already edited by the user',
            File::get($this->permissionMigration)
        );
    }

    public function test_it_creates_the_migrations_directory_if_missing(): void
    {
        $this->assertDirectoryDoesNotExist(database_path('migrations'));

        $this->artisan('authorize:install');

        $this->assertDirectoryExists(database_path('migrations'));
    }
}
