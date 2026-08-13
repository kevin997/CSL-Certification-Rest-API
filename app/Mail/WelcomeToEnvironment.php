<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeToEnvironment extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * IDENTITY UNIFICATION: Password is now optional.
     * When null, the email will instruct user to log in with their existing account.
     */
    public function __construct(
        public User $user,
        public Environment $environment,
        public ?string $password = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: "Welcome to {$branding['company_name']}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Content(
            view: 'emails.welcome-to-environment',
            with: [
                'user' => $this->user,
                'environment' => $this->environment,
                'password' => $this->password,
                'branding' => $branding,
                'loginUrl' => $branding['login_url'],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
