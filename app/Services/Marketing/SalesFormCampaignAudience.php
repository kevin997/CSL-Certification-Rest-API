<?php

namespace App\Services\Marketing;

use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Support\PhoneNumber;

class SalesFormCampaignAudience
{
    private const CHUNK_SIZE = 500;

    private const EXCLUSION_REASONS = [
        'missing_address',
        'invalid_address',
        'no_consent',
        'revoked',
        'duplicate',
    ];

    /**
     * Resolve an audience using the form's concrete environment rather than an
     * ambient tenant scope. Returned data deliberately contains counts and
     * submission IDs only; delivery coordinates never leave this service.
     *
     * @param  array<int, string>  $channels
     */
    public function preview(SalesForm $form, AudienceSelection $selection, array $channels): AudiencePreview
    {
        $preview = [];
        $seenCoordinates = [];

        foreach ($channels as $channel) {
            $preview[$channel] = [
                'eligible_count' => 0,
                'exclusion_counts' => array_fill_keys(self::EXCLUSION_REASONS, 0),
                'submission_ids' => [],
            ];
            $seenCoordinates[$channel] = [];
        }

        $countryCode = $form->environment?->country_code;
        $submissions = SalesFormSubmission::withoutGlobalScopes()
            ->where('sales_form_id', $form->id)
            ->where('environment_id', $form->environment_id)
            ->when(
                $selection->mode === 'selected',
                fn ($query) => $query->whereIn('id', $selection->submissionIds),
            )
            ->when(
                $selection->mode === 'filtered',
                function ($query) use ($selection): void {
                    if (array_key_exists('status', $selection->filters)) {
                        $query->where('status', $selection->filters['status']);
                    }

                    if (array_key_exists('name', $selection->filters)) {
                        $name = mb_strtolower($selection->filters['name'], 'UTF-8');
                        $name = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $name);
                        $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$name}%"]);
                    }
                },
            )
            ->select(['id', 'environment_id', 'email', 'phone'])
            ->orderBy('id');

        $submissions->chunkById(self::CHUNK_SIZE, function ($submissions) use (&$preview, &$seenCoordinates, $channels, $form, $countryCode): void {
            $consents = $this->latestConsents(
                $submissions->pluck('id')->all(),
                $channels,
                (int) $form->environment_id
            );

            foreach ($submissions as $submission) {
                foreach ($channels as $channel) {
                    $coordinate = $this->coordinate($submission, $channel, $countryCode);
                    $excluded = &$preview[$channel]['exclusion_counts'];

                    if ($coordinate === null) {
                        $excluded['missing_address']++;
                        unset($excluded);

                        continue;
                    }

                    if ($coordinate === false) {
                        $excluded['invalid_address']++;
                        unset($excluded);

                        continue;
                    }

                    $consent = $consents[$submission->id.':'.$channel] ?? null;
                    if ($consent === null) {
                        $excluded['no_consent']++;
                        unset($excluded);

                        continue;
                    }

                    if ($consent->status === MarketingConsent::STATUS_REVOKED) {
                        $excluded['revoked']++;
                        unset($excluded);

                        continue;
                    }

                    if (isset($seenCoordinates[$channel][$coordinate])) {
                        $excluded['duplicate']++;
                        unset($excluded);

                        continue;
                    }

                    $seenCoordinates[$channel][$coordinate] = true;
                    $preview[$channel]['submission_ids'][] = $submission->id;
                    $preview[$channel]['eligible_count']++;
                    unset($excluded);
                }
            }
        });

        return new AudiencePreview($preview);
    }

    /**
     * @param  array<int, int>  $submissionIds
     * @param  array<int, string>  $channels
     * @return array<string, MarketingConsent>
     */
    private function latestConsents(array $submissionIds, array $channels, int $environmentId): array
    {
        $latest = [];

        MarketingConsent::withoutGlobalScopes()
            ->where('environment_id', $environmentId)
            ->whereIn('sales_form_submission_id', $submissionIds)
            ->whereIn('channel', $channels)
            ->orderBy('sales_form_submission_id')
            ->orderBy('channel')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->each(function (MarketingConsent $consent) use (&$latest): void {
                $key = $consent->sales_form_submission_id.':'.$consent->channel;
                $latest[$key] ??= $consent;
            });

        return $latest;
    }

    private function coordinate(SalesFormSubmission $submission, string $channel, ?string $countryCode): string|false|null
    {
        if ($channel === 'email') {
            $email = trim((string) $submission->email);

            if ($email === '') {
                return null;
            }

            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                ? strtolower($email)
                : false;
        }

        $rawPhone = trim((string) $submission->phone);
        if ($rawPhone === '') {
            return null;
        }

        $phone = PhoneNumber::normalize($rawPhone, $countryCode);

        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1 ? $phone : false;
    }
}
