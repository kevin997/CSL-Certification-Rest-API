<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnvironmentSetupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Environment $environment,
        public User $user,
        public string $adminEmail,
        public string $adminPassword
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: "Your Environment '{$this->environment->name}' is Ready!",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $branding = EmailBrandingHelper::resolve($this->environment);
        $loginUrl = $this->generateLoginUrl();

        return new Content(
            view: 'emails.environment-setup',
            with: [
                'environment' => $this->environment,
                'user' => $this->user,
                'adminEmail' => $this->adminEmail,
                'adminPassword' => $this->adminPassword,
                'isSubdomain' => $this->isSubdomain(),
                'loginUrl' => $loginUrl,
                'domainType' => $this->isSubdomain() ? 'Subdomain' : 'Custom Domain',
                'branding' => $branding,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Check if the environment uses a subdomain or custom domain.
     */
    private function isSubdomain(): bool
    {
        return str_contains($this->environment->primary_domain, '.csl-brands.com')
            || str_contains($this->environment->primary_domain, '.cfpcsl.com');
    }

    /**
     * Generate the login URL for the environment.
     */
    private function generateLoginUrl(): string
    {
        $protocol = app()->environment('production') ? 'https' : 'http';
        return "{$protocol}://{$this->environment->primary_domain}/auth/login";
    }
}
