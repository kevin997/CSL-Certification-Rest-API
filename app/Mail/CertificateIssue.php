<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\IssuedCertificate;
use App\Models\User;
use App\Models\Environment;

class CertificateIssue extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The issued certificate instance.
     *
     * @var IssuedCertificate
     */
    public $issuedCertificate;

    /**
     * The user instance.
     *
     * @var User
     */
    public $user;

    /**
     * The Environment instance.
     *
     * @var Environment
     */
    public $environment;

    /**
     * Create a new message instance.
     *
     * @param Certificate $certificate
     * @return void
     */
    public function __construct(IssuedCertificate $issuedCertificate)
    {
        $this->issuedCertificate = $issuedCertificate;
        $this->user = $issuedCertificate->user;
        $this->environment = $issuedCertificate->environment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Load the environment relationship if not already loaded
        if (!$this->issuedCertificate->relationLoaded('environment')) {
            $this->issuedCertificate->load('environment');
        }

        // Get the environment name or use 'CSL' as fallback
        $environmentName = $this->issuedCertificate->environment ? $this->issuedCertificate->environment->name : 'CSL';

        // Load environment branding if available
        $branding = null;
        if ($this->issuedCertificate->environment) {
            $branding = \App\Models\Branding::where('environment_id', $this->issuedCertificate->environment->id)
                ->where('is_active', true)
                ->first();
        }

        return $this->from(config('mail.from.address'), $environmentName)
            ->subject(' Congratulations! Your Certificate is Ready')
            ->markdown('emails.issuing-certificate', [
                'environment' => $this->issuedCertificate->environment,
                'branding' => $branding,
                'certificate' => $this->issuedCertificate
            ]);
    }
}
