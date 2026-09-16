<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'role.redirect' => \App\Http\Middleware\RoleBasedRedirect::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A 419 (expired/mismatched CSRF token) previously showed Laravel's
        // bare "Page Expired" page and discarded whatever the user had typed -
        // on a long-open form (e.g. a seller's shift-long Sell sheet) that
        // looked like "refreshing doesn't help" even though a fresh visit
        // would have worked. Instead, send them back to the same form with
        // their input restored and a plain explanation, so a single retry
        // recovers instead of losing the work.
        //
        // Note: by the time a renderable callback runs, Laravel has already
        // converted TokenMismatchException into a plain HttpException with
        // status 419 (see Handler::render() -> prepareException()), so the
        // match has to be on the resulting HttpException + status code, not
        // on TokenMismatchException itself.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 && ! $request->expectsJson()) {
                return redirect()->back()
                    ->withInput($request->except('_token', 'password', 'password_confirmation'))
                    ->with('error', 'Your session had expired, so the page reset for safety - please try again.');
            }
        });
    })->create();
