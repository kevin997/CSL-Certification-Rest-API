<?php

namespace App\Services\Marketing;

use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Support\PhoneNumber;

class SalesFormCampaignAudience
{
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
        $submissions = SalesFormSubmission::withoutGlobalScopes()
            ->where('sales_form_id', $form->id)
            ->where('environment_id', $form->environment_id)
            ->when(
                $selection->mode === 'selected',
                fn ($query) => $query->whereIn('id', $selection->submissionIds),
            )
            ->when(
                $selection->mode === 'filtered',
                fn ($query) => $query->where('status', $selection->filters['status']),
            )
            ->orderBy('id')
            ->get();

        $preview = [];

        foreach ($channels as $channel) {
            $excluded = array_fill_keys(self::EXCLUSION_REASONS, 0);
            $submissionIds = [];
            $seenCoordinates = [];

            foreach ($submissions as $submission) {
                $coordinate = $this->coordinate($submission, $channel, $form);

                if ($coordinate === null) {
                    $excluded['missing_address']++;

                    continue;
                }

                if ($coordinate === false) {
                    $excluded['invalid_address']++;

                    continue;
                }

                $consent = $this->latestConsent($submission, $channel);
                if ($consent === null) {
                    $excluded['no_consent']++;

                    continue;
                }

                if ($consent->status === MarketingConsent::STATUS_REVOKED) {
                    $excluded['revoked']++;

                    continue;
                }

                if (isset($seenCoordinates[$coordinate])) {
                    $excluded['duplicate']++;

                    continue;
                }

                $seenCoordinates[$coordinate] = true;
                $submissionIds[] = $submission->id;
            }

            $preview[$channel] = [
                'eligible_count' => count($submissionIds),
                'exclusion_counts' => $excluded,
                'submission_ids' => $submissionIds,
            ];
        }

        return new AudiencePreview($preview);
    }

    private function latestConsent(SalesFormSubmission $submission, string $channel): ?MarketingConsent
    {
        return MarketingConsent::withoutGlobalScopes()
            ->where('environment_id', $submission->environment_id)
            ->where('sales_form_submission_id', $submission->id)
            ->where('channel', $channel)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function coordinate(SalesFormSubmission $submission, string $channel, SalesForm $form): string|false|null
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

        $phone = PhoneNumber::normalize($rawPhone, $form->environment?->country_code);

        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1 ? $phone : false;
    }
}
