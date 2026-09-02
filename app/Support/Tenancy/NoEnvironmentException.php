<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * A user signed in on the shared host but belongs to no environment. Mapped to
 * 403 { code: no_environment } by the login controllers.
 */
final class NoEnvironmentException extends RuntimeException
{
    public const CODE = 'no_environment';

    public static function forUser(): self
    {
        return new self('You are not a member of any academy yet. Create one from the KURSA website.');
    }
}
