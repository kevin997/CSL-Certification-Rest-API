@component('mail::message', ['environment' => $environment ?? null, 'branding' => $branding ?? null])
# {{ ($days ?? 0) <= 0 ? 'Subscription Expired' : 'Subscription Expiring Soon' }}

@if(($days ?? 0) <= 0)
    Your subscription has expired.
    @elseif(($days ?? 0)===1)
    Your subscription will expire in **1 day**.
    @else
    Your subscription will expire in **{{ $days }} days**.
    @endif

    @if(isset($subscription))
    **Subscription:** {{ $subscription->product->name ?? 'Subscription' }}
    **Ends At:** {{ optional($subscription->ends_at)->format('F j, Y') }}
    @endif

    @if(isset($environment) && $environment && !empty($environment->primary_domain))
    @component('mail::button', ['url' => 'https://' . $environment->primary_domain . '/learners/subscriptions'])
    Manage Subscription
    @endcomponent
    @endif

    Thank you,<br>
    {{ $branding->company_name ?? $environment->name ?? 'CSL' }}
    @endcomponent