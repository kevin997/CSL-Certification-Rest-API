<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome to {{ $branding?->company_name ?? $environment->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet" />
</head>

<body style="text-align: center; margin: 0; padding: 20px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
    <table style="border-collapse: collapse; border-spacing: 0; background-color: #ffffff; width: 100%; max-width: 650px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);" align="center" border="0" cellpadding="0" cellspacing="0">
        <tbody>
            {{-- Header with Logo --}}
            <tr>
                <td style="background-color: #19682f; padding: 28px 40px; text-align: left;">
                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="left" valign="middle">
                                <img src="{{ $logoUrl }}" alt="{{ $branding?->company_name ?? $environment->name }}" style="width: 48px; height: 48px;" />
                            </td>
                            <td align="left" valign="middle" style="padding-left: 14px;">
                                <span style="font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: -0.01em;">
                                    {{ $branding?->company_name ?? $environment->name }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- Accent Bar --}}
            <tr>
                <td style="background: linear-gradient(90deg, #f59c00, #ffb733); height: 4px; font-size: 0; line-height: 0;">
                    &nbsp;
                </td>
            </tr>

            {{-- Welcome Banner Section --}}
            <tr>
                <td style="background: linear-gradient(135deg, #145524 0%, #19682f 50%, #2a8a42 100%); padding: 48px 40px; text-align: center;">
                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center">
                                {{-- Welcome Icon Circle --}}
                                <div style="width: 72px; height: 72px; background-color: rgba(245, 156, 0, 0.15); border-radius: 50%; margin: 0 auto 20px; line-height: 72px; font-size: 36px;">
                                    🎉
                                </div>
                                <h1 style="margin: 0 0 8px; font-weight: 800; font-size: 28px; line-height: 1.2; color: #ffffff; letter-spacing: -0.01em;">
                                    Welcome Aboard!
                                </h1>
                                <p style="margin: 0; font-weight: 400; font-size: 16px; line-height: 1.5; color: rgba(255, 255, 255, 0.85);">
                                    Your account is ready. Let's get started.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- Main Content Section --}}
            <tr>
                <td style="padding: 40px 40px 16px; text-align: left;">
                    <h2 style="margin: 0 0 8px; font-weight: 700; font-size: 20px; line-height: 1.3; color: #1a1a1a;">
                        Hi {{ $user->name }},
                    </h2>
                    <p style="margin: 0; font-weight: 400; font-size: 15px; line-height: 1.7; color: #717882;">
                        Thank you for joining <strong style="color: #19682f;">{{ $branding?->company_name ?? $environment->name }}</strong>.
                        We're excited to have you on board! Your account has been created and you can now access the learning environment.
                    </p>
                </td>
            </tr>

            {{-- Login Credentials Card --}}
            <tr>
                <td style="padding: 16px 40px;">
                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding: 24px 28px;">
                                <h3 style="margin: 0 0 16px; font-weight: 700; font-size: 14px; line-height: 1; color: #19682f; text-transform: uppercase; letter-spacing: 0.08em;">
                                    🔑 Your Login Details
                                </h3>
                                <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8;">
                                            <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Email</span>
                                        </td>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8; text-align: right;">
                                            <span style="font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $user->email }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8;">
                                            <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Password</span>
                                        </td>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8; text-align: right;">
                                            @if($password)
                                                <span style="font-size: 14px; font-weight: 600; color: #1a1a1a; font-family: monospace; background-color: #ffffff; padding: 2px 8px; border-radius: 4px; border: 1px solid #e8e8e8;">{{ $password }}</span>
                                            @else
                                                <span style="font-size: 14px; font-weight: 500; color: #717882; font-style: italic;">Use your existing account password</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Login URL</span>
                                        </td>
                                        <td style="padding: 8px 0; text-align: right;">
                                            <a href="{{ $loginUrl }}" style="font-size: 14px; font-weight: 600; color: #19682f; text-decoration: none;">{{ $loginUrl }}</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- CTA Button --}}
            <tr>
                <td style="padding: 24px 40px; text-align: center;">
                    <a href="{{ $loginUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 16px; line-height: 1; color: #1a1a1a; background-color: #f59c00; border-radius: 9999px; padding: 16px 48px; transition: background-color 0.2s ease;">
                        Login to Your Account
                    </a>
                </td>
            </tr>

            {{-- Security Notice --}}
            <tr>
                <td style="padding: 0 40px 32px; text-align: center;">
                    @if($password)
                        <p style="margin: 0; font-weight: 500; font-size: 13px; line-height: 1.6; color: #d98a00; background-color: #fff8eb; padding: 12px 20px; border-radius: 8px; border: 1px solid #ffe4a0;">
                            🔒 For security reasons, we recommend changing your password after your first login.
                        </p>
                    @else
                        <p style="margin: 0; font-weight: 400; font-size: 13px; line-height: 1.6; color: #717882;">
                            You can log in using the same password you use for your account.
                        </p>
                    @endif
                </td>
            </tr>

            {{-- Divider --}}
            <tr>
                <td style="padding: 0 40px;">
                    <div style="height: 1px; background-color: #e8e8e8;"></div>
                </td>
            </tr>

            {{-- Help Section --}}
            <tr>
                <td style="padding: 28px 40px;">
                    <p style="margin: 0; font-weight: 400; font-size: 14px; line-height: 1.7; text-align: center; color: #717882;">
                        If you have any questions or need assistance, please don't hesitate to contact our support team. We're here to help!
                    </p>
                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="background-color: #1a1a1a; padding: 32px 40px; text-align: center;">
                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center">
                                <img src="{{ $logoUrl }}" alt="{{ $branding?->company_name ?? $environment->name }}" style="width: 36px; height: 36px; margin-bottom: 16px;" />
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <p style="margin: 0 0 8px; font-weight: 600; font-size: 14px; color: #ffffff;">
                                    {{ $branding?->company_name ?? $environment->name }}
                                </p>
                                <p style="margin: 0; font-weight: 400; font-size: 12px; line-height: 1.6; color: #717882;">
                                    &copy; {{ date('Y') }} {{ $branding?->company_name ?? $environment->name }}. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Email Client Spacing --}}
    <table style="border-collapse: collapse; border-spacing: 0; width: 100%; max-width: 650px;" align="center" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 20px; text-align: center;">
                <p style="margin: 0; font-size: 11px; color: #b9b9bb;">
                    This email was sent to {{ $user->email }}. If you didn't create an account, please ignore this email.
                </p>
            </td>
        </tr>
    </table>
</body>

</html>