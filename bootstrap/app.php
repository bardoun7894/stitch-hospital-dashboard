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
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Send medication dose reminders every 5 minutes
        $schedule->command('medications:send-reminders --window=5')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/dose-reminders.log'));

        // Send appointment reminders daily at 6 PM (evening before)
        $schedule->command('appointments:send-reminders')
            ->dailyAt('18:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/appointment-reminders.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\CacheHeaders::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'firebase.auth' => \App\Http\Middleware\VerifyFirebaseToken::class,
            'auth.session' => \App\Http\Middleware\AuthFirebaseSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
