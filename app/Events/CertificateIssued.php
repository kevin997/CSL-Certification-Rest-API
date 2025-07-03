<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\IssuedCertificate;

class CertificateIssued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The issued certificate instance.
     *
     * @var \App\Models\IssuedCertificate
     */
    public $issuedCertificate;
    public function __construct(IssuedCertificate $issuedCertificate)
    {
        $this->issuedCertificate = $issuedCertificate;
    }
}
