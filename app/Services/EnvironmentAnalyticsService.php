<?php

namespace App\Services;

use App\Models\AcademyVisitEvent;
use App\Models\AcademyVisitor;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnvironmentUser;
use App\Models\FeedbackAnswer;
use App\Models\InstructorCommission;
use App\Models\IssuedCertificate;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSubscription;
use App\Models\PushSubscription;
use App\Models\QuizSubmission;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * EnvironmentAnalyticsService
 *
 * A single source of truth for tenant (environment) scoped analytics, built from
 * the model catalog. Each domain method is self-contained and env-scoped using the
 * correct tier:
 *   - direct column / trait  → where('environment_id', $envId)
 *   - relationship chain     → whereHas(...) up to a parent that carries environment_id
 *
 * Every domain is wrapped so one failing query never breaks the whole payload.
 */
class EnvironmentAnalyticsService
{
    /** Revenue-bearing / terminal states. */
    private const COMPLETED = 'completed';
    private const REFUNDED = ['refunded', 'partially_refunded'];

    /**
     * Full environment analytics payload. $range optionally narrows time-based
     * metrics: ['start' => Carbon|string, 'end' => Carbon|string].
     */
    public function overview(int $environmentId, array $range = []): array
    {
        [$start, $end] = $this->resolveRange($range);

        return [
            'environment_id' => $environmentId,
            'range'          => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'audience'       => $this->safe(fn () => $this->audience($environmentId, $start, $end)),
            'learning'       => $this->safe(fn () => $this->learning($environmentId, $start, $end)),
            'assessment'     => $this->safe(fn () => $this->assessment($environmentId)),
            'certificates'   => $this->safe(fn () => $this->certificates($environmentId, $start, $end)),
            'commerce'       => $this->safe(fn () => $this->commerce($environmentId, $start, $end)),
            'subscriptions'  => $this->safe(fn () => $this->subscriptions($environmentId, $start, $end)),
            'payouts'        => $this->safe(fn () => $this->payouts($environmentId)),
            'engagement'     => $this->safe(fn () => $this->engagement($environmentId, $start, $end)),
            'traffic'        => $this->safe(fn () => $this->traffic($environmentId, $start, $end)),
        ];
    }

    // ── Audience (EnvironmentUser is the tenant membership table) ────────────
    private function audience(int $e, Carbon $start, Carbon $end): array
    {
        $base = EnvironmentUser::where('environment_id', $e);
        $total = (clone $base)->count();
        $newInRange = (clone $base)->whereBetween('joined_at', [$start, $end])->count();
        $verified = (clone $base)->whereNotNull('email_verified_at')->count();
        $setupDone = (clone $base)->where('is_account_setup', true)->count();

        $roleMix = (clone $base)->selectRaw('role, COUNT(*) as c')->groupBy('role')
            ->pluck('c', 'role')->toArray();

        $pushOptIns = PushSubscription::where('environment_id', $e)->count();

        return [
            'total_learners'      => $total,
            'new_in_range'        => $newInRange,
            'verified_email_rate' => $this->rate($verified, $total),
            'account_setup_rate'  => $this->rate($setupDone, $total),
            'role_mix'            => $roleMix,
            'push_opt_in_rate'    => $this->rate($pushOptIns, $total),
            'growth'              => $this->dailyCounts(EnvironmentUser::where('environment_id', $e), 'joined_at', $start, $end),
        ];
    }

    // ── Learning content ─────────────────────────────────────────────────────
    private function learning(int $e, Carbon $start, Carbon $end): array
    {
        $courses = Course::where('environment_id', $e);

        return [
            'active_courses'   => (clone $courses)->where('status', 'active')->count(),
            'draft_courses'    => (clone $courses)->where('status', 'draft')->count(),
            'new_courses'      => (clone $courses)->whereBetween('created_at', [$start, $end])->count(),
            'total_courses'    => (clone $courses)->count(),
        ];
    }

