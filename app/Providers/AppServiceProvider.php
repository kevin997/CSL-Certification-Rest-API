<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Spatie\Backup\Events\BackupZipWasCreated;
use App\Listeners\MailBackupWithAttachment;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        
        // Register PHPMailer transport
        Mail::extend('phpmailer', function (array $config = []) {
            return new \App\Mail\PHPMailerTransport($config);
        });
        
        // Register backup email with attachment listener
        Event::listen(
            BackupZipWasCreated::class,
            MailBackupWithAttachment::class
        );
    }
}
