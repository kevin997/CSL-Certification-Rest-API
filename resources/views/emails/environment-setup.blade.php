@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Your Environment is Ready'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 48px 40px; text-align: center;">
            <div style="width: 72px; height: 72px; background-color: rgba(245, 156, 0, 0.15); border-radius: 50%; margin: 0 auto 20px; line-height: 72px; font-size: 36px;">
                🎉
            </div>
            <h1 style="margin: 0 0 8px; font-weight: 800; font-size: 26px; line-height: 1.2; color: #ffffff;">Your Campus is Ready!</h1>
            <p style="margin: 0; font-weight: 400; font-size: 15px; line-height: 1.5; color: rgba(255, 255, 255, 0.85);">
                {{ $environment->name }} is set up and waiting for you.
            </p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 36px 40px 16px; text-align: left;">
            <h2 style="margin: 0 0 8px; font-weight: 700; font-size: 20px; color: #1a1a1a;">
                Hello {{ $user->name }},
            </h2>
            <p style="margin: 0; font-size: 15px; line-height: 1.7; color: #717882;">
                Congratulations! Your <strong style="color: {{ $branding['primary_color'] }};">{{ $branding['company_name'] }}</strong>
                learning environment has been successfully created and is ready for use.
            </p>
        </td>
    </tr>
</table>

{{-- Environment Details Card --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 16px 40px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 24px 28px;">
                        <h3 style="margin: 0 0 16px; font-weight: 700; font-size: 14px; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.08em;">
                            🏫 Environment Details
                        </h3>
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8;">
                                    <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Name</span>
                                </td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8; text-align: right;">
                                    <span style="font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $environment->name }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8;">
                                    <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Type</span>
                                </td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e8e8e8; text-align: right;">
                                    <span style="font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $domainType }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;">
                                    <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">URL</span>
                                </td>
                                <td style="padding: 8px 0; text-align: right;">
                                    <a href="{{ $loginUrl }}" style="font-size: 14px; font-weight: 600; color: {{ $branding['primary_color'] }}; text-decoration: none;">{{ $environment->primary_domain }}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Admin Credentials Card --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 16px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #f0faf3; border-radius: 10px; border: 1px solid #d4edda;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 24px 28px;">
                        <h3 style="margin: 0 0 16px; font-weight: 700; font-size: 14px; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.08em;">
                            🔑 Administrator Credentials
                        </h3>
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda;">
                                    <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Email</span>
                                </td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; text-align: right;">
                                    <span style="font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $adminEmail }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;">
                                    <span style="font-size: 13px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Password</span>
                                </td>
                                <td style="padding: 8px 0; text-align: right;">
                                    <span style="font-size: 14px; font-weight: 600; color: #1a1a1a; font-family: monospace; background-color: #ffffff; padding: 2px 8px; border-radius: 4px; border: 1px solid #d4edda;">{{ $adminPassword }}</span>
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
        <td style="padding: 16px 40px; text-align: center;">
            <a href="{{ $loginUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 16px; line-height: 1; color: #1a1a1a; background-color: {{ $branding['secondary_color'] }}; border-radius: 9999px; padding: 16px 48px;">
                Access Your Environment
            </a>
        </td>
    </tr>
</table>

{{-- Setup Status Notice --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #eef6ff; border-radius: 10px; border: 1px solid #c8e0ff;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 20px 24px;">
                        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: #0284c7;">⚡ Setup in Progress</p>
                        <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #4a4a4a;">
                            Your campus at <strong>{{ $environment->primary_domain }}</strong> is being configured automatically and will be available within a few minutes. SSL certificate will be provisioned for secure access.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Getting Started --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: #1a1a1a;">🚀 Getting Started</p>
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                @foreach([
                    ['1', 'Log in to your admin dashboard with the credentials above'],
                    ['2', 'Change your password after first login for security'],
                    ['3', 'Customize your branding, colors, and logo'],
                    ['4', 'Create courses and start building your learning content'],
                    ['5', 'Invite team members to collaborate'],
                ] as $step)
                <tr>
                    <td style="padding: 6px 0; vertical-align: top; width: 28px;">
                        <span style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; background-color: {{ $branding['primary_color'] }}; color: #ffffff; font-size: 11px; font-weight: 700; border-radius: 50;">{{ $step[0] }}</span>
                    </td>
                    <td style="padding: 6px 0 6px 8px; font-size: 13px; color: #4a4a4a; line-height: 1.5;">{{ $step[1] }}</td>
                </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

{{-- Security Notice --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0; font-weight: 500; font-size: 13px; line-height: 1.6; color: #d98a00; background-color: #fff8eb; padding: 12px 20px; border-radius: 8px; border: 1px solid #ffe4a0;">
                🔒 <strong>Security:</strong> Change your password after first login and enable two-factor authentication for enhanced security.
            </p>
        </td>
    </tr>
</table>

{{-- Divider --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px;">
            <div style="height: 1px; background-color: #e8e8e8;"></div>
        </td>
    </tr>
</table>

{{-- Support --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 24px 40px 32px; text-align: center;">
            <p style="margin: 0 0 12px; font-size: 14px; line-height: 1.6; color: #717882;">
                Need help? Join our
                <a href="https://chat.whatsapp.com/E4W3kHnCticCzxYFp66rE4?mode=ac_t" style="color: {{ $branding['primary_color'] }}; font-weight: 600; text-decoration: none;">WhatsApp Support Group</a>
                for immediate assistance!
            </p>
            <p style="margin: 0; font-size: 13px; color: #b9b9bb;">
                Welcome to {{ $branding['company_name'] }}! We're excited to see what you'll build.
            </p>
        </td>
    </tr>
</table>

@endcomponent