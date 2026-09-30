<?php

namespace App\Support\Mail;

/**
 * One recipient a bounce report says can never be delivered to.
 */
final readonly class HardBounce
{
    public function __construct(
        public string $email,
        public string $statusCode,
        public ?string $diagnostic,
    ) {}
}
