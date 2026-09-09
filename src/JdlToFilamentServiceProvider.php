<?php

namespace Nacer\JdlToFilament;

use Illuminate\Support\ServiceProvider;
use Nacer\JdlToFilament\Console\Commands\GenerateCommand;
use Nacer\JdlToFilament\Console\Commands\GenerateFilamentResourcesCommand;
use Nacer\JdlToFilament\Console\Commands\GenerateMigrationsCommand;
use Nacer\JdlToFilament\Console\Commands\GenerateModelsCommand;
use Nacer\JdlToFilament\Console\Commands\InstallNodeCommand;

class JdlToFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jdl-to-filament.php', 'jdl-to-filament');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            GenerateCommand::class,
            GenerateMigrationsCommand::class,
            GenerateModelsCommand::class,
            GenerateFilamentResourcesCommand::class,
            InstallNodeCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/jdl-to-filament.php' => config_path('jdl-to-filament.php'),
        ], 'jdl-to-filament-config');
    }
}
