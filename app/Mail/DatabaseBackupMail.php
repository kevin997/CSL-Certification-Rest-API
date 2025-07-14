<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class DatabaseBackupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The path to the backup file.
     *
     * @var string
     */
    protected $backupFilePath;

    /**
     * The name of the backup file.
     *
     * @var string
     */
    protected $backupFileName;

    /**
     * The size of the backup file in MB.
     *
     * @var float
     */
    protected $backupSizeMb;

    /**
     * Create a new message instance.
     *
     * @param string $backupFilePath
     * @param string $backupFileName
     * @param float $backupSizeMb
     * @return void
     */
    public function __construct($backupFilePath, $backupFileName, $backupSizeMb)
    {
        $this->backupFilePath = $backupFilePath;
        $this->backupFileName = $backupFileName;
        $this->backupSizeMb = $backupSizeMb;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'CSL Certification API - Database Backup ' . date('Y-m-d H:i'),
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.database-backup',
            with: [
                'backupFileName' => $this->backupFileName,
                'backupSizeMb' => $this->backupSizeMb,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'environment' => config('app.env'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [
            Attachment::fromPath($this->backupFilePath)
                ->as($this->backupFileName)
                ->withMime('application/gzip'),
        ];
    }
}
