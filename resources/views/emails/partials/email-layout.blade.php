{{-- 
    Shared Email Layout — Multikart-inspired, KURSA-branded.
    
    Required variables:
    - $branding (array from EmailBrandingHelper::resolve)
    - $title (string) — email subject/heading
    - $slot — blade section content
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
</head>
<body style="text-align: center; margin: 0; padding: 20px 0; font-family: {{ $branding['font_family'] }}; background-color: #f5f5f5;">
    <table style="border-collapse: collapse; border-spacing: 0; background-color: #ffffff; width: 100%; max-width: 650px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);" align="center" border="0" cellpadding="0" cellspacing="0">
        <tbody>
            {{-- Header with Logo --}}
            <tr>
                <td style="background-color: {{ $branding['primary_color'] }}; padding: 24px 40px; text-align: left;">
                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="left" valign="middle" style="width: 48px;">
                                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['company_name'] }}" style="width: 48px; height: 48px;" />
                            </td>
                            <td align="left" valign="middle" style="padding-left: 14px;">
                                <span style="font-size: 20px; font-weight: 700; color: #ffffff; letter-spacing: -0.01em;">
                                    {{ $branding['company_name'] }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- Accent Bar --}}
            <tr>
                <td style="background: linear-gradient(90deg, {{ $branding['secondary_color'] }}, {{ $branding['accent_color'] }}); height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
            </tr>

            {{-- Body Content (slot) --}}
            <tr>
                <td>
                    {{ $slot }}
                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="background-color: #1a1a1a; padding: 28px 40px; text-align: center;">
                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center">
                                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['company_name'] }}" style="width: 32px; height: 32px; margin-bottom: 12px;" />
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <p style="margin: 0 0 6px; font-weight: 600; font-size: 13px; color: #ffffff;">
                                    {{ $branding['company_name'] }}
                                </p>
                                <p style="margin: 0; font-weight: 400; font-size: 11px; line-height: 1.6; color: #717882;">
                                    &copy; {{ date('Y') }} {{ $branding['company_name'] }}. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Unsubscribe footer --}}
    <table style="border-collapse: collapse; border-spacing: 0; width: 100%; max-width: 650px;" align="center" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 16px; text-align: center;">
                <p style="margin: 0; font-size: 11px; color: #b9b9bb;">
                    You received this email because you have an account with {{ $branding['company_name'] }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
