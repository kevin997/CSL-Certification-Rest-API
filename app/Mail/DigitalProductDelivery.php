<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DigitalProductDelivery extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var Order
     */
    public $order;

    /**
     * The product instance.
     *
     * @var Product
     */
    public $product;

    /**
     * The asset deliveries collection.
     *
     * @var Collection
     */
    public $deliveries;

    /**
     * Create a new message instance.
     *
     * @param Order $order
     * @param Product $product
     * @param Collection $deliveries
     * @return void
     */
    public function __construct(Order $order, Product $product, Collection $deliveries)
    {
        $this->order = $order;
        $this->product = $product;
        $this->deliveries = $deliveries;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Load the environment relationship if not already loaded
        if (!$this->order->relationLoaded('environment')) {
            $this->order->load('environment');
        }

        // Load the user relationship if not already loaded
        if (!$this->order->relationLoaded('user')) {
            $this->order->load('user');
        }

        // Get the environment name or use 'CSL' as fallback
        $environmentName = $this->order->environment ? $this->order->environment->name : 'CSL';

        // Load environment branding if available
        $branding = null;
        if ($this->order->environment) {
            $branding = \App\Models\Branding::where('environment_id', $this->order->environment->id)
                ->where('is_active', true)
                ->first();
        }

        // Load product asset relationships for deliveries
        $this->deliveries->load('productAsset');

        // Generate dashboard URL
        $dashboardUrl = $this->order->environment
            ? url("https://{$this->order->environment->domain}/dashboard")
            : url('/dashboard');

        return $this->from(config('mail.from.address'), $environmentName)
                    ->subject('Your Digital Product: ' . $this->product->name)
                    ->markdown('emails.digital-product-delivery', [
                        'order' => $this->order,
                        'product' => $this->product,
                        'deliveries' => $this->deliveries,
                        'environment' => $this->order->environment,
                        'branding' => $branding,
                        'dashboardUrl' => $dashboardUrl,
                    ]);
    }
}
