<?php

use App\Console\Commands\BroadcastGroupTipCommand;
use App\Console\Commands\GenerateMarketingContentCommand;
use App\Console\Commands\GenerateMonthlyInvoices;
use App\Console\Commands\IndexKnowledgeCommand;
use App\Console\Commands\MarketingHealthReportCommand;
use App\Console\Commands\ProcessAbandonedOrdersCommand;
use App\Console\Commands\ProcessLicenceLifecycle;
use App\Console\Commands\RegularizeCompletedOrders;
use App\Console\Commands\RunRetentionCampaignsCommand;
use App\Console\Commands\SendEmailCampaignCommand;
use App\Console\Commands\SendInstructorWeeklyDigest;
use App\Console\Commands\SendLearnerWeeklyDigest;
use App\Console\Commands\SendLicenceReminders;
use App\Console\Commands\SendProductSubscriptionReminders;
use App\Console\Commands\VerifyEnvironmentDomains;
use App\Console\Commands\VerifyPendingPayments;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

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

// KURSA licensing transition (Phase 2): monthly platform-fee (commission) invoices are
// retired — course sales carry 0% commission, so there is nothing to invoice. The
// GenerateMonthlyInvoices command class and InvoiceService are intentionally KEPT (not
// deleted) so historical invoices stay readable and the command can still be run manually
// if ever needed, but it is no longer scheduled.
// Schedule::command(GenerateMonthlyInvoices::class)
//     ->lastDayOfMonth('23:59');

// Regularize orders with completed transactions every 5 minutes
Schedule::command(RegularizeCompletedOrders::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Clean old backups daily at 1:00 AM
Schedule::command('backup:clean')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Backup cleanup failed');
    });

// Run database backup daily at 2:00 AM with compression and email notifications
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Daily database backup failed');
    })
    ->onSuccess(function () {
        Log::info('Daily database backup completed successfully');
    });

// Full application backup (database + files) weekly on Monday at 1:30 AM
Schedule::command('backup:run')
    ->weeklyOn(1, '01:30') // Every Monday at 1:30 AM
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Weekly full backup failed');
    })
    ->onSuccess(function () {
        Log::info('Weekly full backup completed successfully');
    });

// Monitor backups health daily at 3:00 AM
Schedule::command('backup:monitor')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Backup monitoring failed');
    });

// Weekly analytics report - runs every Monday at 9:00 AM
Schedule::command('analytics:weekly-report --email')
    ->weeklyOn(1, '09:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->emailOutputTo('kevinliboire@gmail.com')
    ->onFailure(function () {
        Log::error('Weekly analytics report failed');
    });

// Sales Database backup - runs daily at 4:00 AM with email notifications
Schedule::command('backup:sales-database --email')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Sales database backup failed');
    })
    ->onSuccess(function () {
        Log::info('Sales database backup completed successfully');
    });

// Product subscription reminders - runs daily at 5:00 AM
Schedule::command(SendProductSubscriptionReminders::class)
    ->dailyAt('05:00')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground();

// Engagement: Instructor weekly digest - Monday at 8:00 AM
Schedule::command(SendInstructorWeeklyDigest::class)
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Instructor weekly digest failed');
    });

// Engagement: Learner weekly digest - Wednesday at 8:00 AM
Schedule::command(SendLearnerWeeklyDigest::class)
    ->weeklyOn(3, '08:00')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Learner weekly digest failed');
    });

// Marketing automations: abandoned-order reminders - hourly
Schedule::command(ProcessAbandonedOrdersCommand::class)
    ->hourly()
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Abandoned-order automation run failed');
    });

// ---------------------------------------------------------------------------
// KURSA Marketing & Retention engine (AI-generated content pool + broadcasts)
// Times are Africa/Douala — the platform's primary audience timezone.
// ---------------------------------------------------------------------------

// Nightly: top the AI content pool up to 10 pending messages per channel
// (group tips, WhatsApp statuses, email campaigns) via Ollama.
Schedule::command(GenerateMarketingContentCommand::class)
    ->dailyAt('03:30')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(7200)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Marketing content generation failed');
    });

// Daily support-group tip (13:00). WhatsApp STATUS posting is delegated to
// the openclaw agent (sources blog.csl-brands.com directly) — the
// kursa:post-daily-status command remains available for manual runs.
Schedule::command(BroadcastGroupTipCommand::class)
    ->dailyAt('13:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('WhatsApp group tip broadcast failed');
    });

// Weekly email campaign to opted-in instructors — Tuesday 09:00.
Schedule::command(SendEmailCampaignCommand::class)
    ->weeklyOn(2, '09:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Email marketing campaign failed');
    });

// Daily behaviour-driven retention nudges (WhatsApp + email fallback) — 12:00.
Schedule::command(RunRetentionCampaignsCommand::class)
    ->dailyAt('12:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Retention campaign run failed');
    });

// Weekly: (re)index the marketing knowledge base — blog articles + docs are
// chunked and embedded (nomic-embed-text) for grounded generation and
// semantic dedupe. Generation also lazily indexes single posts it needs.
Schedule::command(IndexKnowledgeCommand::class)
    ->weeklyOn(7, '02:00')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(7200)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Marketing knowledge indexing failed');
    });

// Keep the assistant's model resident on the Ollama box (default keep_alive
// evicts after ~5 idle minutes; a cold load pushed chat replies past nginx's
// timeout). A no-op load request every ten minutes pins it for 30 minutes.
Schedule::call(function () {
    try {
        Http::timeout(20)->post(
            rtrim((string) config('ai.providers.ollama.url'), '/').'/api/generate',
            ['model' => 'qwen2.5:14b', 'keep_alive' => '30m'],
        );
    } catch (Throwable $e) {
        Log::warning('Assistant model keep-warm failed: '.$e->getMessage());
    }
})->name('assistant-model-keep-warm')->everyTenMinutes()->onOneServer();

// Weekly self-report: pools, sends, retention and assistant usage — emailed
// to the operator so the fully-automated engine stays observable.
Schedule::command(MarketingHealthReportCommand::class)
    ->weeklyOn(1, '07:30')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Marketing health report failed');
    });

// KURSA licensing transition (Phase 3): server-to-server verify pending payments
// for gateways with a trusted status API; confirmations route through WebhookProcessor.
Schedule::command(VerifyPendingPayments::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// KURSA licensing transition (Phase 4): environment-licence lifecycle.
// Every 15 min: trial expiry → Free, paid expiry → past-due/grace, grace
// elapsed → Free, cancel-at-period-end past ends_at → Free (doc §5, §12).
Schedule::command(ProcessLicenceLifecycle::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Daily: trial reminders (days 0/7/12/14 + day-17 recovery) and grace/renewal
// warnings (doc §5). De-duplicated via the licence reminders_sent column.
Schedule::command(SendLicenceReminders::class)
    ->dailyAt('09:30')
    ->timezone('Africa/Douala')
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground();

// Tenant domains: stamp domain_verified_at once a tenant's own domain answers - hourly
Schedule::command(VerifyEnvironmentDomains::class)
    ->hourly()
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground();
