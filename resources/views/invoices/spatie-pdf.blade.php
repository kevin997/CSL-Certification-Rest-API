<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a1a2e;
            background: #ffffff;
            font-size: 13px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }

        /* ── Header ── */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 3px solid {{ $primaryColor ?? '#4F46E5' }};
        }

        .company-logo img {
            max-height: 56px;
            max-width: 200px;
            object-fit: contain;
        }

        .company-logo .company-name-fallback {
            font-size: 22px;
            font-weight: 700;
            color: {{ $primaryColor ?? '#4F46E5' }};
            letter-spacing: -0.5px;
        }

        .invoice-title-block {
            text-align: right;
        }

        .invoice-title-block h1 {
            font-size: 32px;
            font-weight: 700;
            color: {{ $primaryColor ?? '#4F46E5' }};
            margin-bottom: 4px;
            letter-spacing: -1px;
            text-transform: uppercase;
        }

        .invoice-number {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* ── Status Badge ── */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }

        .status-draft { background: #fef3c7; color: #92400e; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f1f5f9; color: #475569; }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 36px;
        }

        .info-block h3 {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .info-block p {
            font-size: 13px;
            color: #334155;
            line-height: 1.7;
        }

        .info-block .name {
            font-weight: 600;
            font-size: 15px;
            color: #1a1a2e;
        }

        /* ── Invoice Details Bar ── */
        .details-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 36px;
        }

        .detail-item {
            background: #ffffff;
            padding: 16px 20px;
            text-align: center;
        }

        .detail-item:first-child {
            border-radius: 10px 0 0 10px;
        }

        .detail-item:last-child {
            border-radius: 0 10px 10px 0;
        }

        .detail-item .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .detail-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        /* ── Table ── */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .invoice-table thead th {
            background: {{ $primaryColor ?? '#4F46E5' }};
            color: #ffffff;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
        }

        .invoice-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        .invoice-table thead th:last-child {
            border-radius: 0 8px 0 0;
            text-align: right;
        }

        .invoice-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #475569;
        }

        .invoice-table tbody tr:last-child td {
            border-bottom: none;
        }

        .invoice-table tbody tr:hover {
            background: #fafbfc;
        }

        .invoice-table .text-right {
            text-align: right;
        }

        .invoice-table .text-center {
            text-align: center;
        }

        /* ── Totals ── */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 36px;
        }

        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }

        .totals-table tr td {
            padding: 8px 0;
            font-size: 13px;
        }

        .totals-table tr td:first-child {
            color: #64748b;
            font-weight: 500;
        }

        .totals-table tr td:last-child {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
        }

        .totals-table .total-row {
            border-top: 2px solid {{ $primaryColor ?? '#4F46E5' }};
        }

        .totals-table .total-row td {
            padding-top: 12px;
            font-size: 18px;
            font-weight: 700;
        }

        .totals-table .total-row td:last-child {
            color: {{ $primaryColor ?? '#4F46E5' }};
        }

        /* ── Payment Section ── */
        .payment-section {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 36px;
        }

        .payment-section h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .payment-link-btn {
            display: inline-block;
            padding: 12px 32px;
            background: {{ $primaryColor ?? '#4F46E5' }};
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        /* ── Footer ── */
        .invoice-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
            text-align: center;
        }

        .invoice-footer p {
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.8;
        }

        .invoice-footer .thank-you {
            font-size: 14px;
            font-weight: 600;
            color: {{ $primaryColor ?? '#4F46E5' }};
            margin-bottom: 8px;
        }

        /* ── Transaction Details ── */
        .transaction-description {
            font-weight: 500;
            color: #1e293b;
        }

        .transaction-sub {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ── Page break prevention ── */
        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-logo">
                @if(isset($branding) && $branding && $branding->logo_path)
                    @php
                        $logoSrc = str_starts_with($branding->logo_path, 'http')
                            ? $branding->logo_path
                            : url('storage/' . $branding->logo_path);
                    @endphp
                    <img src="{{ $logoSrc }}" alt="{{ $branding->company_name ?? 'Company' }}">
                @elseif(isset($environment) && $environment->logo_url)
                    <img src="{{ $environment->logo_url }}" alt="{{ $environment->name }}">
                @else
                    <div class="company-name-fallback">
                        {{ $branding->company_name ?? $environment->name ?? 'CSL Brands' }}
                    </div>
                @endif
            </div>
            <div class="invoice-title-block">
                <h1>Invoice</h1>
                <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
                @php
                    $statusClass = match(strtolower($invoice->status)) {
                        'paid' => 'status-paid',
                        'sent' => 'status-sent',
                        'overdue' => 'status-overdue',
                        'cancelled' => 'status-cancelled',
                        default => 'status-draft',
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ ucfirst($invoice->status) }}</span>
            </div>
        </div>

        <!-- Bill From / Bill To -->
        <div class="info-grid">
            <div class="info-block">
                <h3>From</h3>
                <p>
                    <span class="name">CSL Brands</span><br>
                    Learning Platform Services<br>
                    billing@cslbrands.com
                </p>
            </div>
            <div class="info-block">
                <h3>Bill To</h3>
                <p>
                    <span class="name">{{ $branding->company_name ?? $environment->name ?? 'Client' }}</span><br>
                    @if(isset($owner) && $owner)
                        {{ $owner->name }}<br>
                        {{ $owner->email }}<br>
                    @endif
                    @if(isset($environment) && $environment->primary_domain)
                        {{ $environment->primary_domain }}
                    @endif
                </p>
            </div>
        </div>

        <!-- Details Bar -->
        <div class="details-bar no-break">
            <div class="detail-item">
                <div class="label">Invoice Date</div>
                <div class="value">{{ $invoice->created_at ? $invoice->created_at->format('M d, Y') : now()->format('M d, Y') }}</div>
            </div>
            <div class="detail-item">
                <div class="label">Due Date</div>
                <div class="value">{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}</div>
            </div>
            <div class="detail-item">
                <div class="label">Period</div>
                <div class="value">{{ $invoice->month ? $invoice->month->format('F Y') : 'N/A' }}</div>
            </div>
            <div class="detail-item">
                <div class="label">Currency</div>
                <div class="value">{{ strtoupper($invoice->currency ?? 'USD') }}</div>
            </div>
        </div>

        <!-- Line Items Table -->
        <table class="invoice-table no-break">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th class="text-center">Transactions</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="transaction-description">Platform Service Fees</div>
                        <div class="transaction-sub">{{ $invoice->month ? $invoice->month->format('F Y') : '' }} — Transaction processing & platform usage</div>
                    </td>
                    <td class="text-center">{{ $invoice->transaction_count ?? 0 }}</td>
                    <td class="text-right">{{ number_format($invoice->total_fee_amount, 2) }} {{ strtoupper($invoice->currency ?? 'USD') }}</td>
                </tr>
                @if(isset($transactions) && $transactions->count() > 0)
                    @foreach($transactions->take(20) as $tx)
                    <tr>
                        <td>
                            <div class="transaction-description">{{ $tx->description ?? 'Transaction' }}</div>
                            <div class="transaction-sub">
                                {{ $tx->gateway_transaction_id ? 'Ref: ' . $tx->gateway_transaction_id : '' }}
                                {{ $tx->customer_email ? ' • ' . $tx->customer_email : '' }}
                            </div>
                        </td>
                        <td class="text-center">—</td>
                        <td class="text-right">{{ number_format($tx->fee_amount, 2) }} {{ strtoupper($tx->currency ?? $invoice->currency ?? 'USD') }}</td>
                    </tr>
                    @endforeach
                    @if($transactions->count() > 20)
                    <tr>
                        <td colspan="3" style="text-align: center; color: #94a3b8; font-style: italic;">
                            ... and {{ $transactions->count() - 20 }} more transactions
                        </td>
                    </tr>
                    @endif
                @endif
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section no-break">
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td>{{ number_format($invoice->total_fee_amount, 2) }} {{ strtoupper($invoice->currency ?? 'USD') }}</td>
                </tr>
                <tr>
                    <td>Tax</td>
                    <td>0.00 {{ strtoupper($invoice->currency ?? 'USD') }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Due</td>
                    <td>{{ number_format($invoice->total_fee_amount, 2) }} {{ strtoupper($invoice->currency ?? 'USD') }}</td>
                </tr>
            </table>
        </div>

        <!-- Payment Section -->
        @if($invoice->payment_link)
        <div class="payment-section no-break">
            <h3>💳 Payment</h3>
            <p style="margin-bottom: 12px; color: #475569; font-size: 13px;">
                Click the button below to pay this invoice securely online.
            </p>
            <a href="{{ $invoice->payment_link }}" class="payment-link-btn">Pay Now — {{ number_format($invoice->total_fee_amount, 2) }} {{ strtoupper($invoice->currency ?? 'USD') }}</a>
        </div>
        @endif

        @if($invoice->paid_at)
        <div class="payment-section no-break" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
            <h3>✅ Payment Received</h3>
            <p style="color: #065f46; font-size: 13px;">
                This invoice was paid on <strong>{{ $invoice->paid_at->format('F d, Y') }}</strong>. Thank you!
            </p>
        </div>
        @endif

        <!-- Footer -->
        <div class="invoice-footer">
            <p class="thank-you">Thank you for your business!</p>
            <p>
                KURSA — Learning Platform Services<br>
                This is a computer-generated invoice. No signature is required.<br>
                Generated on {{ now()->format('F d, Y \a\t H:i') }} UTC
            </p>
        </div>
    </div>
</body>
</html>
