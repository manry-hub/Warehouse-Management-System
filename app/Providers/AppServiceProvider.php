<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(ServeCommand::class)) {
            ServeCommand::$passthroughVariables = array_unique(array_merge(
                ServeCommand::$passthroughVariables,
                [
                    'ORACLE_HOME',
                    'LD_LIBRARY_PATH',
                    'PHP_INI_SCAN_DIR',
                ]
            ));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
