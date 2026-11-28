<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAcademyStaff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademyActivityController extends Controller
{
    public const LOOKBACK_DAYS = 14;

    public function __invoke(Request $request): JsonResponse
    {
        // Staff middleware supplies the trusted tenant; query input never selects an academy.
        $environmentId = $request->attributes->get(EnsureAcademyStaff::ACADEMY_ID);
        abort_unless(is_int($environmentId), 403);

        $validated = $request->validate(['limit' => 'sometimes|required|integer|min:1|max:25']);
        $limit = (int) ($validated['limit'] ?? 15);
        $now = CarbonImmutable::now();
        $since = $now->subDays(self::LOOKBACK_DAYS);

        $orders = DB::table('orders')
            ->where('orders.environment_id', $environmentId)
            ->whereNull('orders.deleted_at')
            ->selectRaw('NULL as context_name');

        $enrollments = DB::table('enrollments')
            ->join('courses', function ($join) {
                $join->on('enrollments.course_id', '=', 'courses.id')
                    ->on('enrollments.environment_id', '=', 'courses.environment_id');
            })
            ->where('enrollments.environment_id', $environmentId)
            ->whereNull('enrollments.deleted_at')
            ->where('enrollments.is_provisional', false)
            ->selectRaw('CASE WHEN courses.deleted_at IS NULL THEN courses.title ELSE NULL END as context_name');

        $submissions = DB::table('sales_form_submissions')
            ->join('sales_forms', function ($join) {
                $join->on('sales_form_submissions.sales_form_id', '=', 'sales_forms.id')
                    ->on('sales_form_submissions.environment_id', '=', 'sales_forms.environment_id');
            })
            ->where('sales_form_submissions.environment_id', $environmentId)
            ->selectRaw('CASE WHEN sales_forms.deleted_at IS NULL THEN sales_forms.title ELSE NULL END as context_name');

        // Fetch one extra per source to detect truncation without counting tables or writing notification rows.
        $rows = $this->events($orders, 'orders', 'order', $since, $now, $limit)
            ->concat($this->events($enrollments, 'enrollments', 'enrollment', $since, $now, $limit))
            ->concat($this->events($submissions, 'sales_form_submissions', 'form_submission', $since, $now, $limit))
            ->sortBy([['occurred_at', 'desc'], ['type', 'asc'], ['resource_id', 'desc']]);

        return response()->json([
            'data' => $rows->take($limit)->values(),
            'meta' => [
                'limit' => $limit,
                'lookback_days' => self::LOOKBACK_DAYS,
                'truncated' => $rows->count() > $limit,
            ],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    private function events(Builder $query, string $table, string $type, CarbonImmutable $since, CarbonImmutable $now, int $limit): Collection
    {
        return $query->addSelect(["{$table}.id as resource_id", "{$table}.created_at as occurred_at"])
            ->whereBetween("{$table}.created_at", [$since, $now])
            ->orderByDesc("{$table}.created_at")
            ->orderByDesc("{$table}.id")
            ->limit($limit + 1)
            ->get()
            ->map(static fn ($row) => [
                'id' => $type.':'.$row->resource_id,
                'type' => $type,
                'occurred_at' => CarbonImmutable::parse($row->occurred_at, config('app.timezone'))->toIso8601String(),
                'resource_id' => (int) $row->resource_id,
                'context_name' => $row->context_name,
            ]);
    }
}
