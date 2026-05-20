<?php

namespace Teksite\Authorize\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('authorize:install')]
#[Description('install Authorize migrations')]
class AuthInstall extends Command
{

    /**
     * Execute the console command.
     */
    public function handle() :void
    {
        $stubPath = __DIR__ . '/../Migrations/stub';

        $permissionStub = $stubPath . '/0001_01_01_100100__create_permissions_table.stub';
        $roleStub = $stubPath . '/0001_01_01_100101_create_roles_table.stub';

        $targetPath = database_path('migrations');

        if (!File::isDirectory($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }

        $permissionTarget = $targetPath . '/0001_01_01_100100_create_permissions_table.php';
        $roleTarget = $targetPath . '/0001_01_01_100101_create_roles_table.php';

        if (!File::exists($permissionTarget)) {
            File::copy($permissionStub, $permissionTarget);
            $this->info('Created migration: ' . $permissionTarget);
        } else {
            $this->warn('Migration already exists: ' . $permissionTarget);
        }

        if (!File::exists($roleTarget)) {
            File::copy($roleStub, $roleTarget);
            $this->info('Created migration: ' . $roleTarget);
        } else {
            $this->warn('Migration already exists: ' . $roleTarget);
        }

    }
}
