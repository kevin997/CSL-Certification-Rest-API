<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\IssuedCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateIssue extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public IssuedCertificate $issuedCertificate,
    ) {}

    public function envelope(): Envelope
    {
        $this->issuedCertificate->loadMissing(['user', 'environment', 'certificateContent']);

        $branding = $this->resolveBranding();

        return new Envelope(
            from: new Address(config('mail.from.address'), $branding['company_name']),
            subject: '🎓 Congratulations! Your Certificate is Ready',
        );
    }

    public function content(): Content
    {
        $this->issuedCertificate->loadMissing(['user', 'environment', 'certificateContent']);

        $branding = $this->resolveBranding();

        $previewUrl = $this->issuedCertificate->custom_fields['preview_url'] ?? null;
        $fileUrl = $this->issuedCertificate->file_path ?? null;

        return new Content(
            view: 'emails.issuing-certificate',
            with: [
                'certificate' => $this->issuedCertificate,
                'user' => $this->issuedCertificate->user,
                'branding' => $branding,
                'previewUrl' => $previewUrl,
                'fileUrl' => $fileUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function resolveBranding(): array
    {
        if ($this->issuedCertificate->environment) {
            return EmailBrandingHelper::resolve($this->issuedCertificate->environment);
        }

        return [
            'company_name' => 'KURSA',
            'primary_color' => '#19682f',
            'secondary_color' => '#f59c00',
            'accent_color' => '#ffb733',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            'logo_url' => asset('images/logo-kursa.svg'),
            'login_url' => '#',
        ];
    }
}
