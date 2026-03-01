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

class InstructorWeeklyDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Environment $environment,
        public array $stats,
    ) {}

    public function envelope(): Envelope
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: "📊 Your Weekly Campus Report — {$branding['company_name']}",
        );
    }

    public function content(): Content
    {
        $branding = EmailBrandingHelper::resolve($this->environment);

        return new Content(
            view: 'emails.instructor-weekly-digest',
            with: [
                'user' => $this->user,
                'environment' => $this->environment,
                'stats' => $this->stats,
                'branding' => $branding,
                'dashboardUrl' => 'https://' . $this->environment->primary_domain . '/dashboard',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
