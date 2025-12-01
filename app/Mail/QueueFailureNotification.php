<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QueueFailureNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The queue status data.
     *
     * @var array
     */
    public $queueData;

    /**
     * Create a new message instance.
     *
     * @param array $queueData
     * @return void
     */
    public function __construct(array $queueData)
    {
        $this->queueData = $queueData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = config('app.name', 'CSL Certification Rest API');
        $environment = config('app.env', 'production');
        
        return $this->from(config('mail.from.address'), $appName)
                    ->subject("⚠️ Queue Failure Alert - {$environment} Environment")
                    ->markdown('emails.queue.failure', [
                        'queueData' => $this->queueData,
                        'environment' => $environment,
                        'appName' => $appName,
                        'timestamp' => now()->format('Y-m-d H:i:s')
                    ]);
    }
}
