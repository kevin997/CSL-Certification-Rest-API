@component('mail::message', ['branding' => $branding])
# Your Environment "{{ $environment->name }}" is Ready! 🎉

Hello {{ $user->name }},

Congratulations! Your CSL learning environment has been successfully created and is ready for use.

## Environment Details
**Name:** {{ $environment->name }}  
**Type:** {{ $domainType }}  
**URL:** [{{ $environment->primary_domain }}]({{ $loginUrl }})

## Administrator Credentials
**Email:** {{ $adminEmail }}  
**Password:** {{ $adminPassword }}

@component('mail::button', ['url' => $loginUrl])
Access Your Environment
@endcomponent

## Your Campus is Ready!

Your environment is being configured automatically and will be available at **{{ $environment->primary_domain }}** within a few minutes.

**What happens next:**
- Your campus URL is being set up automatically
- SSL certificate will be provisioned for secure access
- Once ready, simply visit your URL and log in with the credentials above

## Getting Started

1. **Log in** to your admin dashboard using the credentials above
2. **Change your password** after first login for security
3. **Customize** your branding, colors, and logo
4. **Create courses** and start building your learning content
5. **Invite team members** to collaborate

## Important Security Notes

- **Change your password** after first login
- **Enable two-factor authentication** for enhanced security
- **Review user permissions** and roles regularly

## Support

If you need any assistance with the setup process or have questions about your environment, please contact our support team.

**Join our WhatsApp Support Group:** [Click here to join](https://chat.whatsapp.com/E4W3kHnCticCzxYFp66rE4?mode=ac_t) for immediate assistance!

**Documentation:** [CSL Setup Guide](https://docs.cfpcsl.com/setup)  
**Support:** [support@cfpcsl.com](mailto:support@cfpcsl.com)

Welcome to CSL! We're excited to see what you'll build with your new learning environment.

Thanks,<br>
The CSL Team

<x-slot:css>
:root {
    --primary-color: {{ $branding['primary_color'] }};
    --secondary-color: {{ $branding['secondary_color'] }};
    --font-family: {{ $branding['font_family'] }};
}

.button {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
}

.button:hover {
    background-color: var(--secondary-color) !important;
    border-color: var(--secondary-color) !important;
}

body {
    font-family: var(--font-family);
}

h1, h2, h3 {
    color: var(--primary-color);
}
</x-slot:css>
@endcomponent