<?php

namespace App\Support;

use RuntimeException;

class SocialAuthException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
