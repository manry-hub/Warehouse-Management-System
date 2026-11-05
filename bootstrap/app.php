<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Polyfill OCI8 constants if ext-oci8 is not loaded yet to prevent undefined constant errors
if (! defined('OCI_DEFAULT')) {
    define('OCI_DEFAULT', 0);
    define('OCI_SYSOPER', 4);
    define('OCI_SYSDBA', 2);
    define('OCI_CRED_EXT', 8);
    define('OCI_DESCRIBE_ONLY', 16);
    define('OCI_COMMIT_ON_SUCCESS', 32);
    define('OCI_EXACT_FETCH', 64);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
