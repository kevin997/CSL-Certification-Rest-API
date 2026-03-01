<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        $this->order->loadMissing(['environment', 'items.product']);

        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: '✅ Order Confirmation #' . $this->order->order_number,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['environment', 'items.product']);

        $branding = $this->resolveBranding();

        $orderUrl = $this->order->environment
            ? 'https://' . $this->order->environment->primary_domain . '/learners/orders/' . $this->order->id
            : '#';

        return new Content(
            view: 'emails.orders.confirmation',
            with: [
                'order' => $this->order,
                'branding' => $branding,
                'orderUrl' => $orderUrl,
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
