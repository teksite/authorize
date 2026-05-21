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
    public function handle(): void
    {
        $stubPath = __DIR__ . '/../Migrations/stub';

        $stubFiles = [
            'permission' => $stubPath . '/0001_01_01_100100__create_permissions_table.stub',
            'role'       => $stubPath . '/0001_01_01_100101_create_roles_table.stub',
        ];

        $targetPath = database_path('migrations');

        if (!File::isDirectory($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }
        $targetPaths = [
            'permission' => $targetPath . '/0001_01_01_100100_create_permissions_table.php',
            'role'       => $targetPath . '/0001_01_01_100101_create_roles_table.php',
        ];

        foreach ($targetPaths as $key=>$target) {
            if (!File::exists($target)) {
                File::copy($stubFiles[$key], $target);
                $this->info('Created migration: ' . $target);
            } else {
                $this->warn('Migration already exists: ' . $target);
            }

        }
    }
}