    // ── Assessment (QuizSubmission scopes via enrollment) ────────────────────
    private function assessment(int $e): array
    {
        $subs = QuizSubmission::whereHas('enrollment', fn ($q) => $q->where('environment_id', $e));
        $total = (clone $subs)->count();
        $passed = (clone $subs)->where('is_passed', true)->count();
        $avgScore = (float) (clone $subs)->avg('percentage_score');

        return [
            'submissions'    => $total,
            'pass_rate'      => $this->rate($passed, $total),
            'avg_score_pct'  => round($avgScore, 1),
        ];
    }

    // ── Certificates (direct environment_id) ─────────────────────────────────
    private function certificates(int $e, Carbon $start, Carbon $end): array
    {
        $certs = IssuedCertificate::where('environment_id', $e);
        $total = (clone $certs)->count();
        $revoked = (clone $certs)->where('status', 'revoked')->count();
        $issuedInRange = (clone $certs)->whereBetween('issued_date', [$start, $end])->count();
        $expiringSoon = (clone $certs)->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])->count();

        return [
            'total_issued'    => $total,
            'issued_in_range' => $issuedInRange,
            'revocation_rate' => $this->rate($revoked, $total),
            'expiring_30d'    => $expiringSoon,
        ];
    }

    // ── Commerce (Order + Transaction are env-direct; sum total_amount) ───────
    private function commerce(int $e, Carbon $start, Carbon $end): array
    {
        $orders = Order::where('environment_id', $e);
        $completed = (clone $orders)->where('status', self::COMPLETED);

        $grossRevenue = (float) (clone $completed)->sum('total_amount');
        $completedCount = (clone $completed)->count();
        $refundedCount = (clone $orders)->whereIn('status', self::REFUNDED)->count();
        $totalOrders = (clone $orders)->count();

        $statusFunnel = (clone $orders)->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')->pluck('c', 'status')->toArray();

        // Top products by line revenue (OrderItem → order → environment_id)
        $topProducts = OrderItem::whereHas('order', fn ($q) => $q->where('environment_id', $e)->where('status', self::COMPLETED))
            ->selectRaw('product_id, SUM(total) as revenue, SUM(quantity) as units')
            ->groupBy('product_id')->orderByDesc('revenue')->limit(5)->get()->toArray();

        $taxCollected = (float) Transaction::where('environment_id', $e)
            ->where('status', self::COMPLETED)->sum('tax_amount');

        return [
            'gross_revenue'     => round($grossRevenue, 2),
            'completed_orders'  => $completedCount,
            'aov'               => $completedCount ? round($grossRevenue / $completedCount, 2) : 0,
            'refund_rate'       => $this->rate($refundedCount, $totalOrders),
            'status_funnel'     => $statusFunnel,
            'top_products'      => $topProducts,
            'tax_collected'     => round($taxCollected, 2),
            'active_products'   => Product::where('environment_id', $e)->where('status', 'active')->count(),
            'revenue_trend'     => $this->dailySums(
                Order::where('environment_id', $e)->where('status', self::COMPLETED),
                'created_at', 'total_amount', $start, $end
            ),
        ];
    }

    // ── Subscriptions (Subscription = SaaS plan; ProductSubscription = product) ─
    private function subscriptions(int $e, Carbon $start, Carbon $end): array
    {
        // SaaS MRR from platform-plan subscriptions (annual normalised to monthly).
        $active = Subscription::where('environment_id', $e)->where('status', 'active')->with('plan')->get();
        $mrr = $active->reduce(function ($carry, $s) {
            if (! $s->plan) {
                return $carry;
            }
            $monthly = $s->billing_cycle === 'annual'
                ? (float) ($s->plan->price_annual ?? 0) / 12
                : (float) ($s->plan->price_monthly ?? 0);
            return $carry + $monthly;
        }, 0.0);

        $saasCounts = Subscription::where('environment_id', $e)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $canceledInRange = Subscription::where('environment_id', $e)
            ->whereBetween('canceled_at', [$start, $end])->count();
        $activeCount = $saasCounts['active'] ?? 0;

        // Learner product-subscriptions.
        $prodSub = ProductSubscription::where('environment_id', $e);
        $prodCounts = (clone $prodSub)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();

        return [
            'mrr'                   => round($mrr, 2),
            'arr'                   => round($mrr * 12, 2),
            'saas_status_counts'    => $saasCounts,
            'saas_churn_rate'       => $this->rate($canceledInRange, $activeCount + $canceledInRange),
            'product_sub_counts'    => $prodCounts,
            'product_sub_paused'    => (clone $prodSub)->whereNotNull('paused_at')->count(),
        ];
    }

    // ── Instructor payouts ledger ────────────────────────────────────────────
    private function payouts(int $e): array
    {
        $comm = InstructorCommission::where('environment_id', $e);
        $owed = (float) (clone $comm)->whereNull('paid_at')->sum('instructor_payout_amount');
        $paid = (float) (clone $comm)->whereNotNull('paid_at')->sum('instructor_payout_amount');
        $platformFees = (float) (clone $comm)->sum('platform_fee_amount');

        $pendingWithdrawals = (float) WithdrawalRequest::where('environment_id', $e)
            ->where('status', 'pending')->sum('amount');

        return [
            'payout_owed'         => round($owed, 2),
            'payout_paid'         => round($paid, 2),
            'platform_fees'       => round($platformFees, 2),
            'pending_withdrawals' => round($pendingWithdrawals, 2),
        ];
    }

    // ── Engagement (feedback rating, live attendance) ────────────────────────
    private function engagement(int $e, Carbon $start, Carbon $end): array
    {
        // Feedback answers are env-direct; average the numeric answers on rating questions.
        $avgRating = (float) FeedbackAnswer::where('environment_id', $e)
            ->whereHas('question', fn ($q) => $q->where('question_type', 'rating'))
            ->whereNotNull('answer_value')
            ->avg('answer_value');

        $sessions = LiveSession::where('environment_id', $e);

        return [
            'avg_rating'         => round($avgRating, 2),
            'live_sessions'      => (clone $sessions)->count(),
            'upcoming_sessions'  => (clone $sessions)->where('status', 'scheduled')->where('scheduled_at', '>=', now())->count(),
            'ended_sessions'     => (clone $sessions)->where('status', 'ended')->count(),
        ];
    }

    // ── Traffic (AcademyVisitEvent / AcademyVisitor are env-direct) ──────────
    private function traffic(int $e, Carbon $start, Carbon $end): array
    {
        $events = AcademyVisitEvent::where('environment_id', $e)->whereBetween('occurred_at', [$start, $end]);
        $visits = (clone $events)->count();
        $uniqueVisitors = (clone $events)->distinct('visit_hash')->count('visit_hash');

        $byCountry = AcademyVisitor::where('environment_id', $e)
            ->selectRaw('country_code, COUNT(*) as c')->groupBy('country_code')
            ->orderByDesc('c')->limit(5)->pluck('c', 'country_code')->toArray();

        return [
            'visits'          => $visits,
            'unique_visitors' => $uniqueVisitors,
            'visits_by_country' => $byCountry,
            'visits_trend'    => $this->dailyCounts(
                AcademyVisitEvent::where('environment_id', $e), 'occurred_at', $start, $end
            ),
        ];
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function resolveRange(array $range): array
    {
        $end = ! empty($range['end']) ? Carbon::parse($range['end']) : now();
        $start = ! empty($range['start']) ? Carbon::parse($range['start']) : (clone $end)->subDays(30);
        return [$start->startOfDay(), $end->endOfDay()];
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }

    /** Daily row counts for a query, keyed by date, over the range. */
    private function dailyCounts($query, string $dateColumn, Carbon $start, Carbon $end): array
    {
        return $query->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE($dateColumn) as d, COUNT(*) as c")
            ->groupBy('d')->orderBy('d')->pluck('c', 'd')->toArray();
    }

    /** Daily sums of a numeric column for a query, keyed by date, over the range. */
    private function dailySums($query, string $dateColumn, string $sumColumn, Carbon $start, Carbon $end): array
    {
        return $query->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE($dateColumn) as d, SUM($sumColumn) as s")
            ->groupBy('d')->orderBy('d')->pluck('s', 'd')->toArray();
    }

    /** Run a domain closure; on any error log it and return an empty result. */
    private function safe(callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $ex) {
            Log::warning('EnvironmentAnalyticsService domain failed: ' . $ex->getMessage());
            return ['error' => 'unavailable'];
        }
    }
}
