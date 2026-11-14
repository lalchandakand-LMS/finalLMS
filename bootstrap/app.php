<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureInstalled::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\TrackWebsiteVisit::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureDeviceSessionActive::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminModulePermission::class,
            'activity.log' => \App\Http\Middleware\LogUserActivity::class,
            'student.device' => \App\Http\Middleware\EnsureStudentDeviceIsApproved::class,
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
