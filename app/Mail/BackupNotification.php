<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $backupName;
    public float $backupSize;
    public string $backupPath;
    public string $timestamp;
    public string $environment;
    public bool $hasAttachment;

    public function __construct(
        string $backupName,
        float $backupSize,
        string $backupPath,
        bool $hasAttachment = true
    ) {
        $this->backupName = $backupName;
        $this->backupSize = $backupSize;
        $this->backupPath = $backupPath;
        $this->hasAttachment = $hasAttachment;
        $this->timestamp = now()->format('Y-m-d H:i:s');
        $this->environment = app()->environment();
    }

    public function build()
    {
        $appName = config('app.name', 'CSL Certification API');
        
        $mail = $this->from(config('mail.from.address'), $appName)
                     ->subject($this->hasAttachment ? 
                         "{$appName} - Database Backup Successful" : 
                         "{$appName} - Large Database Backup Created")
                     ->view($this->hasAttachment ? 'emails.backup-success' : 'emails.backup-large');

        if ($this->hasAttachment && file_exists($this->backupPath)) {
            $mail->attach($this->backupPath, [
                'as' => $this->backupName,
                'mime' => 'application/zip'
            ]);
        }

        return $mail;
    }
}