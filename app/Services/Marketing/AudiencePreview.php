<?php

namespace App\Services\Marketing;

final class AudiencePreview
{
    /**
     * @param  array<string, array{eligible_count: int, exclusion_counts: array<string, int>, submission_ids: array<int, int>}>  $channels
     */
    public function __construct(public readonly array $channels) {}

    /**
     * @return array{channels: array<string, array{eligible_count: int, exclusion_counts: array<string, int>, submission_ids: array<int, int>}>}
     */
    public function toArray(): array
    {
        return ['channels' => $this->channels];
    }
}
