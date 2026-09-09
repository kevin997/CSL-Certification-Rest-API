<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademyVisitEvent;
use App\Models\AcademyVisitor;
use App\Models\Invoice;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsWidgetsController extends Controller
{
    /**
     * How much of a client-supplied string is kept.
     *
     * Matches the column width set by the widen_academy_visit_tracking_headers
     * migration. If one moves the other has to: a limit above the column is a
     * 500 waiting for a long enough header, and a limit below it throws away
     * data the column could have held.
     */
    private const HEADER_WIDTH = 1024;

    private function getEnvironmentId(Request $request): ?int
    {
        $environment = $request->get('environment');
        if ($environment && isset($environment->id)) {
            return (int) $environment->id;
        }

        $sessionEnvId = session('current_environment_id');

        return $sessionEnvId ? (int) $sessionEnvId : null;
    }

    private function parseDateRange(Request $request): array
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $startDate = $start ? Carbon::parse($start)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $end ? Carbon::parse($end)->endOfDay() : Carbon::now()->endOfDay();

        return [$startDate, $endDate];
    }

    /**
     * Clamp a client-supplied string to what its column can hold.
     *
     * mb_substr, not substr: a multi-byte character cut in half is invalid
     * UTF-8, which MySQL rejects with a different error rather than storing it.
     * An empty string is stored as null, so "sent nothing" and "sent blank" do
     * not become two different things in the analytics.
     */
    private function bounded(?string $value, int $limit = self::HEADER_WIDTH): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = mb_substr($value, 0, $limit);

        return $value === '' ? null : $value;
    }

    private function getClientIp(Request $request): ?string
    {
        $forwarded = $request->header('CF-Connecting-IP')
            ?: $request->header('X-Forwarded-For')
            ?: $request->header('X-Real-IP');

        if ($forwarded) {
            $parts = explode(',', $forwarded);

            return trim($parts[0]);
        }

        return $request->ip();
    }

    private function hashIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        $salt = (string) config('app.key', '');

        return hash('sha256', $salt.'|'.$ip);
    }

    private function isPublicIp(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public function trackVisit(Request $request): JsonResponse
    {
        $environmentId = $this->getEnvironmentId($request);
        if (! $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected',
            ], 400);
        }

        $visitHash = (string) $request->input('visit_hash', '');
        if ($visitHash === '' || strlen($visitHash) > 200) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid visit_hash',
            ], 422);
        }

        // Every one of these is client-supplied and unbounded: two are request
        // body fields and two are headers, and the columns behind them are
        // varchar(255). Ninety-three requests a day were lost to SQLSTATE[22001]
        // because Facebook's in-app browser sends a user agent of nearly three
        // hundred characters — a visitor arriving from a Facebook link was
        // answered with a 500 instead of being counted.
        //
        // Truncated rather than merely widened. A wider column moves the ceiling;
        // it does not remove it, and nothing stops a client sending a header of
        // any length at all. visit_hash above was already bounded, so the
        // question had been asked of one field of five.
        $path = $this->bounded($request->input('path'));
        $referrer = $this->bounded($request->input('referrer'));
        $userAgent = $this->bounded($request->header('User-Agent'));
        $acceptLanguage = $this->bounded($request->header('Accept-Language'));
        $ip = $this->getClientIp($request);
        $ipHash = $this->hashIp($ip);

        $now = Carbon::now();
        $throttleWindowMinutes = 30;

        $visitor = AcademyVisitor::where('environment_id', $environmentId)
            ->where('visit_hash', $visitHash)
            ->first();

        if (! $visitor) {
            $visitor = AcademyVisitor::create([
                'environment_id' => $environmentId,
                'visit_hash' => $visitHash,
                'visits_count' => 0,
                'first_seen_at' => $now,
                'last_seen_at' => null,
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
                'accept_language' => $acceptLanguage,
            ]);
        }

        $shouldCountVisit = true;
        if ($visitor->last_seen_at) {
            $shouldCountVisit = Carbon::parse($visitor->last_seen_at)->lt($now->copy()->subMinutes($throttleWindowMinutes));
        }

        if ($shouldCountVisit) {
            $visitor->visits_count = (int) $visitor->visits_count + 1;
            if (! $visitor->first_seen_at) {
                $visitor->first_seen_at = $now;
            }

            $visitor->last_seen_at = $now;
            $visitor->ip_hash = $ipHash;
            $visitor->user_agent = $userAgent;
            $visitor->accept_language = $acceptLanguage;
            $visitor->save();

            AcademyVisitEvent::create([
                'environment_id' => $environmentId,
                'visit_hash' => $visitHash,
                'path' => $path,
                'referrer' => $referrer,
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
                'occurred_at' => $now,
            ]);
        }

        if ((! $visitor->country_code || ! $visitor->country_name) && $ip) {
            $apiKey = (string) config('services.ipgeolocation.api_key');

            if ($apiKey !== '' && $this->isPublicIp($ip)) {
                try {
                    $response = Http::timeout(5)->get('https://api.ipgeolocation.io/v2/ipgeo', [
                        'apiKey' => $apiKey,
                        'ip' => $ip,
                        'fields' => 'location,network.company.name',
                    ]);

                    if ($response->successful()) {
                        $geo = $response->json();

                        $location = is_array($geo) ? ($geo['location'] ?? null) : null;
                        $network = is_array($geo) ? ($geo['network'] ?? null) : null;
                        $company = is_array($network) ? ($network['company'] ?? null) : null;

                        if (is_array($location)) {
                            $visitor->country_code = $location['country_code2'] ?? null;
                            $visitor->country_name = $location['country_name'] ?? null;
                            $visitor->state_prov = $location['state_prov'] ?? null;
                            $visitor->city = $location['city'] ?? null;
                        }

                        if (is_array($company)) {
                            $visitor->isp = $company['name'] ?? null;
                        }

                        $visitor->geo_data = $geo;
                        $visitor->save();
                    } elseif ($response->status() !== 429) {
                        Log::warning('IP geolocation request failed', [
                            'status' => $response->status(),
                            'body' => $response->json(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('IP geolocation request threw an exception', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'counted' => $shouldCountVisit,
                'visits_count' => (int) $visitor->visits_count,
                'country_code' => $visitor->country_code,
            ],
        ]);
    }

    public function financialWidgets(Request $request): JsonResponse
    {
        $environmentId = $this->getEnvironmentId($request);
        if (! $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected',
            ], 400);
        }

        [$startDate, $endDate] = $this->parseDateRange($request);

        $transactions = Transaction::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $netEarnings = (float) (clone $transactions)
            ->select(DB::raw('COALESCE(SUM(amount - fee_amount), 0) as total'))
            ->value('total');

        $platformCommission = (float) (clone $transactions)->sum('fee_amount');

        $unpaidInvoiceStatuses = ['draft', 'sent', 'overdue'];
        $unpaidInvoicesQuery = Invoice::where('environment_id', $environmentId)
            ->whereIn('status', $unpaidInvoiceStatuses)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $unpaidInvoicesCount = (int) (clone $unpaidInvoicesQuery)->count();
        $unpaidInvoicesTotal = (float) (clone $unpaidInvoicesQuery)->sum('total_fee_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'net_earnings' => $netEarnings,
                'platform_commission' => $platformCommission,
                'unpaid_invoices' => [
                    'count' => $unpaidInvoicesCount,
                    'total' => $unpaidInvoicesTotal,
                ],
                'currency' => 'USD',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    public function trafficWidgets(Request $request): JsonResponse
    {
        $environmentId = $this->getEnvironmentId($request);
        if (! $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected',
            ], 400);
        }

        [$startDate, $endDate] = $this->parseDateRange($request);

        $totalVisits = (int) AcademyVisitEvent::where('environment_id', $environmentId)
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->count();

        $uniqueVisitors = (int) AcademyVisitEvent::where('environment_id', $environmentId)
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->distinct()
            ->count('visit_hash');

        $visitsByCountry = AcademyVisitEvent::query()
            ->leftJoin('academy_visitors', function ($join) {
                $join->on('academy_visit_events.environment_id', '=', 'academy_visitors.environment_id')
                    ->on('academy_visit_events.visit_hash', '=', 'academy_visitors.visit_hash');
            })
            ->where('academy_visit_events.environment_id', $environmentId)
            ->whereBetween('academy_visit_events.occurred_at', [$startDate, $endDate])
            ->select([
                DB::raw("COALESCE(academy_visitors.country_code, 'UN') as country_code"),
                DB::raw('COUNT(*) as visits'),
            ])
            ->groupBy(DB::raw("COALESCE(academy_visitors.country_code, 'UN')"))
            ->orderByDesc('visits')
            ->limit(10)
            ->get();

        $maxTimeSpentSeconds = (int) DB::table('enrollment_analytics')
            ->join('enrollments', 'enrollment_analytics.enrollment_id', '=', 'enrollments.id')
            ->where('enrollments.environment_id', $environmentId)
            ->whereBetween('enrollment_analytics.created_at', [$startDate, $endDate])
            ->max('enrollment_analytics.session_duration');

        return response()->json([
            'success' => true,
            'data' => [
                'total_visits' => $totalVisits,
                'unique_visitors' => $uniqueVisitors,
                'visits_per_country' => $visitsByCountry,
                'max_time_spent_seconds' => $maxTimeSpentSeconds,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }
}
