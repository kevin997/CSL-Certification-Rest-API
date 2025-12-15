<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ArchivalSummaryReport extends Mailable
{
    use Queueable, SerializesModels;

    public array $stats;
    public array $searchStats;
    public object $storageStats;

    public function __construct(array $stats, array $searchStats, object $storageStats)
    {
        $this->stats = $stats;
        $this->searchStats = $searchStats;
        $this->storageStats = $storageStats;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Archival Summary Report')
            ->markdown('emails.archival-summary-report', [
                'stats' => $this->stats,
                'searchStats' => $this->searchStats,
                'storageStats' => $this->storageStats,
            ]);
    }
}
