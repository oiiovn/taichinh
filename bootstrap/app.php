<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'feature' => \App\Http\Middleware\EnsureFeatureAllowed::class,
        ]);
        $middleware->validateCsrfTokens(except: []);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Pay2s: mỗi phút chạy sync trong cùng process (tránh proc_open bị disable trên hosting)
        $schedule->call(function () {
            app(\App\Services\Pay2sApiService::class)->sync();
        })->everyMinute();
        // Recurring: phát hiện pattern định kỳ (lương, tiền nhà, subscription) — hàng ngày 2h
        $schedule->job(new \App\Jobs\DetectRecurringPatternsJob)->dailyAt('02:00');
        $schedule->job(new \App\Jobs\AccrueLiabilityInterestJob)->dailyAt('03:00');
        $schedule->job(new \App\Jobs\AccrueLoanInterestJob)->dailyAt('03:15');
        $schedule->job(new \App\Jobs\CreateLoanPendingPaymentsJob)->dailyAt('04:00');
        $schedule->command('thu-chi:recurring')->dailyAt('05:00');
        $schedule->command('behavior:compliance')->dailyAt('06:00');
        $schedule->command('forecast:learn')->dailyAt('07:00');
        $schedule->job(new \App\Jobs\BehaviorIntelligenceAggregateJob)->dailyAt('02:30');
        $schedule->job(new \App\Jobs\BehaviorPolicySyncJob)->dailyAt('03:00');
        $schedule->job(new \App\Jobs\CoachingEffectivenessOutcomeJob)->dailyAt('04:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
