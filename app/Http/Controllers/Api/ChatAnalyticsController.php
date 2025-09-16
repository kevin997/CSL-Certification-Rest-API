<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatAnalytics\ParticipationMetricsService;
use App\Http\Resources\Analytics\EngagementReportResource;
use App\Http\Resources\Analytics\ParticipationMetricsResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;

class ChatAnalyticsController extends Controller
{
    public function __construct(
        private ParticipationMetricsService $participationService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('environment.slug');
    }

    /**
     * Get comprehensive engagement report for a course
     *
     * @param Request $request
     * @param string $courseId
     * @return JsonResponse|EngagementReportResource
     */
    public function getCourseEngagementReport(Request $request, string $courseId)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date|before_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            ]);

            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();

            // Validate date range (max 1 year)
            if ($startDate->diffInDays($endDate) > 365) {
                return response()->json([
                    'message' => 'Date range cannot exceed 365 days',
                    'error' => 'INVALID_DATE_RANGE'
                ], 400);
            }

            $report = $this->participationService->generateCourseEngagementReport(
                $courseId,
                $startDate,
                $endDate
            );

            return new EngagementReportResource($report);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            logger()->error('Error generating engagement report', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to generate engagement report',
                'error' => 'REPORT_GENERATION_ERROR'
            ], 500);
        }
    }

    /**
     * Get participation metrics for course participants
     *
     * @param Request $request
     * @param string $courseId
     * @return JsonResponse
     */
    public function getParticipationMetrics(Request $request, string $courseId)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date|before_or_equal:today',
                'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
                'role' => 'nullable|string|in:student,instructor,all',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $startDate = Carbon::parse($validated['start_date'] ?? now()->subMonth())->startOfDay();
            $endDate = Carbon::parse($validated['end_date'] ?? now())->endOfDay();
            $role = $validated['role'] ?? 'all';
            $limit = $validated['limit'] ?? 50;

            $metrics = $this->participationService->getParticipationMetrics(
                $courseId,
                $startDate,
                $endDate
            );

            // Filter by role if specified
            if ($role !== 'all') {
                $metrics = array_filter($metrics, fn($m) => $m['role'] === $role);
            }

            // Apply limit
            $metrics = array_slice($metrics, 0, $limit);

            return response()->json([
                'data' => ParticipationMetricsResource::collection($metrics),
                'meta' => [
                    'total' => count($metrics),
                    'course_id' => $courseId,
                    'period' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ],
                    'role_filter' => $role
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            logger()->error('Error fetching participation metrics', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch participation metrics',
                'error' => 'METRICS_FETCH_ERROR'
            ], 500);
        }
    }

    /**
     * Generate participation certificate for a user
     *
     * @param Request $request
     * @param string $courseId
     * @param string $userId
     * @return JsonResponse
     */
    public function generateParticipationCertificate(Request $request, string $courseId, string $userId)
    {
        try {
            // Optional validation for additional parameters
            $validated = $request->validate([
                'force_generate' => 'nullable|boolean',
                'template_override' => 'nullable|string'
            ]);

            $certificate = $this->participationService->generateParticipationCertificate(
                $userId,
                $courseId
            );

            if (!$certificate) {
                return response()->json([
                    'message' => 'User is not eligible for participation certificate',
                    'error' => 'NOT_ELIGIBLE',
                    'eligibility_requirements' => [
                        'min_messages' => config('chat.certificate.min_messages', 10),
                        'min_active_days' => config('chat.certificate.min_active_days', 3),
                        'min_engagement_score' => config('chat.certificate.min_engagement_score', 70)
                    ]
                ], 400);
            }

            return response()->json([
                'message' => 'Participation certificate generated successfully',
                'certificate' => $certificate,
                'generated_at' => now()->toISOString()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            logger()->error('Error generating certificate', [
                'course_id' => $courseId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to generate participation certificate',
                'error' => 'CERTIFICATE_GENERATION_ERROR'
            ], 500);
        }
    }

    /**
     * Get certificate eligibility status for course participants
     *
     * @param string $courseId
     * @return JsonResponse
     */
    public function getCertificateEligibility(string $courseId)
    {
        try {
            $eligibility = $this->participationService->getCertificateEligibilityStatus($courseId);

            return response()->json([
                'data' => $eligibility,
                'meta' => [
                    'course_id' => $courseId,
                    'requirements' => [
                        'min_messages' => config('chat.certificate.min_messages', 10),
                        'min_active_days' => config('chat.certificate.min_active_days', 3),
                        'min_engagement_score' => config('chat.certificate.min_engagement_score', 70)
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            logger()->error('Error fetching certificate eligibility', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch certificate eligibility',
                'error' => 'ELIGIBILITY_FETCH_ERROR'
            ], 500);
        }
    }

    /**
     * Process participation data from chat messages
     * This endpoint would typically be called by the main chat system
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processParticipationData(Request $request)
    {
        try {
            $validated = $request->validate([
                'chat_data' => 'required|array|min:1|max:1000',
                'chat_data.*.user_id' => 'required|string',
                'chat_data.*.course_id' => 'required|string',
                'chat_data.*.message_id' => 'required|string',
                'chat_data.*.created_at' => 'required|date',
                'chat_data.*.content' => 'nullable|string',
                'batch_id' => 'nullable|string'
            ]);

            $this->participationService->processParticipationData($validated['chat_data']);

            return response()->json([
                'message' => 'Participation data processed successfully',
                'processed_count' => count($validated['chat_data']),
                'batch_id' => $validated['batch_id'] ?? null,
                'processed_at' => now()->toISOString()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            logger()->error('Error processing participation data', [
                'batch_size' => count($request->input('chat_data', [])),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to process participation data',
                'error' => 'DATA_PROCESSING_ERROR'
            ], 500);
        }
    }

    /**
     * Get analytics dashboard summary for a course
     *
     * @param Request $request
     * @param string $courseId
     * @return JsonResponse
     */
    public function getDashboardSummary(Request $request, string $courseId)
    {
        try {
            $validated = $request->validate([
                'days' => 'nullable|integer|min:1|max:90'
            ]);

            $days = $validated['days'] ?? 30;
            $endDate = now();
            $startDate = $endDate->copy()->subDays($days);

            $report = $this->participationService->generateCourseEngagementReport(
                $courseId,
                $startDate,
                $endDate
            );

            // Extract key metrics for dashboard
            $summary = [
                'overview' => $report['overview'],
                'top_contributors' => array_slice($report['top_contributors'], 0, 5),
                'certificate_eligibility' => [
                    'total_eligible' => $report['certificate_eligibility']['total_eligible'],
                    'percentage' => $report['overview']['unique_participants'] > 0
                        ? round(($report['certificate_eligibility']['total_eligible'] / $report['overview']['unique_participants']) * 100, 2)
                        : 0
                ],
                'recent_trends' => array_slice($report['engagement_trends'], -7), // Last 7 days
                'peak_activity' => $report['activity_patterns']['peak_activity_hours'] ?? []
            ];

            return response()->json([
                'data' => $summary,
                'meta' => [
                    'course_id' => $courseId,
                    'period_days' => $days,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            logger()->error('Error generating dashboard summary', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to generate dashboard summary',
                'error' => 'DASHBOARD_ERROR'
            ], 500);
        }
    }
}