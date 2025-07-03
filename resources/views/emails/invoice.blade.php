@component('mail::message')
{{-- Branding/Header --}}
@if(isset($branding) && $branding && $branding->logo_url)
<div style="text-align:center;margin-bottom:24px;">
    <img src="{{ $branding->logo_url }}" alt="{{ $branding->company_name ?? $environment->name }} Logo" style="max-height:60px;">
</div>
@endif

# Invoice from {{ $branding->company_name ?? $environment->name ?? 'CSL' }}

Hello,

A new platform fee invoice has been generated for your environment.

@component('mail::panel')
**Invoice Number:** {{ $invoice->invoice_number ?? $invoice->id }}  
**Amount Due:** {{ number_format($invoice->amount, 2) }} {{ $invoice->currency ?? 'USD' }}  
**Due Date:** {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->toFormattedDateString() : 'N/A' }}  
**Status:** {{ ucfirst($invoice->status) }}
@endcomponent

@isset($invoice->payment_link)
@component('mail::button', ['url' => $invoice->payment_link])
Pay Invoice
@endcomponent
@endisset

You can also view or download your invoice from your dashboard.

Thanks,<br>
{{ $branding->company_name ?? $environment->name ?? 'CSL' }} Team
@endcomponent 