@component('mail::message', ['environment' => $environment ?? null, 'branding' => $branding ?? null])
# Your Digital Product is Ready!

Hi {{ $order->user->name }},

Thank you for purchasing **{{ $product->name }}**! Your digital resources are now available.

**Order Number:** {{ $order->order_number }}
**Order Date:** {{ $order->created_at->format('F j, Y') }}
**Product:** {{ $product->name }}

## Access Your Resources

@foreach($deliveries as $delivery)
@php
    $asset = $delivery->productAsset;
@endphp

### {{ $asset->title }}

@if($asset->description)
{{ $asset->description }}
@endif

@if($asset->asset_type === 'external_link')
@component('mail::button', ['url' => $asset->external_url])
Access Resource
@endcomponent

**Direct Link:** [{{ $asset->external_url }}]({{ $asset->external_url }})

@elseif($asset->asset_type === 'email_content')

<div style="padding: 20px; background: #f9fafb; border-radius: 8px; margin: 10px 0;">
{!! $asset->email_template !!}
</div>

@endif

---

@endforeach

## Important Information

- **Access Expires:** {{ $deliveries->first()->expires_at->format('F j, Y') }} (30 days from purchase)
- **Download Limit:** {{ $deliveries->first()->max_access_count }} accesses per resource
- **Your Dashboard:** You can always access your purchased resources from your learner dashboard

@component('mail::button', ['url' => $dashboardUrl])
Go to Dashboard
@endcomponent

## Need Help?

If you have any questions or issues accessing your digital product, please contact our support team.

**Your Login Credentials:**
- **Email:** {{ $order->user->email }}
- **Dashboard:** [{{ $dashboardUrl }}]({{ $dashboardUrl }})

Thank you for your purchase!

Best regards,<br>
{{ $branding->company_name ?? $environment->name ?? 'CSL' }}
@endcomponent
