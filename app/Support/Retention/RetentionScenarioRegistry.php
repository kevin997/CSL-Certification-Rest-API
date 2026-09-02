<?php

namespace App\Support\Retention;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\IssuedCertificate;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Scopes\EnvironmentScope;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The catalogue of retention scenarios. Each scenario knows who it targets
 * (a query) and which message to send. Add a new nudge by adding one entry
 * here — the command/engine picks it up automatically.
 *
 * Priority ordering matters: a recipient who matches several scenarios in one
 * run receives only the highest-priority one, so the most useful nudge wins
 * (e.g. "add a course" before "your account has been quiet").
 *
 * Ported from shopikat's RetentionScenarioRegistry, retargeted at KURSA's two
 * audiences (instructor/learner) and its multi-tenant Environment model.
 *
 * Console-context environment scoping: every model here (Course, Product via
 * raw table, Enrollment, Order, IssuedCertificate) uses the
 * `BelongsToEnvironment` trait, which adds a global `EnvironmentScope` that
 * filters by `session('current_environment_id')`. Outside of an HTTP request
 * (this command always runs from `artisan`/the queue) that session key is
 * never set, so the scope's `apply()` is a no-op and these queries already
 * see every tenant. We still explicitly drop the scope with
 * `withoutGlobalScope(EnvironmentScope::class)` on every Eloquent query below
 * so this engine's cross-tenant behaviour doesn't silently depend on that
 * session-state detail — while deliberately NOT using the blanket
 * `withoutGlobalScopes()` (as `ProcessAbandonedOrdersCommand` does), because
 * that would also strip each model's `SoftDeletingScope` and let
 * soft-deleted courses/products/enrollments leak back into the audience.
 *
 * There is no abandoned-cart/abandoned-order scenario here on purpose — the
 * existing `automations:process-abandoned-orders` command already covers it.
 */
