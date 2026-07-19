<?php

namespace App\Mail\Licensing;

use App\Helpers\EmailBrandingHelper;
use App\Models\EnvironmentLicence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * White Label trial lifecycle reminder (doc §5): days 0, 7, 12, 14 and the
 * day-17 recovery message.
 */
class TrialReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public EnvironmentLicence $licence,
        public string $marker,
    ) {}

    public function envelope(): Envelope
    {
        $branding = $this->resolveBranding();

        $subject = match ($this->marker) {
            'trial_day_0' => 'Your White Label trial has started',
            'trial_day_7' => 'One week left on your White Label trial',
            'trial_day_12' => 'Your White Label trial ends in 2 days',
            'trial_day_14' => 'Your White Label trial ends today',
            'trial_day_17' => 'Come back to White Label — your academy is waiting',
            default => 'About your KURSA White Label trial',
        };

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $branding = $this->resolveBranding();
        $environment = $this->licence->environment;

        return new Content(
            view: 'emails.licensing.trial-reminder',
            with: [
                'licence' => $this->licence,
                'marker' => $this->marker,
                'branding' => $branding,
                'trialEndsAt' => $this->licence->trial_ends_at,
                'manageUrl' => $environment
                    ? 'https://' . $environment->primary_domain . '/billing'
                    : '#',
            ],
        );
    }

    private function resolveBranding(): array
    {
        $environment = $this->licence->environment;

        if ($environment) {
            return EmailBrandingHelper::resolve($environment);
        }

        return [
            'company_name' => 'KURSA',
            'primary_color' => '#19682f',
            'secondary_color' => '#f59c00',
            'logo_url' => asset('images/logo-kursa.svg'),
        ];
    }
}
