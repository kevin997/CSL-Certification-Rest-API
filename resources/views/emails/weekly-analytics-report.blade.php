@component('emails.partials.email-layout', ['branding' => $branding, 'title' => 'Weekly Analytics Report'])

{{-- Hero Section --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background: linear-gradient(135deg, {{ $branding['primary_color'] }}dd 0%, {{ $branding['primary_color'] }} 100%); padding: 36px 40px; text-align: center;">
            <p style="margin: 0 0 4px; font-size: 14px; font-weight: 500; color: rgba(255,255,255,0.8);">Platform Report</p>
            <h1 style="margin: 0 0 6px; font-weight: 800; font-size: 24px; color: #ffffff;">📊 Weekly Analytics Report</h1>
            <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.7);">{{ $weekStart->format('M j, Y') }} — {{ $weekEnd->format('M j, Y') }}</p>
        </td>
    </tr>
</table>

{{-- Key Metrics Grid --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 28px 40px 8px;">
            <p style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: #1a1a1a;">Key Metrics</p>
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                {{-- Row 1: Registrations + Environments --}}
                <tr>
                    <td style="width: 50%; padding: 6px 6px 6px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #f0faf3; border-radius: 10px; border: 1px solid #d4edda;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">👥 New Registrations</p>
                                <p style="margin: 0; font-size: 26px; font-weight: 800; color: {{ $branding['primary_color'] }};">{{ $metrics['new_registrations']['total'] }}</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 6px 0 6px 6px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #eef6ff; border-radius: 10px; border: 1px solid #c8e0ff;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">🏢 Environments</p>
                                <p style="margin: 0; font-size: 26px; font-weight: 800; color: #0284c7;">{{ $metrics['learning_environments']['total'] }}</p>
                                <p style="margin: 2px 0 0; font-size: 10px; color: #717882;">{{ $metrics['learning_environments']['active'] }} active · +{{ $metrics['learning_environments']['new_this_week'] }} new</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
                {{-- Row 2: Orders + Revenue --}}
                <tr>
                    <td style="width: 50%; padding: 6px 6px 6px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fff8eb; border-radius: 10px; border: 1px solid #ffe4a0;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">🛒 Completed Orders</p>
                                <p style="margin: 0; font-size: 26px; font-weight: 800; color: #d98a00;">{{ $metrics['completed_orders']['count'] }}</p>
                                <p style="margin: 2px 0 0; font-size: 10px; color: #717882;">${{ number_format($metrics['completed_orders']['total_amount'], 2) }} revenue</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 6px 0 6px 6px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fdf2f8; border-radius: 10px; border: 1px solid #f5c6e0;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">💰 Commission</p>
                                <p style="margin: 0; font-size: 26px; font-weight: 800; color: #be185d;">${{ number_format($metrics['total_commissions']['total_fee_amount'], 2) }}</p>
                                <p style="margin: 2px 0 0; font-size: 10px; color: #717882;">{{ $metrics['total_commissions']['transaction_count'] }} transactions</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
                {{-- Row 3: Failed Orders + Pending Invoices --}}
                <tr>
                    <td style="width: 50%; padding: 6px 6px 6px 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: {{ $metrics['failed_orders']['count'] > 0 ? '#fef2f2' : '#fafafa' }}; border-radius: 10px; border: 1px solid {{ $metrics['failed_orders']['count'] > 0 ? '#fecaca' : '#e8e8e8' }};" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">❌ Failed Orders</p>
                                <p style="margin: 0; font-size: 26px; font-weight: 800; color: {{ $metrics['failed_orders']['count'] > 0 ? '#dc2626' : '#717882' }};">{{ $metrics['failed_orders']['count'] }}</p>
                                <p style="margin: 2px 0 0; font-size: 10px; color: #717882;">{{ $metrics['failed_orders']['failure_rate'] }}% failure rate</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 6px 0 6px 6px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; color: #717882; text-transform: uppercase; letter-spacing: 0.05em;">📄 Pending Invoices</p>
                                <p style="margin: 0; font-size: 26px; font-weight: 800; color: #4a4a4a;">{{ $metrics['pending_invoices']['count'] }}</p>
                                <p style="margin: 2px 0 0; font-size: 10px; color: {{ $metrics['pending_invoices']['overdue_count'] > 0 ? '#dc2626' : '#717882' }};">{{ $metrics['pending_invoices']['overdue_count'] }} overdue · ${{ number_format($metrics['pending_invoices']['total_amount'], 2) }}</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Revenue Breakdown --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 16px 40px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 20px 24px;">
                        <p style="margin: 0 0 14px; font-size: 14px; font-weight: 700; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.08em;">💵 Revenue Breakdown</p>
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                            @foreach([
                                ['Gross Revenue', $metrics['revenue_breakdown']['gross_revenue']],
                                ['Commission', $metrics['revenue_breakdown']['commission_revenue']],
                                ['Tax', $metrics['revenue_breakdown']['tax_revenue']],
                            ] as $row)
                            <tr>
                                <td style="padding: 6px 0; border-bottom: 1px solid #e8e8e8; font-size: 13px; color: #717882;">{{ $row[0] }}</td>
                                <td style="padding: 6px 0; border-bottom: 1px solid #e8e8e8; text-align: right; font-size: 14px; font-weight: 600; color: #1a1a1a;">${{ number_format($row[1], 2) }}</td>
                            </tr>
                            @endforeach
                            <tr>
                                <td style="padding: 8px 0 0; font-size: 14px; font-weight: 700; color: #1a1a1a;">Total Revenue</td>
                                <td style="padding: 8px 0 0; text-align: right; font-size: 16px; font-weight: 800; color: {{ $branding['primary_color'] }};">${{ number_format($metrics['revenue_breakdown']['total_revenue'], 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Top Environments --}}
@if(!empty($metrics['top_environments']))
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 16px;">
            <p style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: #1a1a1a;">🏆 Top Performing Environments</p>
            @foreach($metrics['top_environments'] as $i => $env)
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%; margin-bottom: 6px; background-color: #fafafa; border-radius: 8px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 12px 16px; width: 30px; vertical-align: middle;">
                        <span style="display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; background-color: {{ $branding['primary_color'] }}; color: #ffffff; font-size: 11px; font-weight: 700; border-radius: 50%;">{{ $i + 1 }}</span>
                    </td>
                    <td style="padding: 12px 8px; vertical-align: middle;">
                        <p style="margin: 0; font-size: 13px; font-weight: 600; color: #1a1a1a;">{{ $env['name'] }}</p>
                        <p style="margin: 0; font-size: 11px; color: #717882;">{{ $env['domain'] }}</p>
                    </td>
                    <td style="padding: 12px 16px; text-align: right; vertical-align: middle;">
                        <span style="font-size: 14px; font-weight: 700; color: {{ $branding['secondary_color'] }};">{{ $env['orders_count'] }} orders</span>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>
@endif

{{-- Content & Enrollment --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 8px 40px 28px;">
            <table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 50%; padding: 0 6px 0 0; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 10px; font-size: 12px; font-weight: 700; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.06em;">📚 Content</p>
                                <p style="margin: 0; font-size: 13px; color: #4a4a4a;">Templates: <strong>+{{ $metrics['published_templates']['new_this_week'] }}</strong> new ({{ $metrics['published_templates']['total'] }} total)</p>
                                <p style="margin: 4px 0 0; font-size: 13px; color: #4a4a4a;">Courses: <strong>+{{ $metrics['published_courses']['new_this_week'] }}</strong> new ({{ $metrics['published_courses']['total'] }} total)</p>
                            </td></tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding: 0 0 0 6px; vertical-align: top;">
                        <table style="border-collapse: collapse; border-spacing: 0; width: 100%; background-color: #fafafa; border-radius: 10px; border: 1px solid #e8e8e8;" border="0" cellpadding="0" cellspacing="0">
                            <tr><td style="padding: 18px;">
                                <p style="margin: 0 0 10px; font-size: 12px; font-weight: 700; color: {{ $branding['primary_color'] }}; text-transform: uppercase; letter-spacing: 0.06em;">📈 Engagement</p>
                                <p style="margin: 0; font-size: 13px; color: #4a4a4a;">Enrollments: <strong>+{{ $metrics['new_enrollments']['new_this_week'] }}</strong> new</p>
                                <p style="margin: 4px 0 0; font-size: 13px; color: #4a4a4a;">Active users: <strong>{{ $metrics['active_users']['active_this_week'] }}</strong></p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Footer note --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 28px; text-align: center;">
            <p style="margin: 0; font-size: 11px; color: #b9b9bb;">
                Generated automatically on {{ now()->format('M j, Y \a\t g:i A') }}
            </p>
        </td>
    </tr>
</table>

@endcomponent
