@component('mail::message', ['environment' => $environment ?? null, 'branding' => $branding ?? null])
# Order Confirmation

Thank you for your order!

**Order Number:** {{ $order->order_number }}  
**Order Date:** {{ $order->created_at->format('F j, Y') }}  
**Total Amount:** {{ number_format($order->total_amount, 2) }} {{ $order->currency }}

## Order Details

@component('mail::table')
| Product | Quantity | Price |
|:--------|:--------:|------:|
@if(isset($order->orderItems) && count($order->orderItems) > 0)
    @foreach($order->orderItems as $item)
        @if(isset($item->product))
        | {{ $item->product->name }} | {{ $item->quantity }} | {{ number_format($item->price, 2) }} {{ $order->currency }} |
        @else
        | Product #{{ $item->product_id }} | {{ $item->quantity }} | {{ number_format($item->price, 2) }} {{ $order->currency }} |
        @endif
    @endforeach
@elseif(isset($order->items) && count($order->items) > 0)
    @foreach($order->items as $item)
        @if(isset($item->product))
        | {{ $item->product->name }} | {{ $item->quantity }} | {{ number_format($item->price, 2) }} {{ $order->currency }} |
        @else
        | Product #{{ $item->product_id }} | {{ $item->quantity }} | {{ number_format($item->price, 2) }} {{ $order->currency }} |
        @endif
    @endforeach
@else
| No items found | - | - |
@endif
@endcomponent

## Billing Information

**Name:** {{ $order->billing_name }}  
**Email:** {{ $order->billing_email }}  
**Address:** {{ $order->billing_address }}  
**City:** {{ $order->billing_city }}  
**State:** {{ $order->billing_state }}  
**Zip/Postal Code:** {{ $order->billing_zip }}  
**Country:** {{ $order->billing_country }}

If you have any questions about your order, please contact our support team.

@component('mail::button', ['url' => 'https://' . $environment->primary_domain . '/learners/orders/' . $order->id])
View Order
@endcomponent

Thank you,<br>
{{ $branding->company_name ?? $environment->name ?? 'CSL' }}
@endcomponent
