<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DigitalProductDelivery extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Product $product,
        public Collection $deliveries,
    ) {}

    public function envelope(): Envelope
    {
        $this->order->loadMissing(['environment', 'user']);

        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: '📦 Your Digital Product: ' . $this->product->name,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['environment', 'user']);
        $this->deliveries->loadMissing('productAsset');

        $branding = $this->resolveBranding();

        $dashboardUrl = $this->order->environment
            ? 'https://' . $this->order->environment->primary_domain . '/learners/dashboard'
            : '#';

        return new Content(
            view: 'emails.digital-product-delivery',
            with: [
                'order' => $this->order,
                'product' => $this->product,
                'deliveries' => $this->deliveries,
                'branding' => $branding,
                'dashboardUrl' => $dashboardUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function resolveBranding(): array
    {
        if ($this->order->environment) {
            return EmailBrandingHelper::resolve($this->order->environment);
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
