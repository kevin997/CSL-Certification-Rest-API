<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Models\Environment;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The invoice instance.
     *
     * @var Invoice
     */
    public $invoice;

    /**
     * The Environment instance.
     *
     * @var Environment
     */
    public $environment;

    /**
     * Branding data (if available)
     *
     * @var mixed
     */
    public $branding;

    /**
     * Create a new message instance.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->environment = $invoice->environment;
        $this->branding = null;
        if ($invoice->environment) {
            $this->branding = \App\Models\Branding::where('environment_id', $invoice->environment->id)
                ->where('is_active', true)
                ->first();
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $environmentName = $this->environment ? $this->environment->name : 'CSL';
        return $this->from('no.reply@cfpcsl.com', $environmentName)
            ->subject('CSL Brands, Learning Platform Fee Invoice')
            ->markdown('emails.invoice', [
                'invoice' => $this->invoice,
                'environment' => $this->environment,
                'branding' => $this->branding,
            ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
