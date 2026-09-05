<?php

<<<<<<< HEAD
use Illuminate\Auth\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
=======
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
        //
    })->create();
