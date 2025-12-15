<?php

namespace App\Mail;

use App\Models\Branding;
use App\Models\ProductSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductSubscriptionExpiringReminder extends Mailable
{
    use Queueable, SerializesModels;

    public ProductSubscription $subscription;
    public int $days;

    public function __construct(ProductSubscription $subscription, int $days)
    {
        $this->subscription = $subscription;
        $this->days = $days;
    }

    public function build()
    {
        if (!$this->subscription->relationLoaded('environment')) {
            $this->subscription->load('environment');
        }
        if (!$this->subscription->relationLoaded('product')) {
            $this->subscription->load('product');
        }
        if (!$this->subscription->relationLoaded('user')) {
            $this->subscription->load('user');
        }

        $environmentName = $this->subscription->environment ? $this->subscription->environment->name : 'CSL';

        $branding = null;
        if ($this->subscription->environment) {
            $branding = Branding::where('environment_id', $this->subscription->environment->id)
                ->where('is_active', true)
                ->first();
        }

        $subjectPrefix = $this->days <= 0 ? 'Subscription Expired' : 'Subscription Expiring Soon';

        return $this->from(config('mail.from.address'), $environmentName)
            ->subject($subjectPrefix . ': ' . ($this->subscription->product->name ?? 'Subscription'))
            ->markdown('emails.product-subscription-expiring', [
                'environment' => $this->subscription->environment,
                'branding' => $branding,
                'subscription' => $this->subscription,
                'days' => $this->days,
            ]);
    }
}
