<?php

namespace App\Listeners;

use Spatie\Backup\Events\BackupZipWasCreated;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

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
            $this->sendBackupEmail($backupPath, $backupName, $backupSize, $appName);
            Log::info("Backup email with attachment sent successfully");
        } catch (\Exception $e) {
            Log::error("Failed to send backup email: " . $e->getMessage());
        }
    }

    /**
     * Send backup email with attachment using PHPMailer
     */
    private function sendBackupEmail(string $backupPath, string $backupName, float $backupSize, string $appName): void
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings using PHPMAILER environment variables
            $mail->isSMTP();
            $mail->Host = env('PHPMAILER_HOST', 'node127-eu.n0c.com');
            $mail->SMTPAuth = env('PHPMAILER_AUTH', true);
            $mail->Username = env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com');
            $mail->Password = env('PHPMAILER_PASSWORD');
            $mail->SMTPSecure = env('PHPMAILER_ENCRYPTION', 'ssl');
            $mail->Port = env('PHPMAILER_PORT', 465);
            $mail->CharSet = env('PHPMAILER_CHARSET', 'UTF-8');
            $mail->Timeout = env('PHPMAILER_TIMEOUT', 60);

            // Recipients
            $mail->setFrom(env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com'), $appName);
            
            // Add multiple recipients
            $recipients = ['kevinliboire@gmail.com', 'data.analyst@cfpcsl.com'];
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }

            // Attachment
            $mail->addAttachment($backupPath, $backupName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "{$appName} - Database Backup Successful";
            
            $timestamp = now()->format('Y-m-d H:i:s');
            $environment = app()->environment();
            
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                            {$appName} - Database Backup Successful
                        </h2>
                        
                        <p>Hello,</p>
                        
                        <p>A new database backup has been generated successfully and is attached to this email.</p>
                        
                        <div style='background-color: #ecf0f1; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                            <strong>Backup Details:</strong><br>
                            <strong>File Name:</strong> {$backupName}<br>
                            <strong>File Size:</strong> {$backupSize} MB<br>
                            <strong>Generated At:</strong> {$timestamp}<br>
                            <strong>Environment:</strong> {$environment}
                        </div>
                        
                        <p style='color: #e74c3c; font-weight: bold;'>
                            ⚠️ Important: This backup contains sensitive database information. 
                            Please store it securely and delete it from your email after downloading.
                        </p>
                        
                        <p>This is an automated email. Please do not reply to this message.</p>
                        
                        <hr style='border: none; border-top: 1px solid #bdc3c7; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #7f8c8d; text-align: center;'>
                            {$appName} System<br>
                            © " . date('Y') . " CSL. All rights reserved.
                        </p>
                    </div>
                </body>
                </html>
            ";

            $mail->AltBody = "Database backup for {$appName} has been generated successfully.\n\n" .
                           "File: {$backupName}\n" .
                           "Size: {$backupSize} MB\n" .
                           "Generated: {$timestamp}\n" .
                           "Environment: {$environment}\n\n" .
                           "The backup file is attached to this email.";

            $mail->send();
            
        } catch (PHPMailerException $e) {
            Log::error("PHPMailer Error: {$mail->ErrorInfo}");
            throw $e;
        }
    }

    /**
     * Send notification for large backup files (without attachment)
     */
    private function sendLargeBackupNotification(string $backupName, float $backupSize, string $backupPath): void
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings using PHPMAILER environment variables
            $mail->isSMTP();
            $mail->Host = env('PHPMAILER_HOST', 'node127-eu.n0c.com');
            $mail->SMTPAuth = env('PHPMAILER_AUTH', true);
            $mail->Username = env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com');
            $mail->Password = env('PHPMAILER_PASSWORD');
            $mail->SMTPSecure = env('PHPMAILER_ENCRYPTION', 'ssl');
            $mail->Port = env('PHPMAILER_PORT', 465);
            $mail->CharSet = env('PHPMAILER_CHARSET', 'UTF-8');
            $mail->Timeout = env('PHPMAILER_TIMEOUT', 60);

            // Recipients
            $mail->setFrom(env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com'), config('app.name'));
            
            $recipients = ['kevinliboire@gmail.com', 'data.analyst@cfpcsl.com'];
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = config('app.name') . " - Large Database Backup Created";
            
            $timestamp = now()->format('Y-m-d H:i:s');
            $environment = app()->environment();
            
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #f39c12; border-bottom: 2px solid #f39c12; padding-bottom: 10px;'>
                            Large Database Backup Created
                        </h2>
                        
                        <p>Hello,</p>
                        
                        <p>A database backup has been generated successfully, but the file is too large to attach to this email.</p>
                        
                        <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #f39c12;'>
                            <strong>Backup Details:</strong><br>
                            <strong>File Name:</strong> {$backupName}<br>
                            <strong>File Size:</strong> {$backupSize} MB<br>
                            <strong>Generated At:</strong> {$timestamp}<br>
                            <strong>Environment:</strong> {$environment}<br>
                            <strong>File Location:</strong> {$backupPath}
                        </div>
                        
                        <p style='color: #856404;'>
                            📁 The backup file is available on the server at the specified location. 
                            Please download it manually from the server.
                        </p>
                        
                        <p>This is an automated email. Please do not reply to this message.</p>
                        
                        <hr style='border: none; border-top: 1px solid #bdc3c7; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #7f8c8d; text-align: center;'>
                            " . config('app.name') . " System<br>
                            © " . date('Y') . " CSL. All rights reserved.
                        </p>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
            Log::info("Large backup notification sent successfully");
            
        } catch (PHPMailerException $e) {
            Log::error("Failed to send large backup notification: {$mail->ErrorInfo}");
        }
    }
}