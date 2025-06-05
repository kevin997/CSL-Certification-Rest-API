<?php

namespace App\Mail;

use App\Models\Environment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
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
     * @var \App\Models\Environment
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
        return new Envelope(
            subject: 'Reset Password for ' . $this->environment->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.environment-reset-password',
            with: [
                'resetUrl' => $this->generateResetUrl(),
                'environmentName' => $this->environment->name,
                'environmentEmail' => $this->environmentEmail,
            ],
        );
    }

    /**
     * Generate the reset URL using the environment's primary domain.
     */
    protected function generateResetUrl(): string
    {
        $domain = $this->environment->primary_domain;
        
        // If domain doesn't include protocol, add it
        if (!str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }
        
        // Build the reset URL with the token and email parameters
        return $domain . '/auth/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $this->userEmail,
            'environment_id' => $this->environment->id,
        ]);
    }
}
