<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Environment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email fallback for a retention nudge, used when a recipient has no
 * WhatsApp number, WhatsApp isn't configured, or the WhatsApp send failed.
 *
 * Deliberately does NOT implement ShouldQueue: SendRetentionMessagesJob sends
 * it synchronously via `Mail::to($email)->send(new RetentionMail(...))`
 * inside a try/catch, so a failure is caught and recorded immediately. If
 * this class implemented ShouldQueue, Laravel's Mailer would silently queue
 * it instead of sending on `send()`, and the try/catch would never see a
 * delivery failure.
 */
class RetentionMail extends Mailable
{
    public function __construct(
        public string $retentionSubject,
        public string $retentionBody,
        public ?Environment $environment = null,
    ) {}

    public function envelope(): Envelope
    {
        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: $this->retentionSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.retention',
            with: [
                'body' => $this->retentionBody,
                'branding' => $this->resolveBranding(),
            ],
        );
    }

    private function resolveBranding(): array
    {
        if ($this->environment) {
            return EmailBrandingHelper::resolve($this->environment);
        }

        return [
            'company_name' => 'KURSA',
            'primary_color' => '#19682f',
            'secondary_color' => '#f59c00',
            'accent_color' => '#ffb733',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            'logo_url' => asset('images/logo-kursa.svg'),
            'login_url' => '#',
        ];
    }
}
