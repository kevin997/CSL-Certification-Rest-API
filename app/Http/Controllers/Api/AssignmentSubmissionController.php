<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Block;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="AssignmentSubmission",
 *     required={"assignment_content_id", "user_id", "status", "attempt_number"},
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="assignment_content_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="submission_text", type="string", example="This is my assignment submission", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"draft", "submitted", "graded", "returned"}, example="submitted"),
 *     @OA\Property(property="score", type="number", format="float", example=85, nullable=true),
 *     @OA\Property(property="feedback", type="string", example="Good work on your submission", nullable=true),
 *     @OA\Property(property="attempt_number", type="integer", example=1),
 *     @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="graded_by", type="integer", format="int64", example=2, nullable=true),
 *     @OA\Property(property="graded_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="files", type="array", @OA\Items(ref="#/components/schemas/AssignmentSubmissionFile"), nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="AssignmentSubmissionFile",
 *     required={"assignment_submission_id", "file_path", "file_name", "file_type", "file_size"},
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="assignment_submission_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="file_path", type="string", example="https://res.cloudinary.com/example/image/upload/v1234567890/example.jpg"),
 *     @OA\Property(property="file_name", type="string", example="assignment.pdf"),
 *     @OA\Property(property="file_type", type="string", example="pdf"),
 *     @OA\Property(property="file_size", type="integer", example=1024000),
 *     @OA\Property(property="is_video", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class AssignmentSubmissionController extends Controller
{
    /**
     * Get all submissions for an assignment
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Get(
     *     path="/activities/{activityId}/submissions",
     *     summary="Get all submissions for an assignment",
     *     description="Returns a list of all submissions for a specific assignment",
     *     operationId="getAssignmentSubmissions",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AssignmentSubmission"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function index($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        $assignmentContent = $this->resolveAssignmentContent($activity, (int) $activityId);

        $isTemplateCreator = (int) $template->created_by === (int) Auth::id();

        $query = AssignmentSubmission::query()
            ->where('assignment_content_id', $assignmentContent->id)
            ->with(['user:id,name,email', 'files', 'criterionScores']);

        // Instructors review every submission; a learner only ever sees their
        // own rows. The endpoint used to refuse learners outright, so nobody
        // could read back their own submission from the course player.
        if (! $isTemplateCreator) {
            $query->where('user_id', Auth::id());
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('created_at')->get(),
        ]);
    }

    /**
     * Get a specific submission
     *
     * @param  int  $activityId
     * @param  int  $submissionId
     * @return Response
     *
     * @OA\Get(
     *     path="/activities/{activityId}/submissions/{submissionId}",
     *     summary="Get a specific assignment submission",
     *     description="Returns details of a specific assignment submission",
     *     operationId="getAssignmentSubmission",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentSubmission")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Submission not found")
     * )
     */
    public function show($activityId, $submissionId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        $assignmentContent = $this->resolveAssignmentContent($activity, (int) $activityId);

        $submission = AssignmentSubmission::with([
            'assignmentContent',
            'user:id,name,email',
            'files',
            'criterionScores.criterion',
        ])
            ->where('assignment_content_id', $assignmentContent->id)
            ->findOrFail($submissionId);

        // Only the learner who owns the row and the template creator may read
        // it. Template access alone used to expose every classmate's work.
        $isOwner = (int) $submission->user_id === (int) Auth::id();
        $isTemplateCreator = (int) $template->created_by === (int) Auth::id();

        if (! $isOwner && ! $isTemplateCreator) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this submission',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $submission,
        ]);
    }

    /**
     * Store a new submission
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Post(
     *     path="/activities/{activityId}/submissions",
     *     summary="Create a new assignment submission",
     *     description="Creates a new submission for an assignment with optional files",
     *     operationId="createAssignmentSubmission",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Submission data",
     *
     *         @OA\JsonContent(
     *             required={},
     *
     *             @OA\Property(property="submission_text", type="string", example="This is my assignment submission", nullable=true),
     *             @OA\Property(property="files", type="array", nullable=true,
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="file_path", type="string", example="https://res.cloudinary.com/example/image/upload/v1234567890/example.jpg"),
     *                     @OA\Property(property="file_name", type="string", example="assignment.pdf"),
     *                     @OA\Property(property="file_type", type="string", example="pdf"),
     *                     @OA\Property(property="file_size", type="integer", example=1024000),
     *                     @OA\Property(property="is_video", type="boolean", example=false, nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Submission created successfully",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment submitted successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentSubmission")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden or maximum attempts reached"),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        Block::findOrFail($activity->block_id);

        // Check if activity is of type assignment
        if ($activity->type->value !== 'assignment') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type assignment',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Legacy assignment activities have no content_id; fall back to the
        // activity_id foreign key so existing rows keep working.
        $assignmentContent = $this->resolveAssignmentContent($activity, (int) $activityId);

        // Validate request
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:draft,submitted',
            'submission_text' => 'nullable|string',
            'fillable_data' => 'nullable|array',
            'fillable_data.values' => 'nullable|array',
            'files' => 'nullable|array',
            'files.*.file_path' => 'required_with:files|string',
            'files.*.file_name' => 'required_with:files|string',
            'files.*.file_type' => 'required_with:files|string',
            'files.*.file_size' => 'required_with:files|numeric',
            'files.*.is_video' => 'boolean',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $assignmentContent) {
            $this->validateFillableValues(
                $validator,
                (array) $request->input('fillable_data.values', []),
                $assignmentContent,
            );
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userId = (int) Auth::id();
        $status = (string) $request->input('status', 'submitted');

        // Attempts are counted on real submissions only: saving a draft must
        // never consume an attempt.
        $nonDraftCount = AssignmentSubmission::query()
            ->where('assignment_content_id', $assignmentContent->id)
            ->where('user_id', $userId)
            ->where('status', '!=', 'draft')
            ->count();

        if ($status === 'submitted'
            && $assignmentContent->max_attempts
            && $nonDraftCount >= (int) $assignmentContent->max_attempts) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have reached the maximum number of attempts for this assignment',
            ], Response::HTTP_FORBIDDEN);
        }

        $submission = AssignmentSubmission::query()
            ->where('assignment_content_id', $assignmentContent->id)
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->first();

        $submissionText = $request->has('submission_text')
            ? $request->input('submission_text')
            : $submission?->submission_text;

        $fillableData = $request->has('fillable_data')
            ? $request->input('fillable_data')
            : $submission?->fillable_data;

        if ($status === 'draft') {
            if ($submission) {
                $submission->fill([
                    'submission_text' => $submissionText,
                    'fillable_data' => $fillableData,
                    'attempt_number' => $nonDraftCount + 1,
                ])->save();
            } else {
                $submission = AssignmentSubmission::create([
                    'assignment_content_id' => $assignmentContent->id,
                    'user_id' => $userId,
                    'submission_text' => $submissionText,
                    'fillable_data' => $fillableData,
                    'status' => 'draft',
                    'attempt_number' => $nonDraftCount + 1,
                ]);
            }

            $message = 'Assignment draft saved successfully';
        } else {
            $attributes = [
                'submission_text' => $submissionText,
                'fillable_data' => $fillableData,
                'status' => 'submitted',
                'submitted_at' => now(),
                'attempt_number' => $nonDraftCount + 1,
            ];

            if ($submission) {
                $submission->fill($attributes)->save();
            } else {
                $submission = AssignmentSubmission::create($attributes + [
                    'assignment_content_id' => $assignmentContent->id,
                    'user_id' => $userId,
                ]);
            }

            $message = 'Assignment submitted successfully';
        }

        // Files are synced when sent: an autosaved draft must not accumulate
        // duplicate rows on every save.
        if ($request->has('files')) {
            $submission->files()->delete();

            foreach ((array) $request->input('files', []) as $fileData) {
                AssignmentSubmissionFile::create([
                    'assignment_submission_id' => $submission->id,
                    'file_path' => $fileData['file_path'],
                    'file_name' => $fileData['file_name'],
                    'file_type' => $fileData['file_type'],
                    'file_size' => $fileData['file_size'],
                    'is_video' => $fileData['is_video'] ?? false,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $submission->fresh()->load('files'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Grade a submission
     *
     * @param  int  $activityId
     * @param  int  $submissionId
     * @return Response
     *
     * @OA\Put(
     *     path="/activities/{activityId}/submissions/{submissionId}/grade",
     *     summary="Grade an assignment submission",
     *     description="Grades a specific assignment submission with score and feedback",
     *     operationId="gradeAssignmentSubmission",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Grading data",
     *
     *         @OA\JsonContent(
     *             required={"score"},
     *
     *             @OA\Property(property="score", type="number", format="float", example=85),
     *             @OA\Property(property="feedback", type="string", example="Good work on your submission", nullable=true),
     *             @OA\Property(property="criterion_scores", type="array", nullable=true,
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="criterion_id", type="integer", example=1),
     *                     @OA\Property(property="score", type="number", format="float", example=8.5),
     *                     @OA\Property(property="feedback", type="string", example="Good analysis", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Submission graded successfully",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Submission graded successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentSubmission")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Submission not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function grade(Request $request, $activityId, $submissionId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Only template creators can grade submissions
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to grade this submission',
            ], Response::HTTP_FORBIDDEN);
        }

        $assignmentContent = $this->resolveAssignmentContent($activity, (int) $activityId);

        $submission = AssignmentSubmission::query()
            ->where('assignment_content_id', $assignmentContent->id)
            ->findOrFail($submissionId);

        // Validate request
        $validator = Validator::make($request->all(), [
            'score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
            'criterion_scores' => 'nullable|array',
            'criterion_scores.*.criterion_id' => 'required_with:criterion_scores|exists:assignment_criteria,id',
            'criterion_scores.*.score' => 'required_with:criterion_scores|numeric|min:0',
            'criterion_scores.*.feedback' => 'nullable|string',
            'correction_data' => 'nullable|array',
            'correction_data.values' => 'nullable|array',
            'correction_data.comments' => 'nullable|array',
            'correction_data.comments.*' => 'nullable|string',
            'correction_data.overall' => 'nullable|string',
            'return_for_correction' => 'boolean',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $assignmentContent) {
            $correctionData = (array) $request->input('correction_data', []);

            $this->validateFillableValues(
                $validator,
                (array) ($correctionData['values'] ?? []),
                $assignmentContent,
                'correction_data.values',
            );

            $this->validateFillableKeys(
                $validator,
                array_keys((array) ($correctionData['comments'] ?? [])),
                $assignmentContent,
                'correction_data.comments',
            );
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $returnForCorrection = $request->boolean('return_for_correction');

        $submission->score = $request->score;
        $submission->feedback = $request->feedback;
        $submission->status = $returnForCorrection ? 'returned' : 'graded';
        $submission->graded_by = Auth::id();
        $submission->graded_at = now();

        // Instructor corrections travel with the submission so the learner can
        // see, field by field, what the instructor changed.
        if ($request->has('correction_data')) {
            $correctionData = (array) $request->input('correction_data', []);
            $correctionData['corrected_by'] = Auth::id();
            $correctionData['corrected_at'] = now()->toIso8601String();
            $submission->correction_data = $correctionData;
        }

        $submission->save();

        // Save criterion scores if provided
        if ($request->has('criterion_scores') && is_array($request->criterion_scores)) {
            foreach ($request->criterion_scores as $criterionScore) {
                $submission->criterionScores()->updateOrCreate(
                    ['assignment_criterion_id' => $criterionScore['criterion_id']],
                    [
                        'score' => $criterionScore['score'],
                        'feedback' => $criterionScore['feedback'] ?? null,
                    ]
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $returnForCorrection
                ? 'Submission returned for correction'
                : 'Submission graded successfully',
            'data' => $submission->load(['files', 'criterionScores.criterion']),
        ]);
    }

    /**
     * Resolve the assignment content for an activity.
     *
     * New rows link through activities.content_id (set by the content
     * controller). Rows written before that link existed only carry
     * assignment_contents.activity_id, so that column is the fallback.
     */
    private function resolveAssignmentContent(Activity $activity, int $activityId): AssignmentContent
    {
        if (
            $activity->content_id
            && in_array($activity->content_type, [null, AssignmentContent::class], true)
        ) {
            $content = AssignmentContent::find($activity->content_id);

            if ($content) {
                return $content;
            }
        }

        return AssignmentContent::where('activity_id', $activityId)->firstOrFail();
    }

    /**
     * Each submitted value must name a field of the content's fillable PDF and
     * carry the type that field expects.
     *
     * @param  array<string, mixed>  $values
     */
    private function validateFillableValues(
        ValidatorContract $validator,
        array $values,
        AssignmentContent $content,
        string $prefix = 'fillable_data.values',
    ): void {
        if ($values === []) {
            return;
        }

        $fields = collect(data_get($content->fillable_pdf, 'fields', []))->keyBy('name');

        foreach ($values as $name => $value) {
            $field = $fields->get($name);

            if (! is_array($field)) {
                $validator->errors()->add(
                    $prefix.'.'.$name,
                    'This field is not defined on the fillable PDF.'
                );

                continue;
            }

            $this->validateFillableValueType(
                $validator,
                $prefix.'.'.$name,
                (string) ($field['type'] ?? 'text'),
                $value,
            );
        }
    }

    /**
     * @param  array<int, string|int>  $keys
     */
    private function validateFillableKeys(
        ValidatorContract $validator,
        array $keys,
        AssignmentContent $content,
        string $prefix,
    ): void {
        $fields = collect(data_get($content->fillable_pdf, 'fields', []))->pluck('name');

        foreach ($keys as $key) {
            if (! $fields->contains($key)) {
                $validator->errors()->add(
                    $prefix.'.'.$key,
                    'This field is not defined on the fillable PDF.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateFillableValueType(
        ValidatorContract $validator,
        string $key,
        string $type,
        mixed $value,
    ): void {
        $valid = match ($type) {
            'text', 'select', 'radio', 'signature' => is_string($value),
            'multiselect' => is_array($value) && array_filter($value, fn ($item) => ! is_string($item)) === [],
            'checkbox' => is_bool($value),
            default => true,
        };

        if ($valid) {
            return;
        }

        $expected = match ($type) {
            'multiselect' => 'an array of strings',
            'checkbox' => 'a boolean',
            'text', 'select', 'radio', 'signature' => 'a string',
            default => 'the expected type',
        };

        $validator->errors()->add($key, 'This value must be '.$expected.'.');
    }
}
