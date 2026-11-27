<?php

namespace App\Console\Commands;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordAdminLicenceGrantExtension extends Command
{
    protected $signature = 'licences:record-admin-grant-extension
        {domain : Verified academy domain}
        {expected-end : Current term end in database time, YYYY-MM-DD HH:MM:SS}
        {reference : Operator authorization reference}
        {--apply : Persist the two grant periods and update the licence end}';

    protected $description = 'Record an existing unpaid admin White Label grant and one 12-month extension. Does not record a payment.';

    public function handle(): int
    {
        $domain = strtolower(trim($this->argument('domain')));
        $reference = trim($this->argument('reference'));
        $expectedEnd = $this->argument('expected-end');
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,99}$/D', $reference)
            || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $expectedEnd)) {
            $this->error('Provide an exact current term end and an operator authorization reference.');

            return self::FAILURE;
        }

        try {
            $end = Carbon::createFromFormat('!Y-m-d H:i:s', $expectedEnd, config('app.timezone'));
            if (! $end || $end->toDateTimeString() !== $expectedEnd) {
                throw new \DomainException('Invalid current term end.');
            }
            $extension = $end->copy()->addYearNoOverflow();
            $result = DB::transaction(function () use ($domain, $reference, $end, $extension): string {
                $environment = Environment::query()->where('primary_domain', $domain)->lockForUpdate()->first();
                if (! $environment || ! $environment->is_active || $environment->is_demo || ! $environment->domain_verified_at) {
                    throw new \DomainException('The domain is not an active, verified, non-demo academy.');
                }

                $licence = EnvironmentLicence::query()->where('environment_id', $environment->id)->lockForUpdate()->first();
                if (! $licence || $licence->plan_type !== EnvironmentLicence::PLAN_WHITE_LABEL
                    || $licence->status !== EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE
                    || $licence->activated_by_transaction_id !== null || $licence->cancel_at_period_end
                    || $licence->pending_plan_type || ! $licence->starts_at || ! $licence->ends_at
                    || $licence->starts_at->gte($end) || $licence->starts_at->isFuture()) {
                    throw new \DomainException('Expected an active, transaction-free White Label admin licence with no scheduled changes.');
                }

                $grants = DB::table('environment_licence_grants')->where('environment_id', $environment->id)->orderBy('starts_at')->get();
                $periods = [[$licence->starts_at, $end], [$end, $extension]];
                if ($grants->isNotEmpty()) {
                    if ($licence->ends_at->eq($extension) && $grants->count() === 2
                        && $grants->every(fn ($grant, $index) => $grant->authorization_ref === $reference
                            && $grant->plan_type === $licence->plan_type && $grant->revoked_at === null
                            && $grant->starts_at === $periods[$index][0]->toDateTimeString()
                            && $grant->ends_at === $periods[$index][1]->toDateTimeString())) {
                        return 'Already recorded; no changes.';
                    }

                    throw new \DomainException('Existing grant history differs. Nothing changed.');
                }

                if (! $licence->ends_at->eq($end) || ! $end->isFuture()) {
                    throw new \DomainException('The current period does not match the expected active term.');
                }

                if (! $this->option('apply')) {
                    return 'Dry run only; no changes.';
                }

                foreach ($periods as [$from, $to]) {
                    DB::table('environment_licence_grants')->insert([
                        'environment_id' => $environment->id, 'plan_type' => $licence->plan_type,
                        'starts_at' => $from, 'ends_at' => $to, 'authorization_ref' => $reference,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                $licence->ends_at = $extension;
                $licence->save();

                return 'Two administrative grant periods recorded; no payment was created.';
            });
            $this->info($result.' Term end: '.$extension->toDateTimeString());

            return self::SUCCESS;
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
