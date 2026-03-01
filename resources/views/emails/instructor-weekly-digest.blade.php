@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Weekly Campus Report'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 4px; font-size: 14px; font-weight: 500; color: rgba(255,255,255,0.8);">Weekly Report</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">📊 Your Campus This Week</h1>
            <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.7);">{{ now()->subDays(7)->format('M d') }} — {{ now()->format('M d, Y') }}</p>
        </td>
    </tr>
</table>

{{-- Greeting --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px 16px; text-align: left;">
            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
                Hi <strong style="color: #1a1a1a;">{{ $user->name }}</strong>, here's what happened on
                <strong style="color: {{ $branding['primary_color'] }};">{{ $branding['company_name'] }}</strong> this week.
            </p>
        </td>
    </tr>
</table>

{{-- Stats Grid --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                {{-- Row 1: Learners + Enrollments --}}
                <tr>
                    <td style="width: 50%; padding: 8px 8px 8px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #f0faf3; border-radius: 10px; border: 1px solid #d4edda;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 20px;">
                                    <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">👥 New Learners</p>
                                    <p style="margin: 0; font-size: 28px; font-weight: 800; color: {{ $branding['primary_color'] }};">{{ $stats['new_learners'] }}</p>
                                    <p style="margin: 4px 0 0; font-size: 11px; color: #717882;">Total: {{ $stats['total_learners'] }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 8px 0 8px 8px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fff8eb; border-radius: 10px; border: 1px solid #ffe4a0;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 20px;">
                                    <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">📝 Enrollments</p>
                                    <p style="margin: 0; font-size: 28px; font-weight: 800; color: #d98a00;">{{ $stats['new_enrollments'] }}</p>
                                    <p style="margin: 4px 0 0; font-size: 11px; color: #717882;">This week</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                {{-- Row 2: Completions + Certificates --}}
                <tr>
                    <td style="width: 50%; padding: 8px 8px 8px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #eef6ff; border-radius: 10px; border: 1px solid #c8e0ff;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 20px;">
                                    <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">✅ Completions</p>
                                    <p style="margin: 0; font-size: 28px; font-weight: 800; color: #0284c7;">{{ $stats['completions'] }}</p>
                                    <p style="margin: 4px 0 0; font-size: 11px; color: #717882;">Courses completed</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 8px 0 8px 8px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fdf2f8; border-radius: 10px; border: 1px solid #f5c6e0;" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 20px;">
                                    <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">🏅 Certificates</p>
                                    <p style="margin: 0; font-size: 28px; font-weight: 800; color: #be185d;">{{ $stats['certificates_issued'] }}</p>
                                    <p style="margin: 4px 0 0; font-size: 11px; color: #717882;">Issued this week</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Top Course --}}
@if(!empty($stats['top_course']))
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 20px 24px;">
                        <p style="margin: 0 0 8px; font-size: 12px; font-weight: 700; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.08em;">🏆 Top Course This Week</p>
                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 700; color: #1a1a1a;">{{ $stats['top_course']['title'] }}</p>
                        <p style="margin: 0; font-size: 13px; color: #717882;">{{ $stats['top_course']['enrollments'] }} enrollments · {{ $stats['top_course']['completion_rate'] }}% completion rate</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endif

{{-- Upcoming Events --}}
@if(!empty($stats['upcoming_events']))
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px;">
            <p style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: #1a1a1a;">📅 Upcoming Events</p>
            @foreach($stats['upcoming_events'] as $event)
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; margin-bottom: 8px; background-color: #fafafa; border-radius: 8px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 14px 20px;">
                        <p style="margin: 0 0 2px; font-size: 14px; font-weight: 600; color: #1a1a1a;">{{ $event['title'] }}</p>
                        <p style="margin: 0; font-size: 12px; color: #717882;">{{ $event['date'] }} · {{ $event['registrations'] }} registrations</p>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>
@endif

{{-- Feedback --}}
@if(isset($stats['avg_rating']) && $stats['avg_rating'] > 0)
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 24px; text-align: center;">
            <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">Average Learner Rating</p>
            <p style="margin: 0; font-size: 32px; font-weight: 800; color: {{ $branding['secondary_color'] }};">{{ number_format($stats['avg_rating'], 1) }}/5</p>
        </td>
    </tr>
</table>
@endif

{{-- CTA Button --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 36px; text-align: center;">
            <a href="{{ $dashboardUrl }}" style="display: inline-block; text-decoration: none; font-weight: 700; font-size: 15px; line-height: 1; color: #1a1a1a; background-color: {{ $branding['secondary_color'] }}; border-radius: 9999px; padding: 14px 40px;">
                View Full Dashboard →
            </a>
        </td>
    </tr>
</table>

@endcomponent
