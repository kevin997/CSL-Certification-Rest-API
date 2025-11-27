@component('mail::message', ['environment' => $environment ?? null, 'branding' => $branding ?? null])
# Welcome to {{ $branding?->company_name ?? $environment->name }}!

Hello {{ $user->name }},

Thank you for joining {{ $branding?->company_name ?? $environment->name }}. We're excited to have you on board!

Your account has been created and you can now access the learning environment.

## Your Login Information
**Email:** {{ $user->email }}
@if($password)
**Password:** {{ $password }}
@else
**Password:** Use your existing account password
@endif
**Login URL:** <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>

@component('mail::button', ['url' => $loginUrl])
Login to Your Account
@endcomponent

@if($password)
For security reasons, we recommend changing your password after your first login.
@else
You can log in using the same password you use for your account.
@endif

If you have any questions or need assistance, please don't hesitate to contact our support team.

Thanks,<br>
{{ $branding?->company_name ?? $environment->name }} Team

@if($branding?->custom_css)
<x-slot:css>
    {{ $branding->custom_css }}
</x-slot:css>
@endif
@endcomponent