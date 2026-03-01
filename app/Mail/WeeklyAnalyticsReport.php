<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class WeeklyAnalyticsReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $metrics,
        public Carbon $weekStart,
        public Carbon $weekEnd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'KURSA Analytics'),
            subject: 'KURSA — Weekly Analytics Report ' . $this->weekStart->format('M j') . ' to ' . $this->weekEnd->format('M j, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-analytics-report',
            with: [
                'metrics' => $this->metrics,
                'weekStart' => $this->weekStart,
                'weekEnd' => $this->weekEnd,
                'branding' => $this->getDefaultBranding(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function getDefaultBranding(): array
    {
        return [
            'company_name' => 'KURSA',
            'primary_color' => '#19682f',
            'secondary_color' => '#f59c00',
            'accent_color' => '#ffb733',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            'logo_url' => asset('images/logo-kursa.svg'),
        ];
    }
}
