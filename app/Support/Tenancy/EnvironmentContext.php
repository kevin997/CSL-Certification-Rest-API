<?php

namespace App\Support\Tenancy;

use App\Models\Environment;

/**
 * The outcome of resolving the environment for one request.
 *
 * `source` says where it came from: the request host (tenant host), the
 * authenticated principal's binding (shared host), or nothing.
 */
final class EnvironmentContext
{
    public const SOURCE_HOST = 'host';

    public const SOURCE_BINDING = 'binding';

    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly ?Environment $environment,
        public readonly string $source,
        public readonly string $host,
    ) {}

    public static function none(string $host): self
    {
        return new self(null, self::SOURCE_NONE, $host);
    }

    public function resolved(): bool
    {
        return $this->environment !== null;
    }
}
