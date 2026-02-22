<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        // Pay2s: mỗi phút chạy 12 lần sync, mỗi lần cách 5 giây (~ đồng bộ mỗi 5 giây)
        $schedule->command('pay2s:sync', ['--loop' => 12, '--interval' => 5])->everyMinute()->withoutOverlapping(90);
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
