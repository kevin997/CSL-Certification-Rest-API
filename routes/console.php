<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateMonthlyInvoices;
use App\Console\Commands\RegularizeCompletedOrders;
use App\Console\Commands\SendProductSubscriptionReminders;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Schedule Commands
|--------------------------------------------------------------------------
|
| This file is where you may define all of your scheduled commands.
|
*/

Schedule::command(GenerateMonthlyInvoices::class)
    ->lastDayOfMonth('23:59');

// Regularize orders with completed transactions every 5 minutes
Schedule::command(RegularizeCompletedOrders::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Clean old backups daily at 1:00 AM
Schedule::command('backup:clean')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Backup cleanup failed');
    });

// Run database backup daily at 2:00 AM with compression and email notifications
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Daily database backup failed');
    })
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Daily database backup completed successfully');
    });

// Full application backup (database + files) weekly on Monday at 1:30 AM
Schedule::command('backup:run')
    ->weeklyOn(1, '01:30') // Every Monday at 1:30 AM
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Weekly full backup failed');
    })
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Weekly full backup completed successfully');
    });

// Monitor backups health daily at 3:00 AM
Schedule::command('backup:monitor')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Backup monitoring failed');
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

// Sales Database backup - runs daily at 4:00 AM with email notifications
Schedule::command('backup:sales-database --email')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Sales database backup failed');
    })
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Sales database backup completed successfully');
    });

// Product subscription reminders - runs daily at 5:00 AM
Schedule::command(SendProductSubscriptionReminders::class)
    ->dailyAt('05:00')
    ->withoutOverlapping(3600)
    ->runInBackground();
