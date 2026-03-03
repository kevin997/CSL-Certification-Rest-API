<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Enrollment;
use App\Models\Course;
use App\Models\IssuedCertificate;
use App\Models\EnvironmentUser;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SystemDashboardController extends Controller
{
    private function guard(Request $request): bool
    {
        return $request->user() && $request->user()->role->value === 'super_admin';
    }

    /**
     * Aggregated system-wide overview stats
     */
    public function overview(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $now   = Carbon::now();
        $ago30 = $now->copy()->subDays(30);
        $ago60 = $now->copy()->subDays(60);

        $totalEnvs    = Environment::count();
        $activeEnvs   = Environment::where('is_active', true)->count();
        $demoEnvs     = Environment::where('is_demo', true)->count();
        $newEnvs30    = Environment::where('created_at', '>=', $ago30)->count();
        $newEnvsPrev  = Environment::whereBetween('created_at', [$ago60, $ago30])->count();

        $totalUsers   = User::count();
        $newUsers30   = User::where('created_at', '>=', $ago30)->count();

        $totalCourses = Course::count();
        $pubCourses   = Course::where('status', 'published')->count();

        $totalEnroll  = Enrollment::count();
        $newEnroll30  = Enrollment::where('enrolled_at', '>=', $ago30)->count();
        $completedEnr = Enrollment::where('status', 'completed')->count();

        $totalCerts   = IssuedCertificate::count();
        $certs30      = IssuedCertificate::where('issued_date', '>=', $ago30)->count();

        $totalRevenue = Order::where('status', 'completed')->sum('total_amount');
        $revenue30    = Order::where('status', 'completed')->where('created_at', '>=', $ago30)->sum('total_amount');
        $totalOrders  = Order::count();
        $orders30     = Order::where('created_at', '>=', $ago30)->count();

        $envGrowthRate = $newEnvsPrev > 0
            ? round((($newEnvs30 - $newEnvsPrev) / $newEnvsPrev) * 100, 1)
            : ($newEnvs30 > 0 ? 100 : 0);

        return response()->json(['success' => true, 'data' => [
            'environments' => [
                'total'      => $totalEnvs,
                'active'     => $activeEnvs,
                'demo'       => $demoEnvs,
                'new_30d'    => $newEnvs30,
                'growth_rate' => $envGrowthRate,
            ],
            'users' => [
                'total'   => $totalUsers,
                'new_30d' => $newUsers30,
            ],
            'learning' => [
                'total_courses'       => $totalCourses,
                'published_courses'   => $pubCourses,
                'total_enrollments'   => $totalEnroll,
                'new_enrollments_30d' => $newEnroll30,
                'completed_enrollments' => $completedEnr,
                'completion_rate'     => $totalEnroll > 0 ? round(($completedEnr / $totalEnroll) * 100, 1) : 0,
                'total_certificates'  => $totalCerts,
                'certificates_30d'    => $certs30,
            ],
            'revenue' => [
                'total'    => round($totalRevenue, 2),
                'last_30d' => round($revenue30, 2),
                'total_orders' => $totalOrders,
                'orders_30d'   => $orders30,
            ],
        ]]);
    }

    /**
     * Per-environment analytics table
     */
    public function environments(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $envs = Environment::with('owner:id,name,email')
            ->withCount([
                'users as learner_count',
                'courses as course_count',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Attach revenue + enrollment stats per environment
        $envIds = $envs->pluck('id')->toArray();

        $revenues = Order::whereIn('environment_id', $envIds)
            ->where('status', 'completed')
            ->groupBy('environment_id')
            ->select('environment_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as order_count'))
            ->get()
            ->keyBy('environment_id');

        $enrollments = Enrollment::whereIn('environment_id', $envIds)
            ->groupBy('environment_id')
            ->select('environment_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) as completed'))
            ->get()
            ->keyBy('environment_id');

        $certs = IssuedCertificate::whereIn('environment_id', $envIds)
            ->groupBy('environment_id')
            ->select('environment_id', DB::raw('COUNT(*) as cert_count'))
            ->get()
            ->keyBy('environment_id');

        $envs->getCollection()->transform(function ($env) use ($revenues, $enrollments, $certs) {
            $env->revenue     = $revenues->get($env->id)?->revenue ?? 0;
            $env->order_count = $revenues->get($env->id)?->order_count ?? 0;
            $env->enrollments = $enrollments->get($env->id)?->total ?? 0;
            $env->completed_enrollments = $enrollments->get($env->id)?->completed ?? 0;
            $env->certificates = $certs->get($env->id)?->cert_count ?? 0;
            return $env;
        });

        return response()->json(['success' => true, 'data' => $envs]);
    }

    /**
     * Enrollment trends (last 6 months) across ALL environments
     */
    public function enrollmentTrends(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        $data = Enrollment::where('enrolled_at', '>=', Carbon::now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(enrolled_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as enrollments'),
                DB::raw('SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) as completions')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        foreach ($months as $m) {
            $result[] = [
                'month'       => Carbon::createFromFormat('Y-m', $m)->format('M Y'),
                'enrollments' => $data->get($m)?->enrollments ?? 0,
                'completions' => $data->get($m)?->completions ?? 0,
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Revenue trends (last 6 months) across ALL environments
     */
    public function revenueTrends(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        $data = Order::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        foreach ($months as $m) {
            $result[] = [
                'month'   => Carbon::createFromFormat('Y-m', $m)->format('M Y'),
                'revenue' => round($data->get($m)?->revenue ?? 0, 2),
                'orders'  => $data->get($m)?->orders ?? 0,
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Environments created per month (origin map)
     */
    public function environmentGrowth(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        $data = Environment::where('created_at', '>=', Carbon::now()->subMonths(12))
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        foreach ($months as $m) {
            $result[] = [
                'month' => Carbon::createFromFormat('Y-m', $m)->format('M'),
                'count' => $data->get($m)?->count ?? 0,
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Breakdown of environments by country
     */
    public function environmentsByCountry(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $data = Environment::select('country_code', DB::raw('COUNT(*) as count'))
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['country' => $r->country_code ?: 'Unknown', 'count' => $r->count]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Top environments by revenue / learners / courses
     */
    public function topEnvironments(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $metric = $request->input('metric', 'revenue');

        $envs = Environment::with('owner:id,name,email')
            ->withCount(['users as learner_count', 'courses as course_count'])
            ->get();

        $envIds = $envs->pluck('id')->toArray();

        $revenues = Order::whereIn('environment_id', $envIds)
            ->where('status', 'completed')
            ->groupBy('environment_id')
            ->select('environment_id', DB::raw('COALESCE(SUM(total_amount), 0) as revenue'))
            ->get()
            ->keyBy('environment_id');

        $result = $envs->map(function ($env) use ($revenues) {
            return [
                'id'            => $env->id,
                'name'          => $env->name,
                'owner'         => $env->owner?->name,
                'is_active'     => $env->is_active,
                'learner_count' => $env->learner_count ?? 0,
                'course_count'  => $env->course_count ?? 0,
                'revenue'       => round($revenues->get($env->id)?->revenue ?? 0, 2),
                'created_at'    => $env->created_at,
            ];
        });

        $sorted = match ($metric) {
            'learners' => $result->sortByDesc('learner_count'),
            'courses'  => $result->sortByDesc('course_count'),
            default    => $result->sortByDesc('revenue'),
        };

        return response()->json(['success' => true, 'data' => $sorted->values()->take(10)]);
    }

    /**
     * Recent system activity (new envs, new users, recent orders)
     */
    public function recentActivity(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $recentEnvs = Environment::with('owner:id,name,email')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($e) => [
                'type'      => 'environment',
                'message'   => 'New environment "' . $e->name . '" created by ' . ($e->owner?->name ?? 'Unknown'),
                'timestamp' => $e->created_at->diffForHumans(),
                'date'      => $e->created_at,
            ]);

        $recentOrders = Order::with('environment:id,name')
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($o) => [
                'type'      => 'order',
                'message'   => "Order {$o->order_number} completed on " . ($o->environment?->name ?? 'Unknown') . " — $" . number_format($o->total_amount, 2),
                'timestamp' => $o->created_at->diffForHumans(),
                'date'      => $o->created_at,
            ]);

        $recentUsers = User::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'type'      => 'user',
                'message'   => "New user {$u->name} ({$u->email}) registered",
                'timestamp' => $u->created_at->diffForHumans(),
                'date'      => $u->created_at,
            ]);

        $combined = $recentEnvs->concat($recentOrders)->concat($recentUsers)
            ->sortByDesc('date')
            ->take(10)
            ->map(fn($item) => collect($item)->except('date'))
            ->values();

        return response()->json(['success' => true, 'data' => $combined]);
    }
}
