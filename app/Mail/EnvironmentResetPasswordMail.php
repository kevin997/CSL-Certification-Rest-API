<?php

namespace App\Mail;

use App\Models\Environment;
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
            from: new Address("no.reply@cfpcsl.com", $this->environment->name),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Get the active branding for this environment
        $branding = $this->environment->brandings()->where('is_active', true)->first();
        
        // Default branding values if no active branding exists
        $brandingData = [
            'company_name' => $this->environment->name,
            'logo_path' => $this->environment->logo_url,
            'primary_color' => $this->environment->theme_color ?? '#00a5ff',
            'secondary_color' => '#4F46E5',
            'accent_color' => '#10B981',
            'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
        ];
        
        // Override with actual branding if available
        if ($branding) {
            $brandingData = [
                'company_name' => $branding->company_name ?? $brandingData['company_name'],
                'logo_path' => $branding->logo_path ?? $brandingData['logo_path'],
                'primary_color' => $branding->primary_color ?? $brandingData['primary_color'],
                'secondary_color' => $branding->secondary_color ?? $brandingData['secondary_color'],
                'accent_color' => $branding->accent_color ?? $brandingData['accent_color'],
                'font_family' => $branding->font_family ?? $brandingData['font_family'],
                'custom_css' => $branding->custom_css,
            ];
        }
        
        return new Content(
            view: 'emails.environment-reset-password',
            with: [
                'resetUrl' => $this->generateResetUrl(),
                'environmentName' => $this->environment->name,
                'environmentEmail' => $this->environmentEmail,
                'branding' => $brandingData,
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
