<?php

namespace App\Listeners;

use Spatie\Backup\Events\BackupZipWasCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\BackupNotification;

class MailBackupWithAttachment
{
    /**
     * Handle the event.
     */
    public function handle(BackupZipWasCreated $event): void
    {
        $backupPath = $event->pathToZip;
        $backupName = basename($backupPath);
        $appName = config('app.name', 'CSL Certification API');
        
        // Get backup file size in MB
        $backupSize = round(filesize($backupPath) / 1024 / 1024, 2);
        
        Log::info("Backup created: {$backupName} ({$backupSize} MB)");
        
        // Check if file size is reasonable for email (less than 25MB)
        if ($backupSize > 25) {
            Log::warning("Backup file too large for email attachment: {$backupSize} MB");
            $this->sendLargeBackupNotification($backupName, $backupSize, $backupPath);
            return;
        }

        try {
            $this->sendBackupEmail($backupPath, $backupName, $backupSize);
            Log::info("Backup email with attachment sent successfully");
        } catch (\Exception $e) {
            Log::error("Failed to send backup email: " . $e->getMessage());
        }
    }

    /**
     * Send backup email with attachment using Laravel Mail
     */
    private function sendBackupEmail(string $backupPath, string $backupName, float $backupSize): void
    {
        $recipients = ['kevinliboire@gmail.com', 'data.analyst@cfpcsl.com'];
        
        $mail = new BackupNotification($backupName, $backupSize, $backupPath, true);
        
        Mail::to($recipients)->send($mail);
    }

    /**
     * Send notification for large backup files (without attachment)
     */
    private function sendLargeBackupNotification(string $backupName, float $backupSize, string $backupPath): void
    {
        try {
            $recipients = ['kevinliboire@gmail.com', 'data.analyst@cfpcsl.com'];
            
            $mail = new BackupNotification($backupName, $backupSize, $backupPath, false);
            
            Mail::to($recipients)->send($mail);
            
            Log::info("Large backup notification sent successfully");
        } catch (\Exception $e) {
            Log::error("Failed to send large backup notification: " . $e->getMessage());
        }
    }
}