<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>White Label trial</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:{{ $branding['primary_color'] ?? '#19682f' }};padding:24px 32px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:700;">{{ $branding['company_name'] ?? 'KURSA' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @if ($marker === 'trial_day_17')
                                <h1 style="margin:0 0 12px;font-size:22px;">Your academy is still here</h1>
                                <p style="margin:0 0 16px;line-height:1.6;color:#444;">
                                    Your White Label trial ended, and your academy is now on the Free Forever plan.
                                    Nothing was deleted — your courses, learners and certificates are safe.
                                    Upgrade any time to bring back custom domains, full branding and premium tools.
                                </p>
                            @else
                                <h1 style="margin:0 0 12px;font-size:22px;">Your White Label trial</h1>
                                <p style="margin:0 0 16px;line-height:1.6;color:#444;">
                                    @if ($trialEndsAt)
                                        Your 14-day White Label trial ends on
                                        <strong>{{ $trialEndsAt->format('F j, Y') }}</strong>.
                                    @endif
                                    Upgrade before it ends to keep your custom domain, full branding and every premium capability.
                                    If you do nothing, your academy safely returns to Free Forever — no data is ever deleted.
                                </p>
                            @endif
                            <p style="margin:24px 0 0;">
                                <a href="{{ $manageUrl }}" style="display:inline-block;background:{{ $branding['secondary_color'] ?? '#f59c00' }};color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">
                                    Manage my licence
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid #eee;color:#999;font-size:12px;">
                            {{ $branding['company_name'] ?? 'KURSA' }} — 0% commission on every course sale.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
