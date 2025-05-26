@component('mail::message')
# Order Confirmation

Thank you for your order!

**Order Number:** {{ $order->order_number }}  
**Order Date:** {{ $order->created_at->format('F j, Y') }}  
**Total Amount:** {{ number_format($order->total_amount, 2) }} {{ $order->currency }}

## Order Details

@component('mail::table')
| Product | Quantity | Price |
|:--------|:--------:|------:|
@foreach($order->orderItems as $item)
| {{ $item->product->name }} | {{ $item->quantity }} | {{ number_format($item->price, 2) }} {{ $order->currency }} |
@endforeach
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

@component('mail::button', ['url' => config('app.frontend_url') . '/account/orders/' . $order->id])
View Order
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent
