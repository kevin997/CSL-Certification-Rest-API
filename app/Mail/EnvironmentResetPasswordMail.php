<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Environment;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnvironmentResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The environment instance.
     *
     * @var Environment
     */
    public $environment;

    /**
     * The environment-specific email.
     *
     * @var string
     */
    public $environmentEmail;

    /**
     * The user's actual email.
     *
     * @var string
     */
    public $userEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $token, Environment $environment, string $environmentEmail, string $userEmail)
    {
        $this->token = $token;
        $this->environment = $environment;
        $this->environmentEmail = $environmentEmail;
        $this->userEmail = $userEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Envelope(
            subject: 'Reset Password for '.$branding['company_name'],
            from: new Address(config('mail.from.address'), $branding['company_name']),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Content(
            view: 'emails.environment-reset-password',
            with: [
                'resetUrl' => $this->generateResetUrl(),
                'environmentName' => $this->environment->name,
                'environmentEmail' => $this->environmentEmail,
                'branding' => $branding,
                'pendingDomainNotice' => TenantUrl::isLive($this->environment)
                    ? null
                    : sprintf(
                        'Your academy is available at %s now. Once %s is live it will open there.',
                        TenantUrl::base($this->environment),
                        $this->environment->primary_domain,
                    ),
            ],
        );
    }

    /**
     * Generate the reset URL using the environment's primary domain.
     */
    protected function generateResetUrl(): string
    {
        return TenantUrl::to($this->environment, '/auth/reset-password', [
            'token' => $this->token,
            'email' => $this->userEmail,
            'environment_id' => $this->environment->id,
        ]);
    }
}
