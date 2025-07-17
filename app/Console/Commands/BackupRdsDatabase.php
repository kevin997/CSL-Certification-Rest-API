<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Facades\Log;

class BackupRdsDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-rds {--email : Send backup via email} {--compress=gzip : Compression method (gzip|none)} {--timeout=3600 : Timeout in seconds} {--chunk-size=1000000 : Rows per chunk for large tables}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the RDS database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting RDS database backup...');

        // Set memory and time limits for large databases
        ini_set('memory_limit', '2G');
        set_time_limit(0); // No time limit

        try {
            $backupPath = $this->createBackup();
            $this->info("Backup created: " . basename($backupPath));

            // Display file size
            $fileSizeMB = round(filesize($backupPath) / (1024 * 1024), 2);
            $this->info("Backup size: {$fileSizeMB} MB");

            if ($this->option('email')) {
                $this->sendBackupEmail($backupPath);
                $this->info('Backup email sent successfully');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            Log::error('Database backup failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function createBackup(): string
    {
        $config = config('database.connections.' . config('database.default'));
        $backupDir = storage_path('backups');

        // Create backups directory if it doesn't exist
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $date = Carbon::now()->format('Y-m-d_His');
        $filename = "backup_{$date}.sql";
        $filepath = "{$backupDir}/{$filename}";

        // Build the mysqldump command with optimizations for large databases
        $timeout = $this->option('timeout');
        
        $command = sprintf(
            'timeout %s mysqldump --single-transaction --set-gtid-purged=OFF --routines --triggers --quick --lock-tables=false --extended-insert --host=%s --port=%s --user=%s --password=%s %s',
            escapeshellarg($timeout),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? 3306),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database'])
        );
        
        // Handle compression
        $compress = $this->option('compress');
        if ($compress === 'gzip') {
            $command .= ' | gzip > ' . escapeshellarg($filepath . '.gz');
            $filepath .= '.gz';
        } else {
            $command .= ' > ' . escapeshellarg($filepath);
        }

        // Add SSL options if using RDS
        if (config('database.connections.mysql.ssl')) {
            $command .= ' --ssl-ca=' . escapeshellarg(config('database.connections.mysql.ssl_ca'));
            $command .= ' --ssl-verify-server-cert';
        }

        // Execute the command with progress tracking
        $this->info('Starting database backup...');
        $startTime = microtime(true);
        
        $return = null;
        $output = [];
        
        // Use popen for real-time output
        $process = popen($command, 'r');
        if (!$process) {
            throw new \RuntimeException('Failed to start backup process');
        }
        
        while (!feof($process)) {
            $line = fgets($process);
            if ($line !== false) {
                $output[] = trim($line);
            }
        }
        
        $return = pclose($process);
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Backup completed in {$duration} seconds");

        if ($return !== 0) {
            throw new \RuntimeException('Failed to create database backup: ' . implode("\n", $output));
        }

        if (!file_exists($filepath) || filesize($filepath) === 0) {
            throw new \RuntimeException('Backup file was not created or is empty');
        }

        // Return the final file path (already compressed if gzip option was used)
        return $filepath;
    }

    private function sendBackupEmail(string $filepath): void
    {
        $recipients = [
            'kevinliboire@gmail.com',
            'data.analyst@cfpcsl.com'
        ];

        $filename = basename($filepath);
        $filesizeMb = round(filesize($filepath) / (1024 * 1024), 2);

        $message = view('emails.database-backup', [
            'backupFileName' => $filename,
            'backupSizeMb' => $filesizeMb,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'environment' => config('app.env'),
        ])->render();

        $this->sendMailWithPHPMailer($recipients, $filepath, $filename, $message);
        $this->info('Backup sent to: ' . implode(', ', $recipients));
    }

    private function sendMailWithPHPMailer(array $recipients, string $filepath, string $filename, string $message): void
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'node127-eu.n0c.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'data.analyst@cfpcsl.com';
            $mail->Password = '_PfaR2@-Hq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';

            // Recipients
            $mail->setFrom('data.analyst@cfpcsl.com', 'CSL Certification API');

            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'CSL Certification API - Database Backup ' . date('Y-m-d H:i');
            $mail->Body = $message;

            // Attach backup file
            $mail->addAttachment($filepath, $filename, 'base64', 'application/gzip');

            $mail->send();
        } catch (PHPMailerException $e) {
            Log::error('Failed to send backup email: ' . $e->getMessage());
            throw new \RuntimeException("Email could not be sent. Error: {$mail->ErrorInfo}");
        }
    }
}
