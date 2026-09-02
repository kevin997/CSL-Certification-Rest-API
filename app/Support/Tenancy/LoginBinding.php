<?php

namespace App\Support\Tenancy;

final class LoginBinding
{
    /**
     * @param  array<int, array<string, mixed>>  $environments
     */
    public function __construct(
        public readonly ?int $environmentId,
        public readonly bool $requiresSelection = false,
        public readonly array $environments = [],
    ) {}

    public static function to(int $environmentId): self
    {
        return new self($environmentId);
    }

    public static function none(): self
    {
        return new self(null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $environments
     */
    public static function select(array $environments): self
    {
        return new self(null, true, $environments);
    }
}
