<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsureAdminTwoFactorIsConfirmed;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceHttpsInProduction;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventAdminDisablingTwoFactor;
use App\Http\Middleware\ThrottleRegistrationRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            PreventAdminDisablingTwoFactor::class,
            ThrottleRegistrationRequests::class,
            ForceHttpsInProduction::class,
            ApplySecurityHeaders::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'webhooks/geniuspay',
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'admin.2fa' => EnsureAdminTwoFactorIsConfirmed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
