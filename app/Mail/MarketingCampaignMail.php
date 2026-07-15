<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Platform-level marketing campaign email (AI-generated content, KURSA
 * branding — not tied to a specific environment).
 */
class MarketingCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailHtml,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing-campaign',
            with: [
                'html' => $this->emailHtml,
                'branding' => $this->resolveBranding(),
                'title' => $this->emailSubject,
                'user' => $this->user,
                'unsubscribeUrl' => URL::signedRoute('marketing.unsubscribe', ['user' => $this->user->id]),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    /**
     * Platform-level campaign — there is no environment to brand against, so
     * this mirrors AutomationMail/ProductSubscriptionExpiringReminder's
     * fallback-to-KURSA-defaults pattern (EmailBrandingHelper::resolve()
     * requires a non-null Environment, so it cannot be called here).
     */
    private function resolveBranding(): array
    {
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
