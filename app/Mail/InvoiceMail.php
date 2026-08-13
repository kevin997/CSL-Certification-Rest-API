<?php

namespace App\Mail;

use App\Helpers\EmailBrandingHelper;
use App\Models\Environment;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The invoice instance.
     */
    public Invoice $invoice;

    /**
     * The Environment instance.
     */
    public ?Environment $environment;

    /**
     * Branding data (if available).
     */
    public ?array $branding;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->environment = $invoice->environment;
        $this->branding = null;

        if ($this->environment) {
            $this->branding = EmailBrandingHelper::resolve($this->environment);
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = $this->branding['company_name'] ?? $this->environment?->name ?? 'CSL';

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                $companyName
            ),
            subject: 'Platform Fee Invoice — '.($this->invoice->invoice_number ?? 'Invoice'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'environment' => $this->environment,
                'branding' => $this->branding,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Attach the generated PDF if it exists
        if ($this->invoice->pdf_path) {
            $fullPath = storage_path('app/'.$this->invoice->pdf_path);

            if (file_exists($fullPath)) {
                $attachments[] = Attachment::fromPath($fullPath)
                    ->as('invoice-'.$this->invoice->invoice_number.'.pdf')
                    ->withMime('application/pdf');
            }
        }

        return $attachments;
    }
}
