@php
    $branding = $branding ?? null;
    $environment = $environment ?? null;
    $certificate = $certificate ?? null;
    $user = $certificate?->user;
    $previewUrl = $certificate?->custom_fields['preview_url'] ?? null;
    $fileUrl = $certificate?->file_path ?? null;
    $accessCode = $certificate?->certificate_number ?? null;
@endphp

@component('mail::message', ['environment' => $environment, 'branding' => $branding])
# Congratulations, {{ $user?->name ?? 'Learner' }}!

Your certificate for **{{ $certificate?->certificateContent?->title ?? 'your course' }}** is ready.

@if($branding && $branding->logo)
<div style="text-align:center;margin-bottom:20px;">
    <img src="{{ $branding->logo }}" alt="{{ $branding->company_name }}" style="max-width:200px;max-height:100px;">
</div>
@endif

**Certificate Title:** {{ $certificate?->certificateContent?->title ?? 'Certificate' }}  
**Recipient:** {{ $user?->name ?? 'Learner' }}  
@if($accessCode)
**Access Code:** {{ $accessCode }}  
@endif
@if($certificate?->issued_date)
**Issued Date:** {{ $certificate->issued_date->format('F j, Y') }}  
@endif
@if($certificate?->expiry_date)
**Expiry Date:** {{ $certificate->expiry_date->format('F j, Y') }}  
@endif

@isset($previewUrl)
@component('mail::button', ['url' => $previewUrl])
View Certificate
@endcomponent
@endisset

@isset($fileUrl)
@component('mail::button', ['url' => $fileUrl])
Download Certificate (PDF)
@endcomponent
@endisset

@if($branding && $branding->company_name)
Thanks,<br>
{{ $branding->company_name }} Team
@elseif($environment)
Thanks,<br>
{{ $environment->name }} Team
@else
Thanks,<br>
CSL Team
@endif

@if($branding && $branding->custom_css)
<x-slot:css>
{!! $branding->custom_css !!}
</x-slot:css>
@endif
@endcomponent
