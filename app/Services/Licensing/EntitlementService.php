<?php

namespace App\Services\Licensing;

use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\PaymentGatewaySetting;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the effective entitlements (plan, limits, features) for an
 * environment from its EnvironmentLicence, falling back to a synthesized
 * Free Forever when no licence row exists (Free is a valid licence, never
 * "missing" — doc §4.1).
 *
 * Phase 4 scope: this service ONLY resolves entitlements. It is NOT enforced
 * anywhere yet — server-side enforcement is Phase 9. The resolution is cached
 * per environment and invalidated by EnvironmentLicenceObserver.
 */
class EntitlementService
{
    private array $resolved;

    private ?Environment $environment;

    private function __construct(array $resolved, ?Environment $environment = null)
    {
        $this->resolved = $resolved;
        $this->environment = $environment;
    }

    /**
     * Build the entitlement resolver for an environment (cached).
     */
    public static function for(Environment $environment): self
    {
        $ttl = (int) config('licensing.entitlement_cache_ttl', 3600);

        $resolved = Cache::remember(
            self::cacheKey((int) $environment->id),
            $ttl,
            fn () => self::resolve($environment)
        );

        return new self($resolved, $environment);
    }

    public static function cacheKey(int $environmentId): string
    {
        return "entitlement:env:{$environmentId}";
    }

    public static function forgetCache(int $environmentId): void
    {
        Cache::forget(self::cacheKey($environmentId));
    }

    /**
     * @return array{plan_type:string, status:string, limits:array, features:array}
     */
    private static function resolve(Environment $environment): array
    {
        $licence = EnvironmentLicence::where('environment_id', $environment->id)->first();

        $planType = $licence?->plan_type ?? EnvironmentLicence::PLAN_FREE;
        $status = $licence?->status ?? EnvironmentLicence::STATUS_FREE_ACTIVE;

        $plan = $licence?->plan_id
            ? Plan::find($licence->plan_id)
            : Plan::where('type', $planType)->first();

        // Fall back to the catalogue Free Forever plan if the licence's plan
        // row is unavailable for any reason.
        if (! $plan) {
            $plan = Plan::where('type', EnvironmentLicence::PLAN_FREE)->first();
        }

        return [
            'plan_type' => $planType,
            'status' => $status,
            'limits' => $plan?->limits ?? [],
            'features' => $plan?->features ?? [],
        ];
    }

    public function planType(): string
    {
        return $this->resolved['plan_type'];
    }

    public function status(): string
    {
        return $this->resolved['status'];
    }

    /**
     * @return array<string, mixed>
     */
    public function limits(): array
    {
        return $this->resolved['limits'];
    }

    /**
     * @return array<string, mixed>
     */
    public function features(): array
    {
        return $this->resolved['features'];
    }

    /**
     * A numeric limit (null = unlimited/absent).
     */
    public function limit(string $key): ?int
    {
        $value = $this->resolved['limits'][$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) ($this->resolved['features'][$key] ?? false);
    }

    public function featureLevel(string $key): ?string
    {
        $value = $this->resolved['features'][$key] ?? null;

        return is_string($value) ? $value : null;
    }

    // ---------------------------------------------------------------------
    // Usage counters (Phase 9). Each is current usage for the resolved
    // environment, cached briefly (usage_cache_ttl, default 60s) so that a
    // limit gate on a hot write path stays a single cheap lookup. Counts are
    // deliberately "current state", never historical totals — over-limit
    // migrated data is only ever READ, never deleted (doc §5 / §12).
    // ---------------------------------------------------------------------

    /**
     * Map a limits[] key to its current usage count for this environment.
     */
    public function usage(string $limitKey): int
    {
        return match ($limitKey) {
            'published_courses' => $this->publishedCoursesCount(),
            'active_learners' => $this->activeLearnersCount(),
            'admin_seats' => $this->adminSeatsCount(),
            'payment_gateways' => $this->paymentGatewaysCount(),
            'certificate_templates' => $this->certificateTemplatesCount(),
            default => 0,
        };
    }

    public function publishedCoursesCount(): int
    {
        return $this->countCached('published_courses', fn (int $envId) => Course::query()
            ->where('environment_id', $envId)
            ->where('status', 'published')
            ->count());
    }

    /**
     * Active learners = distinct environment members who are NOT staff (do not
     * hold an admin-seat role) and have a last_active_at within the measurement
     * window (doc §4.4 — accessed during the current period).
     *
     * Fallback note: on a fresh deploy last_active_at is all-null, so this
     * returns 0 and UNDER-counts. That is safe — it can only fail-open (never
     * over-block a learner enrolment) while heartbeat data accumulates.
     */
    public function activeLearnersCount(): int
    {
        return $this->countCached('active_learners', function (int $envId) {
            $seatRoles = (array) config('licensing.admin_seat_roles', []);
            $windowDays = (int) config('licensing.active_learner_window_days', 30);

            return (int) DB::table('environment_user')
                ->where('environment_id', $envId)
                ->whereNotIn('role', $seatRoles)
                ->whereNotNull('last_active_at')
                ->where('last_active_at', '>=', now()->subDays($windowDays))
                ->distinct()
                ->count('user_id');
        });
    }

    /**
     * Admin/instructor seats = distinct environment members holding a
     * seat-consuming role (config licensing.admin_seat_roles).
     */
    public function adminSeatsCount(): int
    {
        return $this->countCached('admin_seats', function (int $envId) {
            $seatRoles = (array) config('licensing.admin_seat_roles', []);

            return (int) DB::table('environment_user')
                ->where('environment_id', $envId)
                ->whereIn('role', $seatRoles)
                ->distinct()
                ->count('user_id');
        });
    }

    public function paymentGatewaysCount(): int
    {
        return $this->countCached('payment_gateways', fn (int $envId) => PaymentGatewaySetting::query()
            ->where('environment_id', $envId)
            ->where('status', true)
            ->count());
    }

    /**
     * Certificate templates are NOT environment-scoped in the schema (no
     * environment_id column). Best available proxy: templates created by users
     * who belong to this environment. See report — a true per-environment
     * template scope is a schema gap to be closed later.
     */
    public function certificateTemplatesCount(): int
    {
        return $this->countCached('certificate_templates', function (int $envId) {
            $userIds = DB::table('environment_user')
                ->where('environment_id', $envId)
                ->pluck('user_id');

            if ($userIds->isEmpty()) {
                return 0;
            }

            return CertificateTemplate::query()
                ->whereIn('created_by', $userIds)
                ->count();
        });
    }

    /**
     * @param callable(int):int $counter
     */
    private function countCached(string $metric, callable $counter): int
    {
        if (! $this->environment) {
            return 0;
        }

        $envId = (int) $this->environment->id;
        $ttl = (int) config('licensing.usage_cache_ttl', 60);

        return (int) Cache::remember(
            "usage:{$metric}:env:{$envId}",
            $ttl,
            fn () => $counter($envId)
        );
    }
}
