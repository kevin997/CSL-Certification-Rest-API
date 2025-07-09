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

## Next Steps - {{ $domainType === 'subdomain' ? 'Subdomain' : 'Custom Domain' }} Configuration

@if($isSubdomain)
### Subdomain Configuration for Vercel

Your environment is configured with a subdomain. To set up your Vercel deployment:

1. **Add Domain to Vercel Project:**
   - Go to your Vercel project settings
   - Navigate to "Domains" section
   - Add the domain: `{{ $environment->primary_domain }}`
   - Vercel will automatically handle the DNS configuration

2. **DNS Configuration (if needed):**
   - If you need to configure DNS manually, add a CNAME record:
   - **Type:** CNAME
   - **Name:** {{ explode('.', $environment->primary_domain)[0] }}
   - **Value:** cname.vercel-dns.com

3. **SSL Certificate:**
   - Vercel will automatically provision and renew SSL certificates
   - Your environment will be accessible via HTTPS

@else
### Custom Domain Configuration for Vercel

Your environment is configured with a custom domain. To set up your Vercel deployment:

1. **Add Domain to Vercel Project:**
   - Go to your Vercel project settings
   - Navigate to "Domains" section
   - Add the domain: `{{ $environment->primary_domain }}`

2. **DNS Configuration:**
   - Update your domain's DNS settings with your registrar
   - Add the following records:
   - **Type:** A
   - **Name:** @ (or your subdomain)
   - **Value:** 76.76.19.19
   - **Type:** CNAME
   - **Name:** www
   - **Value:** cname.vercel-dns.com

3. **SSL Certificate:**
   - Vercel will automatically provision and renew SSL certificates
   - Your environment will be accessible via HTTPS

@endif

## Important Security Notes

- **Change your password** after first login
- **Enable two-factor authentication** for enhanced security
- **Review user permissions** and roles regularly
- **Keep your environment updated** with latest features

## Support

If you need any assistance with the setup process or have questions about your environment, please contact our support team.

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