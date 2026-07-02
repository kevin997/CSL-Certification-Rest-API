<?php

namespace App\Events;

use App\Models\SalesFormSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesFormSubmitted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SalesFormSubmission $submission,
    ) {}
}
