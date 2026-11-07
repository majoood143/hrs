<?php

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as BasePreventRequestsDuringMaintenance;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Payment gateways POST back to these without a session or CSRF token; each one is
        // authenticated by the gateway itself (signature, encryption key, or a matching amount).
        $middleware->validateCsrfTokens(except: [
            'payment/thawani/webhook',
            'payment/nbo/callback',
            'payment/ccavenue/callback',
        ]);

        // Only the customer pages use the plain "auth" middleware (the admin panels have their own);
        // send a signed-out customer to the phone sign-in, and come back to the page they asked for.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('account/*') ? route('account.login') : url('/admin/login'));

        $middleware->replace(
            BasePreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API answers errors (404, 422, 429…) as JSON whatever the client's Accept header says.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());

        // The app shows `message`, and Laravel's own are English ("Unauthenticated.", "Server Error",
        // "No query results for model…"). The API's translated messages never arrive here as exceptions.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/v1/*') || ! $response instanceof JsonResponse || $e instanceof ValidationException || $e instanceof HttpResponseException) {
                return $response;
            }

            $status = in_array($response->getStatusCode(), [401, 403, 404, 429], true) ? $response->getStatusCode() : 500;

            return $response->setData(['message' => __("api.errors.{$status}", locale: SetApiLocale::resolve($request))] + $response->getData(true));
        });
    })->create();
