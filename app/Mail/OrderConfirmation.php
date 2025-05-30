<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var Order
     */
    public $order;

    /**
     * Create a new message instance.
     *
     * @param Order $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
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
        
        // Get the environment name or use 'CSL' as fallback
        $environmentName = $this->order->environment ? $this->order->environment->name : 'CSL';
        
        // Load environment branding if available
        $branding = null;
        if ($this->order->environment) {
            $branding = \App\Models\Branding::where('environment_id', $this->order->environment->id)
                ->where('is_active', true)
                ->first();
        }
        
        return $this->from('no.reply@cfpcsl.com', $environmentName)
                    ->subject('Order Confirmation #' . $this->order->order_number)
                    ->markdown('emails.orders.confirmation', [
                        'environment' => $this->order->environment,
                        'branding' => $branding
                    ]);
    }
}