class RetentionScenarioRegistry
{
    /**
     * @return array<int, RetentionScenario>
     */
    public function all(): array
    {
        return [
            // ── Instructors (own ≥1 Environment) ─────────────────────
            new RetentionScenario('instructor_trial_expiring', RetentionScenario::INSTRUCTOR, 95, 7,
                fn () => $this->instructors(fn (Builder $q) => $q
                    ->whereExists($this->hasExpiringSubscriptionSub())
                ),
                'retention.instructor_trial_expiring'),

            new RetentionScenario('instructor_no_course', RetentionScenario::INSTRUCTOR, 90, 5,
                fn () => $this->instructors(fn (Builder $q) => $q
                    ->whereNotExists($this->hasCoursesSub())
                ),
                'retention.instructor_no_course'),

            new RetentionScenario('instructor_no_product', RetentionScenario::INSTRUCTOR, 80, 7,
                fn () => $this->instructors(fn (Builder $q) => $q
                    ->whereExists($this->hasCoursesSub())
                    ->whereNotExists($this->hasProductsSub())
                ),
                'retention.instructor_no_product'),

            new RetentionScenario('instructor_no_sales', RetentionScenario::INSTRUCTOR, 75, 7,
                fn () => $this->instructors(fn (Builder $q) => $q
                    ->whereExists($this->hasProductsSub())
                    ->whereNotExists($this->hasCompletedSalesSub())
                ),
                'retention.instructor_no_sales'),

            new RetentionScenario('instructor_inactive', RetentionScenario::INSTRUCTOR, 50, 14,
                fn () => $this->instructors(fn (Builder $q) => $q
                    ->whereExists($this->hasCoursesSub())
                    ->whereNotExists($this->hasRecentEnrollmentActivitySub())
                ),
                'retention.instructor_inactive'),

            // ── Learners (via Enrollment) ─────────────────────────────
            new RetentionScenario('learner_not_started', RetentionScenario::LEARNER, 85, 7,
                fn () => $this->learners(fn (Builder $q) => $q
                    ->where('enrollments.enrolled_at', '<=', now()->subDays(3))
                    ->where('enrollments.progress_percentage', 0)
                    ->whereNotIn('enrollments.status', [Enrollment::STATUS_COMPLETED, Enrollment::STATUS_DROPPED]),
                    orderBy: 'enrollments.enrolled_at'),
                'retention.learner_not_started'),

            new RetentionScenario('learner_almost_done', RetentionScenario::LEARNER, 85, 10,
                fn () => $this->learners(fn (Builder $q) => $q
                    ->where('enrollments.progress_percentage', '>=', 75)
                    ->where('enrollments.progress_percentage', '<', 100)
                    ->where('enrollments.last_activity_at', '<', now()->subDays(3)),
                    orderBy: 'enrollments.last_activity_at'),
                'retention.learner_almost_done'),

            new RetentionScenario('learner_stalled', RetentionScenario::LEARNER, 75, 7,
                fn () => $this->learners(fn (Builder $q) => $q
                    ->where('enrollments.progress_percentage', '>', 0)
                    ->where('enrollments.progress_percentage', '<', 75)
                    ->where('enrollments.last_activity_at', '<', now()->subDays(7)),
                    orderBy: 'enrollments.last_activity_at'),
                'retention.learner_stalled'),

            new RetentionScenario('certificate_earned', RetentionScenario::LEARNER, 90, 30,
                fn () => $this->certificateEarned(),
                'retention.certificate_earned'),

            new RetentionScenario('learner_completed_upsell', RetentionScenario::LEARNER, 60, 21,
                fn () => $this->learners(fn (Builder $q) => $q
                    ->where('enrollments.status', Enrollment::STATUS_COMPLETED)
                    ->where('enrollments.completed_at', '<=', now()->subDays(14))
                    ->whereNotExists($this->hasOtherActiveEnrollmentSub()),
                    orderBy: 'enrollments.completed_at'),
                'retention.learner_completed_upsell'),

            new RetentionScenario('learner_winback', RetentionScenario::LEARNER, 55, 30,
                fn () => $this->learners(fn (Builder $q) => $q
                    ->whereNotExists($this->hasRecentActivityForSameUserSub())
                    ->whereNotExists($this->hasRecentCompletionForSameUserSub()),
                    orderBy: 'enrollments.last_activity_at'),
                'retention.learner_winback'),
        ];
    }

    // ── Instructor audience + mappers ─────────────────────────────────

    /**
     * @param  callable(Builder): Builder  $filter
     * @return Collection<int, RetentionTarget>
     */
    private function instructors(callable $filter): Collection
    {
        $query = User::query()
            ->whereIn('role', [UserRole::INDIVIDUAL_TEACHER->value, UserRole::COMPANY_TEACHER->value])
            ->where('marketing_opt_in', true)
            ->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('environments')
                ->whereColumn('environments.owner_id', 'users.id')
                ->where('environments.is_active', true)
                ->whereNull('environments.deleted_at')
            );

