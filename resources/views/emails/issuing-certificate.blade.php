@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Certificate Issued'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 8px; font-size: 48px; line-height: 1;">🎓</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">Congratulations!</h1>
            <p style="margin: 0; font-size: 14px; color: rgba(255,255,255,0.8);">Your certificate is ready</p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px 16px; text-align: left;">
            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
                Hi <strong style="color: #1a1a1a;">{{ $user->name ?? 'Learner' }}</strong>, you've earned your certificate for
                <strong style="color: {{ $branding['primary_color'] }};">{{ $certificate->certificateContent->title ?? 'your course' }}</strong>!
            </p>
        </td>
    </tr>
</table>

{{-- Certificate Details Card --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background: linear-gradient(135deg, #f0faf3 0%, #eef6ff 100%); border-radius: 12px; border: 1px solid #d4edda;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 28px 28px;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align: center; padding-bottom: 16px;">
                                    <p style="margin: 0 0 4px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.08em;">Certificate of Completion</p>
                                    <p style="margin: 0; font-size: 20px; font-weight: 800; color: {{ $branding['primary_color'] }};">{{ $certificate->certificateContent->title ?? 'Certificate' }}</p>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #ffffff; border-radius: 8px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding: 16px 20px;">
                                                <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="padding: 4px 0; font-size: 12px; font-weight: 600; color: #717882; width: 110px;">Recipient</td>
                                                        <td style="padding: 4px 0; font-size: 13px; font-weight: 600; color: #1a1a1a;">{{ $user->name ?? 'Learner' }}</td>
                                                    </tr>
                                                    @if($certificate->certificate_number)
                                                    <tr>
                                                        <td style="padding: 4px 0; font-size: 12px; font-weight: 600; color: #717882;">Access Code</td>
                                                        <td style="padding: 4px 0; font-size: 13px; font-weight: 700; color: {{ $branding['primary_color'] }}; font-family: monospace; letter-spacing: 0.05em;">{{ $certificate->certificate_number }}</td>
                                                    </tr>
                                                    @endif
                                                    @if($certificate->issued_date)
                                                    <tr>
                                                        <td style="padding: 4px 0; font-size: 12px; font-weight: 600; color: #717882;">Issued Date</td>
                                                        <td style="padding: 4px 0; font-size: 13px; color: #1a1a1a;">{{ $certificate->issued_date->format('F j, Y') }}</td>
                                                    </tr>
                                                    @endif
                                                    @if($certificate->expiry_date)
                                                    <tr>
                                                        <td style="padding: 4px 0; font-size: 12px; font-weight: 600; color: #717882;">Expires</td>
                                                        <td style="padding: 4px 0; font-size: 13px; color: #d98a00;">{{ $certificate->expiry_date->format('F j, Y') }}</td>
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
        </td>
    </tr>
</table>

{{-- Action Buttons --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 36px; text-align: center;">
            @if($previewUrl)
            <a href="{{ $previewUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 15px; line-height: 1; color: #1a1a1a; background-color: {{ $branding['secondary_color'] }}; border-radius: 9999px; padding: 14px 40px;">
                View Certificate →
            </a>
            @endif
            @if($fileUrl)
            <div style="margin-top: 12px;">
                <a href="{{ $fileUrl }}" style="display: inline-block; text-decoration: none; font-weight: 600; font-size: 13px; color: {{ $branding['primary_color'] }}; border: 2px solid {{ $branding['primary_color'] }}; border-radius: 9999px; padding: 10px 32px;">
                    📄 Download PDF
                </a>
            </div>
            @endif
            <p style="margin: 20px 0 0; font-size: 12px; color: #b9b9bb;">
                Share your achievement with others — you earned it!
            </p>
        </td>
    </tr>
</table>

@endcomponent
