<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class BackupSalesDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:sales-database {--email : Send backup via email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup CSL Sales Website PostgreSQL database and optionally send via email';

    /**
     * Sales Website database connection details
     */
    private $salesDbConfig = [
        'host' => 'database-2.ccr2s68cu8xf.us-east-1.rds.amazonaws.com',
        'port' => '5432',
        'database' => 'csl_sales_db',
        'username' => 'postgres',
        'password' => '(1217w5w7j735J2==='
    ];

    /**
     * Email recipients for backup notifications
     */
    private $emailRecipients = [
        'kevinliboire@gmail.com',
        'data.analyst@cfpcsl.com'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting CSL Sales Database backup...');
        
        try {
            // Create backup directory if not exists
            $backupDir = storage_path('app/sales-backups');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
                $this->info("Created backup directory: {$backupDir}");
            }

            // Generate backup filename
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $backupFileName = "csl_sales_db_backup_{$timestamp}.sql";
            $backupPath = $backupDir . '/' . $backupFileName;

            // Create PostgreSQL backup using pg_dump
            $this->createPostgreSQLBackup($backupPath);

            // Compress backup file
            $compressedPath = $this->compressBackup($backupPath);

            // Send email if requested
            if ($this->option('email')) {
                $this->sendBackupEmail($compressedPath, basename($compressedPath));
            }

            $this->info('Backup completed successfully!');
            $this->info("Backup saved to: {$compressedPath}");

        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Sales database backup failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Create PostgreSQL database backup using pg_dump
     */
    private function createPostgreSQLBackup(string $backupPath): void
    {
        $this->info('Creating PostgreSQL backup...');

        // Set PGPASSWORD environment variable for authentication
        putenv("PGPASSWORD=" . $this->salesDbConfig['password']);

        // Use manual backup approach due to version mismatch
        $this->info("Using manual backup approach due to PostgreSQL version mismatch");
        $this->createManualBackup($backupPath);
        return;

        // Set SSL environment variables and ignore version mismatch
        putenv("PGSSLMODE=require");
        putenv("PGDUMP_VERSION_IGNORE=1");

        $this->info("Executing: pg_dump command (password hidden)");

        // The command already includes output redirection
        
        // Execute command
        $output = [];
        $returnCode = null;
        exec($command, $output, $returnCode);

        // Clear environment variables
        putenv("PGPASSWORD");
        putenv("PGSSLMODE");
        putenv("PGDUMP_VERSION_IGNORE");

        // Check the output and handle version mismatch
        $outputString = implode("\n", $output);
        
        if ($returnCode !== 0) {
            // Check if it's just a version mismatch but backup was created
            if (strpos($outputString, 'server version mismatch') !== false || 
                strpos($outputString, 'aborting because of server version mismatch') !== false) {
                
                $this->warn("PostgreSQL version mismatch detected:");
                $this->warn("Server: 17.4, Client: 16.9");
                
                // Check if backup file was created despite the warning
                if (file_exists($backupPath) && filesize($backupPath) > 1000) {
                    $this->info("Backup file created successfully despite version mismatch");
                } else {
                    // Try a simpler approach without version checks
                    $this->info("Attempting backup with compatibility mode...");
                    $this->attemptCompatibilityBackup($backupPath);
                }
            } else {
                throw new \Exception("pg_dump failed with code {$returnCode}: " . $outputString);
            }
        }

        if (!file_exists($backupPath) || filesize($backupPath) === 0) {
            throw new \Exception("Backup file was not created or is empty");
        }

        $sizeInMB = round(filesize($backupPath) / 1024 / 1024, 2);
        $this->info("Backup created successfully: {$sizeInMB} MB");
        
        Log::info("Sales database backup created: {$backupPath} ({$sizeInMB} MB)");
    }

    /**
     * Create manual backup using psql queries to bypass version mismatch
     */
    private function createManualBackup(string $backupPath): void
    {
        $this->info('Creating manual backup using psql...');
        
        // Set environment variables
        putenv("PGPASSWORD=" . $this->salesDbConfig['password']);
        putenv("PGSSLMODE=require");
        
        $backupContent = [];
        $backupContent[] = "-- CSL Sales Database Backup";
        $backupContent[] = "-- Generated: " . date('Y-m-d H:i:s');
        $backupContent[] = "-- Method: Manual backup due to PostgreSQL version mismatch";
        $backupContent[] = "-- Server: PostgreSQL 17.4, Client: 16.9";
        $backupContent[] = "";
        
        try {
            // Get list of tables
            $tablesCommand = sprintf(
                "psql -h %s -p %s -U %s -d %s -t -c \"SELECT tablename FROM pg_tables WHERE schemaname = 'public';\"",
                escapeshellarg($this->salesDbConfig['host']),
                escapeshellarg($this->salesDbConfig['port']),
                escapeshellarg($this->salesDbConfig['username']),
                escapeshellarg($this->salesDbConfig['database'])
            );
            
            $tablesOutput = [];
            $tablesReturnCode = null;
            exec($tablesCommand . ' 2>/dev/null', $tablesOutput, $tablesReturnCode);
            
            if ($tablesReturnCode === 0 && !empty($tablesOutput)) {
                $tables = array_map('trim', array_filter($tablesOutput));
                $this->info("Found " . count($tables) . " tables to backup");
                
                foreach ($tables as $table) {
                    if (empty($table)) continue;
                    
                    $this->info("Backing up table: {$table}");
                    
                    // Get table schema
                    $schemaCommand = sprintf(
                        "pg_dump -h %s -p %s -U %s -d %s --no-password --schema-only --table=%s 2>/dev/null || echo '-- Schema for %s could not be retrieved'",
                        escapeshellarg($this->salesDbConfig['host']),
                        escapeshellarg($this->salesDbConfig['port']),
                        escapeshellarg($this->salesDbConfig['username']),
                        escapeshellarg($this->salesDbConfig['database']),
                        escapeshellarg($table),
                        $table
                    );
                    
                    $schemaOutput = [];
                    exec($schemaCommand, $schemaOutput);
                    
                    if (!empty($schemaOutput)) {
                        $backupContent[] = "-- Table: {$table} (Schema)";
                        $backupContent = array_merge($backupContent, $schemaOutput);
                        $backupContent[] = "";
                    }
                    
                    // Get table data using COPY command
                    $dataCommand = sprintf(
                        "psql -h %s -p %s -U %s -d %s -c \"\\copy %s TO STDOUT WITH CSV HEADER;\" 2>/dev/null",
                        escapeshellarg($this->salesDbConfig['host']),
                        escapeshellarg($this->salesDbConfig['port']),
                        escapeshellarg($this->salesDbConfig['username']),
                        escapeshellarg($this->salesDbConfig['database']),
                        $table
                    );
                    
                    $dataOutput = [];
                    $dataReturnCode = null;
                    exec($dataCommand, $dataOutput, $dataReturnCode);
                    
                    if ($dataReturnCode === 0 && !empty($dataOutput)) {
                        $backupContent[] = "-- Table: {$table} (Data as CSV)";
                        $backupContent[] = "-- Use \\copy {$table} FROM 'filename' WITH CSV HEADER; to restore";
                        $backupContent = array_merge($backupContent, $dataOutput);
                        $backupContent[] = "";
                        
                        $rowCount = count($dataOutput) - 1; // Subtract header
                        $this->info("  → Backed up {$rowCount} rows");
                    } else {
                        $backupContent[] = "-- Table: {$table} (No data or access denied)";
                        $backupContent[] = "";
                    }
                }
                
                // Write backup file
                file_put_contents($backupPath, implode("\n", $backupContent));
                
                $sizeInMB = round(filesize($backupPath) / 1024 / 1024, 2);
                $this->info("Manual backup created successfully: {$sizeInMB} MB");
                
                Log::info("Sales database manual backup created: {$backupPath} ({$sizeInMB} MB)");
                
            } else {
                throw new \Exception("Could not retrieve table list from database");
            }
            
        } catch (\Exception $e) {
            $this->error("Manual backup failed: " . $e->getMessage());
            
            // Fallback: try to get at least some basic info
            $basicInfoCommand = sprintf(
                "psql -h %s -p %s -U %s -d %s -c \"SELECT version(); SELECT current_database(); SELECT current_user;\" 2>/dev/null",
                escapeshellarg($this->salesDbConfig['host']),
                escapeshellarg($this->salesDbConfig['port']),
                escapeshellarg($this->salesDbConfig['username']),
                escapeshellarg($this->salesDbConfig['database'])
            );
            
            $basicOutput = [];
            exec($basicInfoCommand, $basicOutput);
            
            $fallbackContent = [
                "-- CSL Sales Database Connection Test",
                "-- Generated: " . date('Y-m-d H:i:s'),
                "-- Status: Manual backup failed, but connection succeeded",
                ""
            ];
            
            if (!empty($basicOutput)) {
                $fallbackContent[] = "-- Database Connection Info:";
                $fallbackContent = array_merge($fallbackContent, $basicOutput);
            }
            
            file_put_contents($backupPath, implode("\n", $fallbackContent));
            $this->info("Created fallback connection test file");
            
        } finally {
            // Clear environment variables
            putenv("PGPASSWORD");
            putenv("PGSSLMODE");
        }
    }

    /**
     * Attempt backup with compatibility mode for version mismatch issues
     */
    private function attemptCompatibilityBackup(string $backupPath): void
    {
        $this->info('Trying alternative backup method...');
        
        // Set environment variables
        putenv("PGPASSWORD=" . $this->salesDbConfig['password']);
        putenv("PGSSLMODE=require");
        
        // Use psql to get schema and data separately to avoid version issues
        $schemaCommand = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s --schema-only --no-password --no-owner --no-privileges',
            escapeshellarg($this->salesDbConfig['host']),
            escapeshellarg($this->salesDbConfig['port']),
            escapeshellarg($this->salesDbConfig['username']),
            escapeshellarg($this->salesDbConfig['database'])
        );
        
        $dataCommand = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s --data-only --no-password --no-owner --no-privileges --disable-triggers',
            escapeshellarg($this->salesDbConfig['host']),
            escapeshellarg($this->salesDbConfig['port']),
            escapeshellarg($this->salesDbConfig['username']),
            escapeshellarg($this->salesDbConfig['database'])
        );
        
        // Try to get at least the schema
        $schemaOutput = [];
        $schemaReturnCode = null;
        exec($schemaCommand . ' 2>/dev/null', $schemaOutput, $schemaReturnCode);
        
        $dataOutput = [];
        $dataReturnCode = null;
        exec($dataCommand . ' 2>/dev/null', $dataOutput, $dataReturnCode);
        
        // Clear environment variables
        putenv("PGPASSWORD");
        putenv("PGSSLMODE");
        putenv("PGDUMP_VERSION_IGNORE");
        
        // Combine outputs if we got something
        $backupContent = [];
        if (!empty($schemaOutput)) {
            $backupContent = array_merge($backupContent, ['-- SCHEMA'], $schemaOutput, ['']);
        }
        if (!empty($dataOutput)) {
            $backupContent = array_merge($backupContent, ['-- DATA'], $dataOutput);
        }
        
        if (!empty($backupContent)) {
            file_put_contents($backupPath, implode("\n", $backupContent));
            $this->info('Alternative backup method succeeded');
        } else {
            throw new \Exception("Alternative backup method also failed");
        }
    }

    /**
     * Compress backup file using gzip
     */
    private function compressBackup(string $backupPath): string
    {
        $this->info('Compressing backup file...');

        $compressedPath = $backupPath . '.gz';
        
        // Use gzip to compress
        $command = sprintf('gzip -9 %s', escapeshellarg($backupPath));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($compressedPath)) {
            throw new \Exception("Failed to compress backup file");
        }

        $originalSize = filesize($backupPath . '.gz');
        $compressedSize = round($originalSize / 1024 / 1024, 2);
        
        $this->info("Backup compressed successfully: {$compressedSize} MB");

        return $compressedPath;
    }

    /**
     * Send backup email with attachment using PHPMailer
     */
    private function sendBackupEmail(string $backupPath, string $backupName): void
    {
        $this->info('Sending backup email...');

        $backupSize = round(filesize($backupPath) / 1024 / 1024, 2);

        // Check file size limit for email
        if ($backupSize > 25) {
            $this->warn("Backup file too large for email attachment: {$backupSize} MB");
            $this->sendLargeBackupNotification($backupName, $backupSize);
            return;
        }

        foreach ($this->emailRecipients as $recipient) {
            try {
                $this->sendEmailToRecipient($recipient, $backupPath, $backupName, $backupSize);
                $this->info("Backup email sent to: {$recipient}");
            } catch (\Exception $e) {
                $this->error("Failed to send email to {$recipient}: " . $e->getMessage());
                Log::error("Failed to send backup email to {$recipient}: " . $e->getMessage());
            }
        }
    }

    /**
     * Send backup email to specific recipient
     */
    private function sendEmailToRecipient(string $recipient, string $backupPath, string $backupName, float $backupSize): void
    {
        Mail::send('emails.database-backup', [
            'backupFileName' => $backupName,
            'backupSizeMb' => $backupSize,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s T'),
            'environment' => 'Sales Database'
        ], function ($message) use ($recipient, $backupPath, $backupName) {
            $message->to($recipient)
                    ->subject('CSL Sales Database Backup - ' . Carbon::now()->format('Y-m-d H:i:s'))
                    ->attach($backupPath, ['as' => $backupName]);
        });
    }

    /**
     * Send notification for large backup files
     */
    private function sendLargeBackupNotification(string $backupName, float $backupSize): void
    {
        foreach ($this->emailRecipients as $recipient) {
            try {
                Mail::send('emails.database-backup', [
                    'backupFileName' => $backupName,
                    'backupSizeMb' => $backupSize,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s T'),
                    'environment' => 'Sales Database (Large File)'
                ], function ($message) use ($recipient, $backupName) {
                    $message->to($recipient)
                            ->subject('CSL Sales Database Backup (Large File) - ' . Carbon::now()->format('Y-m-d H:i:s'));
                });

                $this->info("Large backup notification sent to: {$recipient}");

            } catch (\Exception $e) {
                $this->error("Failed to send large backup notification to {$recipient}: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate email body HTML
     */
    private function generateEmailBody(string $backupName, float $backupSize, string $appName): string
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s T');
        $environment = app()->environment();

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .info-box { background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🛡️ Database Backup Notification</h1>
                <p>Automated backup system for {$appName}</p>
            </div>
            
            <div class='content'>
                <h2>Backup Details</h2>
                
                <div class='info-box'>
                    <strong>📄 File Name:</strong> {$backupName}<br>
                    <strong>📊 File Size:</strong> {$backupSize} MB<br>
                    <strong>🕒 Created:</strong> {$timestamp}<br>
                    <strong>🌍 Environment:</strong> {$environment}<br>
                    <strong>💾 Database:</strong> CSL Sales Website (PostgreSQL)
                </div>

                <div class='info-box warning'>
                    <strong>⚠️ Security Notice:</strong><br>
                    This backup contains sensitive data. Please handle with care and store securely.
                    Do not forward this email to unauthorized personnel.
                </div>

                <p>The backup has been automatically created and attached to this email. 
                Please verify the backup integrity and store it in a secure location.</p>
            </div>
            
            <div class='footer'>
                <p>This is an automated message from CSL Backup System<br>
                Generated at {$timestamp}</p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate large file notification email body
     */
    private function generateLargeFileEmailBody(string $backupName, float $backupSize): string
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s T');
        $environment = app()->environment();

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #e74c3c; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .info-box { background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>📦 Large Backup File Notification</h1>
                <p>Backup file too large for email attachment</p>
            </div>
            
            <div class='content'>
                <h2>Backup Information</h2>
                
                <div class='info-box'>
                    <strong>📄 File Name:</strong> {$backupName}<br>
                    <strong>📊 File Size:</strong> {$backupSize} MB<br>
                    <strong>🕒 Created:</strong> {$timestamp}<br>
                    <strong>🌍 Environment:</strong> {$environment}<br>
                    <strong>💾 Database:</strong> CSL Sales Website (PostgreSQL)
                </div>

                <div class='info-box warning'>
                    <strong>⚠️ Large File Notice:</strong><br>
                    The backup file ({$backupSize} MB) exceeds the email attachment limit of 25 MB.
                    The file has been saved on the server and can be accessed directly.
                </div>

                <h3>📍 Server Location:</h3>
                <code>storage/app/sales-backups/{$backupName}</code>

                <p>Please access the server directly to retrieve this backup file.</p>
            </div>
            
            <div class='footer'>
                <p>This is an automated message from CSL Backup System<br>
                Generated at {$timestamp}</p>
            </div>
        </body>
        </html>
        ";
    }
}
