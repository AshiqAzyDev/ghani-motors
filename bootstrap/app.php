<?php

use App\Http\Middleware\EnsureDealerConfigured;
use App\Http\Middleware\RefreshAutotraderStock;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StaffAuthenticated;
use App\Http\Middleware\StaffCan;
use App\Http\Middleware\StaffCanMutate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'staff' => StaffAuthenticated::class,
            'staff.mutate' => StaffCanMutate::class,
            'staff.can' => StaffCan::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/autotrader',
        ]);
        $middleware->web(prepend: [
            EnsureDealerConfigured::class,
        ]);
        $middleware->web(append: [
            RefreshAutotraderStock::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
