<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Environment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic branded email for marketing automations. Subject and body are
 * instructor-authored templates with placeholders already substituted.
 */
class AutomationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $automationSubject,
        public string $automationBody,
        public ?Environment $environment = null,
    ) {}

    public function envelope(): Envelope
    {
        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: $this->automationSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.automation',
            with: [
                'body' => $this->automationBody,
                'branding' => $this->resolveBranding(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
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
