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
 * Failed-renewal / grace-period warning (doc §5, §12). Sent while a paid
 * licence is past-due or in its grace window, before it downgrades to Free.
 */
class LicenceRenewalWarningMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public EnvironmentLicence $licence) {}

    public function envelope(): Envelope
    {
        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: 'Action needed: renew your KURSA licence to keep premium features',
        );
    }

    public function content(): Content
    {
        $branding = $this->resolveBranding();
        $environment = $this->licence->environment;

        return new Content(
            view: 'emails.licensing.renewal-warning',
            with: [
                'licence' => $this->licence,
                'branding' => $branding,
                'graceEndsAt' => $this->licence->grace_ends_at,
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
