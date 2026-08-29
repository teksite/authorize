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
        $this->copyMigration();
        $this->publishConfigFile();
    }

    private function copyMigration(): void
    {
        $this->info('Publishing migrations assets.');

        $stubPath = __DIR__ . '/../Migrations/stub';
        $targetPath = database_path('migrations');

        if (!File::isDirectory($targetPath)) File::makeDirectory($targetPath, 0755, true);


        $migrations = [
            'permission' => [
                'source' => $stubPath . '/0001_01_01_100100__create_permissions_table.stub',
                'target' => $targetPath . '/0001_01_01_100100_create_permissions_table.php',
            ],
            'role'       => [
                'source' => $stubPath . '/0001_01_01_100101_create_roles_table.stub',
                'target' => $targetPath . '/0001_01_01_100101_create_roles_table.php',
            ],
        ];

        foreach ($migrations as $key=>$migration) {
            if (File::exists($migration['target'])) {
                $this->components->twoColumnDetail(
                    "File [{$migration['target']}] already exists  ",
                    '<fg=yellow;options=bold>SKIPPED</>'
                );
                continue;
            }

            File::copy($migration['source'], $migration['target']);

            $this->components->twoColumnDetail(
                "Copying $key migration file to [{$migration['target']}]",
                '<fg=green;options=bold>DONE</>'
            );

        }
    }

    private function publishConfigFile(): int
    {
        return $this->call('vendor:publish', [
            '--tag'   => 'authorize-config',
            '--force' => false,
        ]);
    }
}
