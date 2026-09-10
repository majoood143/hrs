<?php

<<<<<<< HEAD
<<<<<<< HEAD
use Illuminate\Auth\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
=======
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
=======
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\SetLocale;
>>>>>>> bbd33618 (Add transfer board creation and listing views)
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as BasePreventRequestsDuringMaintenance;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
<<<<<<< HEAD
    ->withMiddleware(function (Middleware $middleware) {
       $middleware->alias([
        'auth'=> Authenticate::class,
        'guest'=> RedirectIfAuthenticated::class
       ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
=======
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->replace(
            BasePreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
        //
    })->create();
