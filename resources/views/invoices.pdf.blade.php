<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
        .logo { height: 60px; }
        .company-info { font-size: 1.1em; }
        .invoice-title { font-size: 2em; font-weight: bold; margin-bottom: 8px; }
        .section { margin-bottom: 24px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; }
        .table th { background: #f5f5f5; }
        .right { text-align: right; }
        .mb-2 { margin-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            @if(isset($branding) && $branding->logo_url)
                <img src="{{ $branding->logo_url }}" alt="Logo" class="logo">
            @elseif(isset($environment) && $environment->logo_url)
                <img src="{{ $environment->logo_url }}" alt="Logo" class="logo">
            @else
                <div class="company-info">
                    <strong>{{ $branding->company_name ?? $environment->name ?? 'Company' }}</strong>
                </div>
            @endif
            <div class="company-info">
                {{ $branding->company_name ?? $environment->name ?? 'Company' }}<br>
                @if(isset($branding) && $branding->address)
                    {{ $branding->address }}<br>
                @elseif(isset($environment) && $environment->address)
                    {{ $environment->address }}<br>
                @endif
                @if(isset($branding) && $branding->email)
                    {{ $branding->email }}<br>
                @elseif(isset($environment) && $environment->email)
                    {{ $environment->email }}<br>
                @endif
                @if(isset($branding) && $branding->phone)
                    {{ $branding->phone }}<br>
                @elseif(isset($environment) && $environment->phone)
                    {{ $environment->phone }}<br>
                @endif
            </div>
        </div>
        <div class="invoice-title">
            Invoice
            <div style="font-size: 1em; font-weight: normal;">#{{ $invoice->invoice_number }}</div>
        </div>
    </div>

    <div class="section mb-4">
        <strong>Invoice Date:</strong> {{ $invoice->month ? $invoice->month->format('F Y') : $invoice->created_at->format('F Y') }}<br>
        <strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '' }}<br>
        <strong>Status:</strong> {{ ucfirst($invoice->status) }}<br>
    </div>

    <div class="section mb-4">
        <strong>Billed To:</strong><br>
        @if(isset($environment) && $environment->owner_name)
            {{ $environment->owner_name }}<br>
        @endif
        @if(isset($environment) && $environment->owner_email)
            {{ $environment->owner_email }}<br>
        @endif
    </div>

    <div class="section">
        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Platform Fees for {{ $invoice->month ? $invoice->month->format('F Y') : $invoice->created_at->format('F Y') }}</td>
                    <td class="right">{{ number_format($invoice->total_fee_amount, 2) }} {{ $invoice->currency }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th class="right">Total</th>
                    <th class="right">{{ number_format($invoice->total_fee_amount, 2) }} {{ $invoice->currency }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section mb-2">
        <strong>Transaction Count:</strong> {{ $invoice->transaction_count }}<br>
        <strong>Invoice Status:</strong> {{ ucfirst($invoice->status) }}<br>
        @if($invoice->paid_at)
            <strong>Paid At:</strong> {{ $invoice->paid_at->format('Y-m-d') }}<br>
        @endif
    </div>

    <div class="section" style="font-size: 0.95em; color: #666;">
        Thank you for your business.
    </div>
</body>
</html> 