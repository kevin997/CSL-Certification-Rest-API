@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Order Confirmation'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 8px; font-size: 48px; line-height: 1;">✅</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">Order Confirmed!</h1>
            <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.7);">Thank you for your purchase</p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px 16px; text-align: left;">
            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
                Hi <strong style="color: #1a1a1a;">{{ $order->billing_name }}</strong>, your payment has been successfully processed.
            </p>
        </td>
    </tr>
</table>

{{-- Order Info Cards --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 33%; padding: 6px 4px 6px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #f0faf3; border-radius: 10px; border: 1px solid #d4edda; text-align: center;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 8px;">
                                <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Order #</p>
                                <p style="margin: 0; font-size: 14px; font-weight: 700; color: {{ $branding['primary_color'] }};">{{ $order->order_number }}</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 33%; padding: 6px 4px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fff8eb; border-radius: 10px; border: 1px solid #ffe4a0; text-align: center;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 8px;">
                                <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Date</p>
                                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #d98a00;">{{ $order->created_at->format('M j, Y') }}</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 33%; padding: 6px 0 6px 4px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #eef6ff; border-radius: 10px; border: 1px solid #c8e0ff; text-align: center;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 8px;">
                                <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Total</p>
                                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #0284c7;">{{ number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Order Items Table --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: #1a1a1a;">🛒 Order Details</p>
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; border-radius: 10px; overflow: hidden; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                {{-- Header Row --}}
                <tr>
                    <td style="padding: 12px 16px; background-color: {{ $branding['primary_color'] }}; font-size: 12px; font-weight: 600; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; text-align: left;">Product</td>
                    <td style="padding: 12px 16px; background-color: {{ $branding['primary_color'] }}; font-size: 12px; font-weight: 600; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; width: 60px;">Qty</td>
                    <td style="padding: 12px 16px; background-color: {{ $branding['primary_color'] }}; font-size: 12px; font-weight: 600; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; text-align: right; width: 100px;">Price</td>
                </tr>
                {{-- Order Items --}}
                @php
                    $items = $order->orderItems ?? $order->items ?? collect();
                @endphp
                @forelse($items as $item)
                <tr>
                    <td style="padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; font-weight: 500; color: #1a1a1a; text-align: left;">
                        {{ $item->product->name ?? 'Product #' . $item->product_id }}
                    </td>
                    <td style="padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #717882; text-align: center;">
                        {{ $item->quantity }}
                    </td>
                    <td style="padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; font-weight: 600; color: #1a1a1a; text-align: right;">
                        {{ number_format($item->price, 2) }} {{ $order->currency }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="padding: 20px 16px; text-align: center; font-size: 13px; color: #717882;">No items found</td>
                </tr>
                @endforelse
                {{-- Total Row --}}
                <tr>
                    <td colspan="2" style="padding: 14px 16px; background-color: #fafafa; font-size: 14px; font-weight: 700; color: #1a1a1a; text-align: left;">Total Paid</td>
                    <td style="padding: 14px 16px; background-color: #fafafa; font-size: 16px; font-weight: 800; color: {{ $branding['primary_color'] }}; text-align: right;">
                        {{ number_format($order->total_amount, 2) }} {{ $order->currency }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Billing Information --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 20px 24px;">
                        <p style="margin: 0 0 12px; font-size: 12px; font-weight: 700; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.08em;">📋 Billing Information</p>
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            @foreach([
                                ['Name', $order->billing_name],
                                ['Email', $order->billing_email],
                                ['Address', $order->billing_address],
                                ['City', $order->billing_city],
                                ['State', $order->billing_state],
                                ['Zip Code', $order->billing_zip],
                                ['Country', $order->billing_country],
                            ] as $row)
                            @if($row[1])
                            <tr>
                                <td style="padding: 4px 0; font-size: 12px; font-weight: 600; color: #717882; width: 90px; vertical-align: top;">{{ $row[0] }}</td>
                                <td style="padding: 4px 0; font-size: 13px; color: #1a1a1a;">{{ $row[1] }}</td>
                            </tr>
                            @endif
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- CTA Button --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 36px; text-align: center;">
            <a href="{{ $orderUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 15px; line-height: 1; color: #1a1a1a; background-color: {{ $branding['secondary_color'] }}; border-radius: 9999px; padding: 14px 40px;">
                View Order →
            </a>
            <p style="margin: 16px 0 0; font-size: 12px; color: #b9b9bb;">
                If you have any questions about your order, please contact our support team.
            </p>
        </td>
    </tr>
</table>

@endcomponent