        return $filter($query)->get(['id', 'name', 'email', 'whatsapp_number'])
            ->map(fn (User $u) => new RetentionTarget(
                RetentionScenario::INSTRUCTOR,
                (string) $u->id,
                $u->whatsapp_number ?: null,
                $u->email,
                (string) $u->name,
                pushUserId: $u->id,
                context: $this->instructorContext($u->id),
            ))
            ->values();
    }

    /**
     * First active environment owned by this user — id feeds push/email
     * branding, primary_domain lets RetentionLinks deep-link the instructor
     * to THEIR own academy's /dashboard (multi-tenant: there is no global
     * dashboard URL; www.getkursa.space is only the marketing landing page).
     */
    private function instructorContext(int $userId): array
    {
        $env = Environment::query()
            ->where('owner_id', $userId)
            ->where('is_active', true)
            ->first(['id', 'primary_domain', 'domain_verified_at']);

        return [
            'environment_id' => $env?->id,
            'environment_url' => $env ? TenantUrl::to($env, '/') : null,
        ];
    }

    /** A user has at least one course across any environment they own. */
    private function hasCoursesSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('courses')
            ->join('environments', 'environments.id', '=', 'courses.environment_id')
            ->whereColumn('environments.owner_id', 'users.id')
            ->whereNull('courses.deleted_at')
            ->whereNull('environments.deleted_at');
    }

    /** A user has at least one product across any environment they own. */
    private function hasProductsSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('products')
            ->join('environments', 'environments.id', '=', 'products.environment_id')
            ->whereColumn('environments.owner_id', 'users.id')
            ->whereNull('products.deleted_at')
            ->whereNull('environments.deleted_at');
    }

    /** A user has at least one completed sale for a product in an environment they own. */
    private function hasCompletedSalesSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('environments', 'environments.id', '=', 'products.environment_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereColumn('environments.owner_id', 'users.id')
            ->where('orders.status', Order::STATUS_COMPLETED)
            ->whereNull('order_items.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('environments.deleted_at');
    }

    /**
     * A subscription for an environment this user owns that's about to lapse:
     * still trialing with trial_ends_at in the next 5 days, or otherwise
     * (non-canceled) with ends_at in the next 5 days.
     */
    private function hasExpiringSubscriptionSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('subscriptions')
            ->join('environments', 'environments.id', '=', 'subscriptions.environment_id')
            ->whereColumn('environments.owner_id', 'users.id')
            ->where('subscriptions.status', '!=', Subscription::STATUS_CANCELED)
            ->where(function ($q2) {
                $q2->whereBetween('subscriptions.trial_ends_at', [now(), now()->addDays(5)])
                    ->orWhereBetween('subscriptions.ends_at', [now(), now()->addDays(5)]);
            })
            ->whereNull('environments.deleted_at');
    }

    /** Any enrollment in an environment this user owns with activity in the last 14 days. */
    private function hasRecentEnrollmentActivitySub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('enrollments')
            ->join('environments', 'environments.id', '=', 'enrollments.environment_id')
            ->whereColumn('environments.owner_id', 'users.id')
            ->where(function ($q2) {
                $q2->where('enrollments.last_activity_at', '>=', now()->subDays(14))
                    ->orWhere('enrollments.updated_at', '>=', now()->subDays(14));
            })
            ->whereNull('enrollments.deleted_at')
            ->whereNull('environments.deleted_at');
    }

    // ── Learner audience + mappers ────────────────────────────────────

    /**
     * Base learner query: one row per matching enrollment, joined to the
     * user/course/environment so scenario filters and the target mapper can
     * read plain columns instead of nested EXISTS checks.
     */
    private function baseLearnerQuery(): Builder
    {
        return Enrollment::query()
            ->withoutGlobalScope(EnvironmentScope::class)
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->leftJoin('environments', 'environments.id', '=', 'enrollments.environment_id')
            ->where('users.marketing_opt_in', true)
            ->whereNull('courses.deleted_at')
            ->where(function ($q) {
                $q->whereNull('environments.deleted_at')->orWhereNull('enrollments.environment_id');
            })
            ->select([
                'enrollments.id as enrollment_id',
                'enrollments.user_id',
                'enrollments.environment_id',
                'enrollments.progress_percentage',
                'enrollments.last_activity_at',
                'enrollments.enrolled_at',
                'enrollments.completed_at',
                'enrollments.status',
                'users.name as user_name',
                'users.email as user_email',
                'users.whatsapp_number as user_whatsapp',
                'courses.title as course_title',
                'environments.id as env_id',
                'environments.primary_domain as env_domain',
                'environments.domain_verified_at as env_domain_verified_at',
            ]);
    }

    /**
     * @param  callable(Builder): Builder  $filter
     * @return Collection<int, RetentionTarget>
     */
    private function learners(callable $filter, string $orderBy): Collection
    {
        return $filter($this->baseLearnerQuery())
            ->orderByDesc($orderBy)
            ->get()
            // One row per user: keep the "latest relevant enrollment" (first
            // after the ordering above) when a user matches on several courses.
            ->unique('user_id')
            ->values()
            ->map(fn ($row) => new RetentionTarget(
                RetentionScenario::LEARNER,
                (string) $row->user_id,
                $row->user_whatsapp ?: null,
                $row->user_email,
                (string) $row->user_name,
                pushUserId: (int) $row->user_id,
                context: [
                    'course' => (string) $row->course_title,
                    'progress' => (string) round((float) $row->progress_percentage),
                    'environment_id' => $row->environment_id,
                    'environment_url' => $row->env_domain
                        ? TenantUrl::to((new Environment)->forceFill([
                            'id' => (int) $row->env_id,
                            'primary_domain' => $row->env_domain,
                            'domain_verified_at' => $row->env_domain_verified_at,
                        ]), '/')
                        : null,
                ],
            ))
            ->values();
    }

    /** Recipients who earned a certificate in the last 3 days. */
    private function certificateEarned(): Collection
    {
        return IssuedCertificate::query()
            ->withoutGlobalScope(EnvironmentScope::class)
            ->join('users', 'users.id', '=', 'issued_certificates.user_id')
            ->join('courses', 'courses.id', '=', 'issued_certificates.course_id')
            ->leftJoin('environments', 'environments.id', '=', 'issued_certificates.environment_id')
            ->where('users.marketing_opt_in', true)
            ->where('issued_certificates.issued_date', '>=', now()->subDays(3))
            // Don't congratulate for a certificate that's since been revoked.
            ->where('issued_certificates.status', '!=', 'revoked')
            ->whereNull('courses.deleted_at')
            ->where(function ($q) {
                $q->whereNull('environments.deleted_at')->orWhereNull('issued_certificates.environment_id');
            })
            ->select([
                'issued_certificates.user_id',
                'issued_certificates.environment_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.whatsapp_number as user_whatsapp',
                'courses.title as course_title',
                'environments.id as env_id',
                'environments.primary_domain as env_domain',
                'environments.domain_verified_at as env_domain_verified_at',
            ])
            ->orderByDesc('issued_certificates.issued_date')
            ->get()
            ->unique('user_id')
            ->values()
            ->map(fn ($row) => new RetentionTarget(
                RetentionScenario::LEARNER,
                (string) $row->user_id,
                $row->user_whatsapp ?: null,
                $row->user_email,
                (string) $row->user_name,
                pushUserId: (int) $row->user_id,
                context: [
                    'course' => (string) $row->course_title,
                    'progress' => '100',
                    'environment_id' => $row->environment_id,
                    'environment_url' => $row->env_domain
                        ? TenantUrl::to((new Environment)->forceFill([
                            'id' => (int) $row->env_id,
                            'primary_domain' => $row->env_domain,
                            'domain_verified_at' => $row->env_domain_verified_at,
                        ]), '/')
                        : null,
                ],
            ))
            ->values();
    }

    /** Another enrollment for the same user that's still enrolled/in-progress (not completed/dropped). */
    private function hasOtherActiveEnrollmentSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('enrollments as e2')
            ->whereColumn('e2.user_id', 'enrollments.user_id')
            ->whereColumn('e2.id', '!=', 'enrollments.id')
            ->whereIn('e2.status', [Enrollment::STATUS_ENROLLED, Enrollment::STATUS_IN_PROGRESS])
            ->whereNull('e2.deleted_at');
    }

    /** Any enrollment for the same user with activity in the last 30 days. */
    private function hasRecentActivityForSameUserSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('enrollments as e2')
            ->whereColumn('e2.user_id', 'enrollments.user_id')
            ->where('e2.last_activity_at', '>=', now()->subDays(30))
            ->whereNull('e2.deleted_at');
    }

    /** Any enrollment for the same user completed in the last 30 days. */
    private function hasRecentCompletionForSameUserSub(): callable
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('enrollments as e2')
            ->whereColumn('e2.user_id', 'enrollments.user_id')
            ->where('e2.completed_at', '>=', now()->subDays(30))
            ->whereNull('e2.deleted_at');
    }
}
