<?php

namespace Teksite\Authorize;

use Illuminate\Support\ServiceProvider;
use Teksite\Authorize\Console\AuthInstall;


class AuthorizeServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->registerConfigs();
        $this->registerProviders();

    }

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->bootCommands();
        $this->bootPublishing();
    }


    protected function registerProviders(): void
    {
        $this->app->register(GateServiceProvider::class);

    }

    /**
     * Boot console commands.
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuthInstall::class,
            ]);
        }
    }


    protected function registerConfigs(): void
    {
        $configPath = config_path('authorize.php');
        $this->mergeConfigFrom(file_exists($configPath) ? $configPath : __DIR__ . '/config/authorize.php', 'authorize');
    }

    /**
     * Boot publishing configuration and migrations.
     */
    protected function bootPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/config/authorize.php' => config_path('authorize.php'),
        ], ['authorize', 'authorize-config']);

        $this->publishes([
            __DIR__ . '/Migrations/' => database_path('migrations'),
        ], ['authorize', 'authorize-migration']);
    }


}
