<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateMonthlyInvoices;
use App\Console\Commands\RegularizeCompletedOrders;
use App\Console\Commands\BackupRdsDatabase;



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
    
// Run RDS database backup daily at 2:00 AM and email to data analyst
Schedule::command('rds:backup --email=data.analyst@cfpcsl.com')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->emailOutputTo('kevinliboire@gmail.com')
    ->onFailure(function () {
        // Log failure or send notification
        \Illuminate\Support\Facades\Log::error('RDS backup failed');
    });