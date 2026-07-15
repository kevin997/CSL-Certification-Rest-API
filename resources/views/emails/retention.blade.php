<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $branding['company_name'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: {{ $branding['font_family'] }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color:#ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: {{ $branding['primary_color'] }}; padding: 20px 32px;" align="center">
                            @if (!empty($branding['logo_url']))
                                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['company_name'] }}" height="36" style="display:block; max-height:36px;">
                            @else
                                <span style="color:#ffffff; font-size: 18px; font-weight: bold;">{{ $branding['company_name'] }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; color: #333333; font-size: 15px; line-height: 1.6;">
                            {!! nl2br(e($body)) !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 32px; background-color:#fafafa; color:#999999; font-size: 12px;" align="center">
                            &copy; {{ date('Y') }} {{ $branding['company_name'] }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
