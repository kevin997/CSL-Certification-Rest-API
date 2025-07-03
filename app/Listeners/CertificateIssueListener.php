<?php

namespace App\Listeners;

use App\Events\CertificateIssued;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\CertificateIssue;
use App\Notifications\CertificateIsuued;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CertificateIssueListener implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        // Constructor is empty as we don't need any dependencies injected
    }

    /**
     * Handle the event.
     */
    public function handle(CertificateIssued $event): void
    {
        $issuedCertificate = $event->issuedCertificate;
        try {
            // Ensure relationships are loaded
            $issuedCertificate->loadMissing(['user', 'environment']);
            $user = $issuedCertificate->user;
            $environment = $issuedCertificate->environment;

            // Send certificate by email
            if ($user && $user->email) {
                Mail::to($user->email)->send(new CertificateIssue($issuedCertificate));
                Log::info('Certificate email sent to user', ['user_id' => $user->id, 'certificate_id' => $issuedCertificate->id]);
            } else {
                Log::warning('User or user email missing for certificate email', ['certificate_id' => $issuedCertificate->id]);
            }

            // Send certificate notification to Telegram
            try {
                $telegramService = app(TelegramService::class);
                $user->notify(new CertificateIsuued($user, $environment, $telegramService));
                Log::info('Certificate Telegram notification sent', ['user_id' => $user->id, 'certificate_id' => $issuedCertificate->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send Telegram notification for certificate', [
                    'certificate_id' => $issuedCertificate->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle CertificateIssued event', [
                'certificate_id' => $issuedCertificate->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
