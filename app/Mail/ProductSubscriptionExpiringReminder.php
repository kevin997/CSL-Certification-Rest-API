<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\ProductSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductSubscriptionExpiringReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProductSubscription $subscription,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        $this->subscription->loadMissing(['environment', 'product', 'user']);

        $branding = $this->resolveBranding();
        $prefix = $this->days <= 0 ? '⚠️ Subscription Expired' : '⏰ Subscription Expiring Soon';

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: $prefix . ': ' . ($this->subscription->product->name ?? 'Subscription'),
        );
    }

    public function content(): Content
    {
        $this->subscription->loadMissing(['environment', 'product', 'user']);

        $branding = $this->resolveBranding();

        $manageUrl = $this->subscription->environment
            ? 'https://' . $this->subscription->environment->primary_domain . '/learners/subscriptions'
            : '#';

        return new Content(
            view: 'emails.product-subscription-expiring',
            with: [
                'subscription' => $this->subscription,
                'days' => $this->days,
                'branding' => $branding,
                'manageUrl' => $manageUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function resolveBranding(): array
    {
        if ($this->subscription->environment) {
            return EmailBrandingHelper::resolve($this->subscription->environment);
        }

        return [
            'company_name' => 'KURSA',
            'primary_color' => '#19682f',
            'secondary_color' => '#f59c00',
            'accent_color' => '#ffb733',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            'logo_url' => asset('images/logo-kursa.svg'),
            'login_url' => '#',
        ];
    }
}
