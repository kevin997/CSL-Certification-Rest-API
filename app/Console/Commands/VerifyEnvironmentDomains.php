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
    protected $signature = 'environments:verify-domains';

    protected $description = 'Mark environments whose primary domain resolves and answers HTTPS as domain-verified';

    public function handle(DomainProbe $probe): int
    {
        $verified = 0;

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

        $this->info("Verified {$verified} environment domain(s).");

        return self::SUCCESS;
    }
}
