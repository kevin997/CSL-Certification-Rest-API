<?php

namespace App\Console\Commands;

use App\Models\Environment;
use App\Support\Tenancy\DomainProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Stamps domain_verified_at on active environments whose own domain answers.
 * Only ever sets the flag; an operator clears it from the admin app. Links
 * switch from the shared host to the tenant domain once it is set, so a
 * flapping probe must never move them back.
 */
class VerifyEnvironmentDomains extends Command
{
    protected $signature = 'environments:verify-domains {--recheck : Also probe already-verified domains and clear the flag on the ones that no longer serve}';

    protected $description = 'Mark environments whose primary domain resolves and answers HTTPS as domain-verified';

    public function handle(DomainProbe $probe): int
    {
        $verified = 0;
        $cleared = 0;

        Environment::query()
            ->where('is_active', true)
            ->whereNull('domain_verified_at')
            ->whereNotNull('primary_domain')
            ->orderBy('id')
            ->each(function (Environment $environment) use ($probe, &$verified): void {
                if (! $probe->isLive((string) $environment->primary_domain)) {
                    return;
                }

                $environment->forceFill(['domain_verified_at' => now()])->save();
                $verified++;

                Log::info('tenancy.domain_verified', [
                    'environment_id' => $environment->id,
                    'primary_domain' => $environment->primary_domain,
                ]);
            });

        // The migration backfilled every pre-existing environment as verified,
        // which is right for the ones that work and wrong for the ones whose
        // domain never resolved — and the hourly pass only looks at NULL rows,
        // so it would never revisit them. --recheck is the way back.
        if ($this->option('recheck')) {
            Environment::query()
                ->where('is_active', true)
                ->whereNotNull('domain_verified_at')
                ->whereNotNull('primary_domain')
                ->orderBy('id')
                ->each(function (Environment $environment) use ($probe, &$cleared): void {
                    if ($probe->isLive((string) $environment->primary_domain)) {
                        return;
                    }

                    $environment->forceFill(['domain_verified_at' => null])->save();
                    $cleared++;

                    Log::info('tenancy.domain_verification_cleared', [
                        'environment_id' => $environment->id,
                        'primary_domain' => $environment->primary_domain,
                    ]);
                });

            $this->info("Cleared {$cleared} environment domain(s) that no longer serve.");
        }

        $this->info("Verified {$verified} environment domain(s).");

        return self::SUCCESS;
    }
}
