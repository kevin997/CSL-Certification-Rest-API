@component('emails.partials.email-layout', ['branding' => $branding, 'title' => $days <= 0 ? 'Subscription Expired' : 'Subscription Expiring Soon'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $days <= 0 ? '#dc2626dd' : $branding['secondary_color'] . 'dd' }} 0%, {{ $days <= 0 ? '#dc2626' : $branding['secondary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 8px; font-size: 48px; line-height: 1;">{{ $days <= 0 ? '⚠️' : '⏰' }}</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">
                {{ $days <= 0 ? 'Subscription Expired' : 'Subscription Expiring Soon' }}
            </h1>
            <p style="margin: 0; font-size: 14px; color: rgba(255,255,255,0.85);">
                @if($days <= 0)
                    Your subscription has expired
                @elseif($days === 1)
                    Expires tomorrow
                @else
                    Expires in {{ $days }} days
                @endif
            </p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px 16px; text-align: left;">
            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
                Hi <strong style="color: #1a1a1a;">{{ $subscription->user->name ?? 'there' }}</strong>,
                @if($days <= 0)
                    your subscription has expired. Renew now to continue accessing your resources.
                @elseif($days === 1)
                    your subscription expires <strong style="color: #dc2626;">tomorrow</strong>. Renew now to avoid losing access.
                @else
                    your subscription will expire in <strong style="color: {{ $branding['secondary_color'] }};">{{ $days }} days</strong>.
                @endif
            </p>
        </td>
    </tr>
</table>

{{-- Subscription Details Card --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: {{ $days <= 0 ? '#fef2f2' : '#fff8eb' }}; border-radius: 10px; border: 1px solid {{ $days <= 0 ? '#fecaca' : '#ffe4a0' }};" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 24px 28px;">
                        <p style="margin: 0 0 14px; font-size: 12px; font-weight: 700; color: {{ $days <= 0 ? '#dc2626' : '#d98a00' }}; text-transform: uppercase; letter-spacing: 0.08em;">
                            📋 Subscription Details
                        </p>
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #ffffff; border-radius: 8px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 16px 20px;">
                                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding: 5px 0; font-size: 12px; font-weight: 600; color: #717882; width: 110px;">Product</td>
                                            <td style="padding: 5px 0; font-size: 14px; font-weight: 700; color: #1a1a1a;">{{ $subscription->product->name ?? 'Subscription' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; font-size: 12px; font-weight: 600; color: #717882;">Status</td>
                                            <td style="padding: 5px 0;">
                                                <span style="display: inline-block; font-size: 11px; font-weight: 700; color: #ffffff; background-color: {{ $days <= 0 ? '#dc2626' : ($days <= 3 ? '#d98a00' : $branding['primary_color']) }}; border-radius: 9999px; padding: 3px 12px;">
                                                    {{ $days <= 0 ? 'EXPIRED' : 'ACTIVE' }}
                                                </span>
                                            </td>
                                        </tr>
                                        @if($subscription->ends_at)
                                        <tr>
                                            <td style="padding: 5px 0; font-size: 12px; font-weight: 600; color: #717882;">{{ $days <= 0 ? 'Expired On' : 'Expires On' }}</td>
                                            <td style="padding: 5px 0; font-size: 13px; font-weight: 600; color: {{ $days <= 0 ? '#dc2626' : '#1a1a1a' }};">{{ $subscription->ends_at->format('F j, Y') }}</td>
                                        </tr>
                                        @endif
                                    </table>
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
            <a href="{{ $manageUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 15px; line-height: 1; color: #ffffff; background-color: {{ $days <= 0 ? '#dc2626' : $branding['primary_color'] }}; border-radius: 9999px; padding: 14px 40px;">
                {{ $days <= 0 ? 'Renew Now →' : 'Manage Subscription →' }}
            </a>
            <p style="margin: 16px 0 0; font-size: 12px; color: #b9b9bb;">
                Don't lose access to your learning resources.
            </p>
        </td>
    </tr>
</table>

@endcomponent