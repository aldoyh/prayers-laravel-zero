<?php

use App\Providers\AppServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name' => 'Prayers-cli',

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | Read from the VERSION file at the project root. Falls back to the git
    | version tag, and finally to '1.0.0' if neither is available (e.g.
    | when running inside a compiled PHAR without a git context).
    |
    */

    'version' => (static function (): string {
        $versionFile = dirname(__DIR__) . '/VERSION';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        try {
            return app('git.version');
        } catch (\Throwable) {
            return '1.0.0';
        }
    })(),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */

    'env' => 'development',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        AppServiceProvider::class,
    ],

];
