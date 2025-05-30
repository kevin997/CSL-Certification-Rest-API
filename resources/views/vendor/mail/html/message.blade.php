<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if(isset($environment) && isset($branding) && $branding->logo_path)
    <img src="{{ asset($branding->logo_path) }}" alt="{{ $branding->company_name ?? $environment->name ?? config('app.name') }}" style="max-width: 200px; max-height: 80px;">
@else
    {{ config('app.name') }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ isset($branding) ? ($branding->company_name ?? (isset($environment) ? $environment->name : config('app.name'))) : config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
