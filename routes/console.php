<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateMonthlyInvoices;
use App\Console\Commands\RegularizeCompletedOrders;
use App\Console\Commands\BackupRdsDatabase;
use App\Console\Commands\WeeklyAnalyticsReportCommand;



Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(GenerateMonthlyInvoices::class)
    ->lastDayOfMonth('23:59');

// Regularize orders with completed transactions every 5 minutes
Schedule::command(RegularizeCompletedOrders::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
    
// Run RDS database backup daily at 2:00 AM with compression and email to data analyst
Schedule::command('db:backup-rds --email --compress=gzip')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->emailOutputTo('kevinliboire@gmail.com')
    ->onFailure(function () {
        // Log failure or send notification
        \Illuminate\Support\Facades\Log::error('RDS backup failed');
    });

// Emergency backup command for large databases (runs weekly with extended timeout)
Schedule::command('db:backup-rds --email --compress=gzip --timeout=7200')
    ->weeklyOn(1, '01:00') // Every Monday at 1:00 AM
    ->withoutOverlapping()
    ->runInBackground()
    ->emailOutputTo('kevinliboire@gmail.com')
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Weekly extended RDS backup failed');
    });

// Weekly analytics report - runs every Monday at 9:00 AM
Schedule::command('analytics:weekly-report --email')
    ->weeklyOn(1, '09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->emailOutputTo('kevinliboire@gmail.com')
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Weekly analytics report failed');
    });