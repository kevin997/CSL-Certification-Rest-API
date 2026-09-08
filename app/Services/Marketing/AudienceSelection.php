<?php

namespace App\Services\Marketing;

final class AudienceSelection
{
    /**
     * @param  array<int, int>  $submissionIds
     * @param  array{status?: string}  $filters
     */
    private function __construct(
        public readonly string $mode,
        public readonly array $submissionIds = [],
        public readonly array $filters = [],
    ) {}

    /**
     * @param  array{mode: string, submission_ids?: array<int, int>, filters?: array{status?: string}}  $selection
     */
    public static function fromArray(array $selection): self
    {
        return new self(
            $selection['mode'],
            array_values($selection['submission_ids'] ?? []),
            $selection['filters'] ?? [],
        );
    }
}
