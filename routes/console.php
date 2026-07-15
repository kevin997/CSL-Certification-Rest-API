<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateMonthlyInvoices;
use App\Console\Commands\RegularizeCompletedOrders;
use App\Console\Commands\SendProductSubscriptionReminders;
use App\Console\Commands\SendInstructorWeeklyDigest;
use App\Console\Commands\SendLearnerWeeklyDigest;

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

// Engagement: Instructor weekly digest - Monday at 8:00 AM
Schedule::command(SendInstructorWeeklyDigest::class)
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping(3600)
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Instructor weekly digest failed');
    });

// Engagement: Learner weekly digest - Wednesday at 8:00 AM
Schedule::command(SendLearnerWeeklyDigest::class)
    ->weeklyOn(3, '08:00')
    ->withoutOverlapping(3600)
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Learner weekly digest failed');
    });

// Marketing automations: abandoned-order reminders - hourly
Schedule::command(\App\Console\Commands\ProcessAbandonedOrdersCommand::class)
    ->hourly()
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Abandoned-order automation run failed');
    });

// ---------------------------------------------------------------------------
// KURSA Marketing & Retention engine (AI-generated content pool + broadcasts)
// Times are Africa/Douala — the platform's primary audience timezone.
// ---------------------------------------------------------------------------

// Nightly: top the AI content pool up to 10 pending messages per channel
// (group tips, WhatsApp statuses, email campaigns) via Ollama.
Schedule::command(\App\Console\Commands\GenerateMarketingContentCommand::class)
    ->dailyAt('03:30')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(7200)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Marketing content generation failed');
    });

// Daily support-group tip (13:00). WhatsApp STATUS posting is delegated to
// the openclaw agent (sources blog.csl-brands.com directly) — the
// kursa:post-daily-status command remains available for manual runs.
Schedule::command(\App\Console\Commands\BroadcastGroupTipCommand::class)
    ->dailyAt('13:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('WhatsApp group tip broadcast failed');
    });

// Weekly email campaign to opted-in instructors — Tuesday 09:00.
Schedule::command(\App\Console\Commands\SendEmailCampaignCommand::class)
    ->weeklyOn(2, '09:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Email marketing campaign failed');
    });

// Daily behaviour-driven retention nudges (WhatsApp + email fallback) — 12:00.
Schedule::command(\App\Console\Commands\RunRetentionCampaignsCommand::class)
    ->dailyAt('12:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Retention campaign run failed');
    });

// Weekly: (re)index the marketing knowledge base — blog articles + docs are
// chunked and embedded (nomic-embed-text) for grounded generation and
// semantic dedupe. Generation also lazily indexes single posts it needs.
Schedule::command(\App\Console\Commands\IndexKnowledgeCommand::class)
    ->weeklyOn(7, '02:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(7200)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Marketing knowledge indexing failed');
    });

// Keep the assistant's model resident on the Ollama box (default keep_alive
// evicts after ~5 idle minutes; a cold load pushed chat replies past nginx's
// timeout). A no-op load request every ten minutes pins it for 30 minutes.
Schedule::call(function () {
    try {
        \Illuminate\Support\Facades\Http::timeout(20)->post(
            rtrim((string) config('ai.providers.ollama.url'), '/').'/api/generate',
            ['model' => 'llama3.2:1b', 'keep_alive' => '30m'],
        );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Assistant model keep-warm failed: '.$e->getMessage());
    }
})->name('assistant-model-keep-warm')->everyTenMinutes()->onOneServer();
