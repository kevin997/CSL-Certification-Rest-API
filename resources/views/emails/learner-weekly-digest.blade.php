@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Your Learning Progress'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 4px; font-size: 14px; font-weight: 500; color: rgba(255,255,255,0.8);">Weekly Progress</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">📚 Keep Up the Great Work!</h1>
            <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.7);">{{ now()->subDays(7)->format('M d') }} — {{ now()->format('M d, Y') }}</p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px 16px; text-align: left;">
            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
                Hi <strong style="color: #1a1a1a;">{{ $user->name }}</strong>, here's your learning summary for
                <strong style="color: {{ $branding['primary_color'] }};">{{ $branding['company_name'] }}</strong> this week.
            </p>
        </td>
    </tr>
</table>

{{-- Quick Stats --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 33%; padding: 8px 4px 8px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #f0faf3; border-radius: 10px; border: 1px solid #d4edda; text-align: center;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 8px;">
                                <p style="margin: 0; font-size: 24px; font-weight: 800; color: {{ $branding['primary_color'] }};">{{ $stats['activities_completed'] }}</p>
                                <p style="margin: 4px 0 0; font-size: 11px; font-weight: 600; color: #717882;">Activities Done</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 33%; padding: 8px 4px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fff8eb; border-radius: 10px; border: 1px solid #ffe4a0; text-align: center;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 8px;">
                                <p style="margin: 0; font-size: 24px; font-weight: 800; color: #d98a00;">{{ $stats['active_enrollments'] }}</p>
                                <p style="margin: 4px 0 0; font-size: 11px; font-weight: 600; color: #717882;">Active Courses</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 33%; padding: 8px 0 8px 4px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fdf2f8; border-radius: 10px; border: 1px solid #f5c6e0; text-align: center;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 16px 8px;">
                                <p style="margin: 0; font-size: 24px; font-weight: 800; color: #be185d;">{{ $stats['certificates_earned'] }}</p>
                                <p style="margin: 4px 0 0; font-size: 11px; font-weight: 600; color: #717882;">Certificates</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Course Progress Bars --}}
@if(!empty($stats['courses']))
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: #1a1a1a;">📖 Your Courses</p>
            @foreach($stats['courses'] as $course)
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; margin-bottom: 12px; background-color: #fafafa; border-radius: 8px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 16px 20px;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align: left;">
                                    <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $course['title'] }}</p>
                                </td>
                                <td style="text-align: right;">
                                    <p style="margin: 0 0 8px; font-size: 13px; font-weight: 700; color: {{ $branding['primary_color'] }};">{{ $course['progress'] }}%</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    {{-- Progress bar --}}
                                    <table style="border-collapse: collapse; border-spacing: 0; width: 100%; height: 8px; background-color: #e8e8e8; border-radius: 4px; overflow: hidden;" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="width: {{ $course['progress'] }}%; background-color: {{ $branding['primary_color'] }}; border-radius: 4px; height: 8px; font-size: 0;">&nbsp;</td>
                                            @if($course['progress'] < 100)
                                            <td style="height: 8px; font-size: 0;">&nbsp;</td>
                                            @endif
                                        </tr>
                                    </table>
                                    <p style="margin: 6px 0 0; font-size: 11px; color: #717882;">
                                        {{ $course['status'] === 'completed' ? '✅ Completed' : 'In Progress' }}
                                    </p>
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
@endif

{{-- Upcoming Deadlines --}}
@if(!empty($stats['upcoming_deadlines']))
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: #1a1a1a;">⏰ Upcoming Deadlines</p>
            @foreach($stats['upcoming_deadlines'] as $deadline)
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; margin-bottom: 8px; background-color: #fff8eb; border-radius: 8px; border: 1px solid #ffe4a0;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 14px 20px;">
                        <p style="margin: 0 0 2px; font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $deadline['title'] }}</p>
                        <p style="margin: 0; font-size: 12px; color: #d98a00;">Due: {{ $deadline['due_date'] }} · {{ $deadline['course'] }}</p>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>
@endif

{{-- CTA Button --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 36px; text-align: center;">
            <a href="{{ $loginUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 15px; line-height: 1; color: #1a1a1a; background-color: {{ $branding['secondary_color'] }}; border-radius: 9999px; padding: 14px 40px;">
                Continue Learning →
            </a>
        </td>
    </tr>
</table>

@endcomponent
