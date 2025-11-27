<?php

namespace App\Mail;

use App\Models\Branding;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        $branding = Branding::where('environment_id', $this->environment->id)
            ->where('is_active', true)
            ->first();

        $companyName = $branding?->company_name ?? $this->environment->name;

        return new Envelope(
            from: new Address(env('MAIL_FROM_ADDRESS', 'no.reply@cfpcsl.com'), $companyName),
            subject: "Welcome to {$companyName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $branding = Branding::where('environment_id', $this->environment->id)
            ->where('is_active', true)
            ->first();

        return new Content(
            markdown: 'emails.welcome-to-environment',
            with: [
                'user' => $this->user,
                'environment' => $this->environment,
                'password' => $this->password,
                'branding' => $branding,
                'loginUrl' => 'https://' . $this->environment->primary_domain . '/auth/login',
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
}
