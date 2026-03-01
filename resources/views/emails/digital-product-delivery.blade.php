@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Your Digital Product'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 8px; font-size: 48px; line-height: 1;">📦</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">Your Product is Ready!</h1>
            <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.7);">Access your digital resources below</p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px 16px; text-align: left;">
            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
                Hi <strong style="color: #1a1a1a;">{{ $order->user->name }}</strong>, thank you for purchasing
                <strong style="color: {{ $branding['primary_color'] }};">{{ $product->name }}</strong>!
                Your digital resources are now available.
            </p>
        </td>
    </tr>
</table>

{{-- Order Summary Cards --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 50%; padding: 6px 6px 6px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #f0faf3; border-radius: 10px; border: 1px solid #d4edda;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Order #</p>
                                <p style="margin: 0; font-size: 15px; font-weight: 700; color: {{ $branding['primary_color'] }};">{{ $order->order_number }}</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 6px 0 6px 6px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fff8eb; border-radius: 10px; border: 1px solid #ffe4a0;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Purchase Date</p>
                                <p style="margin: 0; font-size: 15px; font-weight: 700; color: #d98a00;">{{ $order->created_at->format('M j, Y') }}</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Digital Resources --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0 0 14px; font-size: 14px; font-weight: 700; color: #1a1a1a;">🎁 Your Resources</p>

            @foreach($deliveries as $delivery)
            @php
                $asset = $delivery->productAsset;
            @endphp
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; margin-bottom: 12px; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 20px 24px;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <p style="margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #1a1a1a;">{{ $asset->title }}</p>
                                    @if($asset->description)
                                    <p style="margin: 0 0 12px; font-size: 13px; color: #717882; line-height: 1.5;">{{ $asset->description }}</p>
                                    @endif

                                    @if($asset->asset_type === 'external_link')
                                    <a href="{{ $asset->external_url }}" style="display: inline-block; text-decoration: none; font-weight: 600; font-size: 13px; color: #ffffff; background-color: {{ $branding['primary_color'] }}; border-radius: 6px; padding: 10px 24px;">
                                        Access Resource →
                                    </a>
                                    <p style="margin: 8px 0 0; font-size: 11px; color: #b9b9bb;">
                                        {{ $asset->external_url }}
                                    </p>
                                    @elseif($asset->asset_type === 'email_content')
                                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #ffffff; border-radius: 8px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding: 16px 20px; font-size: 13px; color: #4a4a4a; line-height: 1.6;">
                                                {!! $asset->email_template !!}
                                            </td>
                                        </tr>
                                    </table>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>

{{-- Access Info --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #eef6ff; border-radius: 10px; border: 1px solid #c8e0ff;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 20px 24px;">
                        <p style="margin: 0 0 10px; font-size: 12px; font-weight: 700; color: #0284c7; text-transform: uppercase; letter-spacing: 0.08em;">ℹ️ Important Information</p>
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            @if($deliveries->first() && $deliveries->first()->expires_at)
                            <tr>
                                <td style="padding: 3px 0; font-size: 13px; color: #4a4a4a;">
                                    <strong>Access Expires:</strong> {{ $deliveries->first()->expires_at->format('F j, Y') }}
                                </td>
                            </tr>
                            @endif
                            @if($deliveries->first() && $deliveries->first()->max_access_count > 0)
                            <tr>
                                <td style="padding: 3px 0; font-size: 13px; color: #4a4a4a;">
                                    <strong>Download Limit:</strong> {{ $deliveries->first()->max_access_count }} accesses per resource
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td style="padding: 3px 0; font-size: 13px; color: #4a4a4a;">
                                    <strong>Your Email:</strong> {{ $order->user->email }}
                                </td>
                            </tr>
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
            <a href="{{ $dashboardUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 15px; line-height: 1; color: #1a1a1a; background-color: {{ $branding['secondary_color'] }}; border-radius: 9999px; padding: 14px 40px;">
                Go to Dashboard →
            </a>
            <p style="margin: 16px 0 0; font-size: 12px; color: #b9b9bb;">
                You can always access your purchased resources from your learner dashboard.
            </p>
        </td>
    </tr>
</table>

@endcomponent
