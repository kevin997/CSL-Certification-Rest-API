<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use App\Mail\DatabaseBackupMail;
use Carbon\Carbon;

class BackupRdsDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rds:backup {--email=} {--no-email : Don\'t send email} {--keep=5 : Number of backups to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the RDS database and optionally email it';

    /**
     * The backup storage path
     * 
     * @var string
     */
    protected $backupPath = 'backups/database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('Starting RDS database backup process...');
        
        try {
            // Get database configuration
            $connection = Config::get('database.default');
            $config = Config::get("database.connections.{$connection}");
            
            if (empty($config)) {
                throw new \Exception("Database configuration not found for connection: {$connection}");
            }
            
            $this->info("Using database connection: {$connection}");
            
            // Create timestamp for the backup file
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $databaseName = $config['database'];
            $backupFileName = "{$databaseName}_{$timestamp}.sql";
            $localBackupPath = storage_path("app/{$this->backupPath}");
            
            // Ensure the backup directory exists
            if (!is_dir($localBackupPath)) {
                mkdir($localBackupPath, 0755, true);
            }
            
            $backupFilePath = "{$localBackupPath}/{$backupFileName}";
            
            // Build the mysqldump command with SSL support
            $sslCertPath = env('RDS_SSL_CERT_PATH', storage_path('ssl/rds-combined-ca-bundle.pem'));
            $sslMode = env('RDS_SSL_MODE', 'REQUIRED');
            
            // Check if SSL certificate file exists
            if (!file_exists($sslCertPath)) {
                throw new \Exception("SSL certificate file not found: {$sslCertPath}");
            }
            
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s --ssl-ca=%s --ssl-mode=%s %s > %s',
                escapeshellarg($config['host']),
                escapeshellarg($config['port'] ?? 3306),
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($sslCertPath),
                escapeshellarg($sslMode),
                escapeshellarg($databaseName),
                escapeshellarg($backupFilePath)
            );
            
            $this->info("Executing database backup command...");
            
            // Execute the backup command
            $returnVar = null;
            $output = [];
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new \Exception("Database backup failed with exit code: {$returnVar}");
            }
            
            // Check if the backup file was created and has content
            if (!file_exists($backupFilePath) || filesize($backupFilePath) === 0) {
                throw new \Exception("Backup file was not created or is empty");
            }
            
            $backupSizeMb = round(filesize($backupFilePath) / 1048576, 2);
            $this->info("Database backup completed successfully: {$backupFileName} ({$backupSizeMb} MB)");
            
            // Compress the backup file
            $this->info("Compressing backup file...");
            $compressedFileName = "{$backupFileName}.gz";
            $compressedFilePath = "{$localBackupPath}/{$compressedFileName}";
            
            $compressCommand = "gzip -c {$backupFilePath} > {$compressedFilePath}";
            exec($compressCommand, $output, $returnVar);
            
            if ($returnVar !== 0 || !file_exists($compressedFilePath)) {
                throw new \Exception("Failed to compress backup file");
            }
            
            // Remove the uncompressed file to save space
            unlink($backupFilePath);
            
            $compressedSizeMb = round(filesize($compressedFilePath) / 1048576, 2);
            $this->info("Backup compressed: {$compressedFileName} ({$compressedSizeMb} MB)");
            
            // Perform backup rotation (keep only the specified number of backups)
            $this->rotateBackups($localBackupPath, $this->option('keep'));
            
            // Send email with the backup if requested
            if (!$this->option('no-email')) {
                $emailTo = $this->option('email') ?: 'data.analyst@cfpcsl.com';
                $this->info("Sending backup via email to {$emailTo}...");
                
                Mail::to($emailTo)->send(new DatabaseBackupMail($compressedFilePath, $compressedFileName, $compressedSizeMb));
                $this->info("Backup email sent successfully");
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("Backup process completed in {$executionTime} seconds");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Backup failed: {$e->getMessage()}");
            Log::error("Database backup failed: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Rotate backups to keep only the specified number
     *
     * @param string $backupDir
     * @param int $keep
     * @return void
     */
    protected function rotateBackups($backupDir, $keep)
    {
        $keep = max(1, intval($keep)); // Ensure we keep at least 1 backup
        
        $files = glob("{$backupDir}/*.sql.gz");
        
        // Sort files by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Keep only the specified number of backups
        if (count($files) > $keep) {
            $this->info("Rotating backups, keeping the {$keep} most recent...");
            
            $filesToDelete = array_slice($files, $keep);
            foreach ($filesToDelete as $file) {
                $this->info("Removing old backup: " . basename($file));
                unlink($file);
            }
            
            $this->info("Backup rotation completed");
        }
    }
}
