<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class LogClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:clear {--keep=7 : Number of days of logs to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear old log files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing old log files...');
        
        $keep = (int) $this->option('keep');
        $path = storage_path('logs');
        $files = File::files($path);
        $cutoffDate = Carbon::now()->subDays($keep);
        $deleted = 0;
        
        foreach ($files as $file) {
            // Skip the current log file
            if ($file->getFilename() === 'laravel.log') {
                continue;
            }
            
            // Check if the file is a daily log file
            if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $file->getFilename(), $matches)) {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1]);
                
                if ($fileDate->lt($cutoffDate)) {
                    File::delete($file->getPathname());
                    $deleted++;
                    $this->line("Deleted: " . $file->getFilename());
                }
            }
        }
        
        $this->info("Completed! Deleted {$deleted} old log files.");
    }
}
