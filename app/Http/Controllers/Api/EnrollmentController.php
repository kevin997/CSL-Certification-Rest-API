<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Enrollment",
 *     required={"user_id", "course_id", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="course_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="status", type="string", enum={"active", "completed", "expired", "cancelled"}, example="active"),
 *     @OA\Property(property="enrollment_date", type="string", format="date-time"),
 *     @OA\Property(property="completion_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", format="email", example="john@example.com")
 *     ),
 *     @OA\Property(
 *         property="course",
 *         type="object",
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="title", type="string", example="Introduction to CSL Certification"),
 *         @OA\Property(property="status", type="string", example="published")
 *     )
 * )
 */

class EnrollmentController extends Controller
{
    /**
     * Display a listing of the enrollments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'nullable|integer|exists:courses,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:active,completed,expired,cancelled',
            'sort_by' => 'nullable|string|in:created_at,updated_at,expires_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Enrollment::query();

        // Apply course filter
        if ($request->has('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        // Apply user filter
        if ($request->has('user_id')) {
            // Only allow admins or the user themselves to filter by user_id
            if (Auth::id() != $request->input('user_id') && !Auth::user()->is_admin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to view enrollments for other users',
                ], Response::HTTP_FORBIDDEN);
            }
            $query->where('user_id', $request->input('user_id'));
        } else {
            // If no user_id specified, only show enrollments for the current user (unless admin)
            if (!Auth::user()->is_admin) {
                $query->where('user_id', Auth::id());
            }
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $perPage = $request->input('per_page', 15);
        $enrollments = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $enrollments,
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:courses,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:active,completed,expired,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Determine user_id (default to current user if not specified)
        $userId = $request->input('user_id', Auth::id());

        // Check if user has permission to create enrollment for another user
        if ($userId != Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to enroll other users',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if course exists and is published
        $course = Course::findOrFail($request->course_id);
        if ($course->status !== 'published' && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot enroll in an unpublished course',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if enrollment already exists
        $existingEnrollment = Enrollment::where('course_id', $request->course_id)
            ->where('user_id', $userId)
            ->first();

        if ($existingEnrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is already enrolled in this course',
                'data' => $existingEnrollment,
            ], Response::HTTP_CONFLICT);
        }

        // Check enrollment limit if applicable
        if ($course->enrollment_limit) {
            $currentEnrollments = Enrollment::where('course_id', $request->course_id)->count();
            if ($currentEnrollments >= $course->enrollment_limit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Course enrollment limit has been reached',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Create enrollment
        $enrollment = new Enrollment();
        $enrollment->course_id = $request->course_id;
        $enrollment->user_id = $userId;
        $enrollment->status = $request->input('status', 'active');
        $enrollment->expires_at = $request->expires_at;
        $enrollment->notes = $request->notes;
        $enrollment->enrolled_by = Auth::id();
        $enrollment->save();

        // Initialize activity completion records for this enrollment
        $this->initializeActivityCompletionRecords($enrollment);

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment created successfully',
            'data' => $enrollment,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified enrollment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $enrollment = Enrollment::with(['course', 'user', 'activityCompletions'])->findOrFail($id);

        // Check if user has permission to view this enrollment
        if ($enrollment->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this enrollment',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $enrollment,
        ]);
    }

    /**
     * Update the specified enrollment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        // Check if user has permission to update this enrollment
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this enrollment',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'string|in:active,completed,expired,cancelled',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update enrollment fields
        if ($request->has('status')) $enrollment->status = $request->status;
        if ($request->has('expires_at')) $enrollment->expires_at = $request->expires_at;
        if ($request->has('notes')) $enrollment->notes = $request->notes;
        $enrollment->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment updated successfully',
            'data' => $enrollment,
        ]);
    }

    /**
     * Remove the specified enrollment from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $enrollment = Enrollment::findOrFail($id);

        // Check if user has permission to delete this enrollment
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this enrollment',
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete enrollment
        $enrollment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment deleted successfully',
        ]);
    }

    /**
     * Get enrollments for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function myEnrollments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:active,completed,expired,cancelled',
            'sort_by' => 'nullable|string|in:created_at,updated_at,expires_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Enrollment::where('user_id', Auth::id());

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $perPage = $request->input('per_page', 15);
        $enrollments = $query->with('course')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $enrollments,
        ]);
    }

    /**
     * Get enrollments for a specific course.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $courseId
     * @return \Illuminate\Http\Response
     */
    public function courseEnrollments(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        // Check if user has permission to view enrollments for this course
        if ($course->created_by !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view enrollments for this course',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:active,completed,expired,cancelled',
            'sort_by' => 'nullable|string|in:created_at,updated_at,expires_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Enrollment::where('course_id', $courseId);

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $perPage = $request->input('per_page', 15);
        $enrollments = $query->with('user')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $enrollments,
        ]);
    }

    /**
     * Initialize activity completion records for a new enrollment.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return void
     */
    private function initializeActivityCompletionRecords($enrollment)
    {
        $course = Course::with('sections.activities')->findOrFail($enrollment->course_id);
        
        foreach ($course->sections as $section) {
            foreach ($section->activities as $activity) {
                $enrollment->activityCompletions()->create([
                    'course_activity_id' => $activity->id,
                    'status' => 'not_started',
                    'is_completed' => false,
                ]);
            }
        }
    }
}
