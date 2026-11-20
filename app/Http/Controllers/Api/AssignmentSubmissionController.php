<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Block;
use App\Models\DocumentationAttachment;
use App\Models\DocumentBlob;
use App\Models\Template;
use App\Services\Documents\DocumentProtectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
 *     @OA\Property(property="document_uuid", type="string", format="uuid", example="9b2f4c1e-6a3d-4f8b-9c7e-1d2a3b4c5d6e", nullable=true, description="Set when the file lives in the private document store; read it through GET /api/documents/{uuid}/content"),
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
     * Fallback when the assignment sets no allowed_file_types.
     *
     * @var array<int, string>
     */
    private const DEFAULT_FILE_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'csv', 'zip',
        'jpg', 'jpeg', 'png', 'webp',
    ];

    /**
     * Hard cap for one submission file when the assignment sets no max_file_size.
     */
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly DocumentProtectionService $documents) {}

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
     *                     @OA\Property(property="file_path", type="string", example="https://res.cloudinary.com/example/image/upload/v1234567890/example.jpg", nullable=true, description="Legacy shape: send this with file_name/file_type/file_size"),
     *                     @OA\Property(property="document_uuid", type="string", format="uuid", nullable=true, description="New shape: uuid returned by POST /api/activities/{activityId}/submission-files"),
     *                     @OA\Property(property="name", type="string", example="my-workbook.pdf", nullable=true),
     *                     @OA\Property(property="file_name", type="string", example="assignment.pdf", nullable=true),
     *                     @OA\Property(property="file_type", type="string", example="pdf", nullable=true),
     *                     @OA\Property(property="file_size", type="integer", example=1024000, nullable=true),
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
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

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
            // A file entry is either a legacy Cloudinary descriptor
            // (file_path/file_name/file_type/file_size) or a document-store
            // reference (document_uuid, optionally with a display name).
            'files.*.file_path' => 'nullable|string',
            'files.*.file_name' => 'nullable|string',
            'files.*.file_type' => 'nullable|string',
            'files.*.file_size' => 'nullable|numeric',
            'files.*.is_video' => 'boolean',
            'files.*.document_uuid' => 'nullable|string|exists:documentation_attachments,uuid',
            'files.*.name' => 'nullable|string|max:255',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $assignmentContent, $template) {
            $this->validateFillableValues(
                $validator,
                (array) $request->input('fillable_data.values', []),
                $assignmentContent,
            );

            $this->validateSubmissionFiles(
                $validator,
                (array) $request->input('files', []),
                $assignmentContent,
                $template,
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
                $documentUuid = $fileData['document_uuid'] ?? null;

                if (is_string($documentUuid) && $documentUuid !== '') {
                    $attachment = DocumentationAttachment::query()
                        ->where('uuid', $documentUuid)
                        ->firstOrFail();

                    AssignmentSubmissionFile::create([
                        'assignment_submission_id' => $submission->id,
                        'document_uuid' => $attachment->uuid,
                        'file_path' => 'doc://'.$attachment->uuid,
                        'file_name' => $this->submissionFileName($fileData, $attachment),
                        'file_type' => (string) $attachment->file_type,
                        'file_size' => (int) $attachment->file_size,
                        'is_video' => false,
                    ]);

                    continue;
                }

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
     * Upload one assignment submission file into the private document store.
     *
     * The browser never gets a public URL: it sends the multipart file here,
     * then references the returned uuid when it submits the assignment, and
     * reads the bytes back through GET /documents/{uuid}/content.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Post(
     *     path="/activities/{activityId}/submission-files",
     *     summary="Upload a file for an assignment submission",
     *     description="Stores the file in the private document store and returns its uuid",
     *     operationId="uploadAssignmentSubmissionFile",
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
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(type="object",
     *
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="name", type="string", example="my-workbook", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="File stored successfully",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string", example="my-workbook.pdf"),
     *                 @OA\Property(property="file_type", type="string", example="application/pdf"),
     *                 @OA\Property(property="file_size", type="integer", example=1024000),
     *                 @OA\Property(property="can_preview", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Activity is not an assignment"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function uploadFile(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type->value !== 'assignment') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type assignment',
            ], Response::HTTP_BAD_REQUEST);
        }

        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        // Same rule as the assignment content show endpoint: the template
        // creator, platform staff, the academy manager, or an enrolled learner.
        if (! $this->documents->canAccessTemplate($user, $template)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to upload a file for this assignment',
            ], Response::HTTP_FORBIDDEN);
        }

        $assignmentContent = $this->resolveAssignmentContent($activity, (int) $activityId);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'name' => 'nullable|string|max:120',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $assignmentContent) {
            $this->validateSubmissionUpload($validator, $request->file('file'), $assignmentContent);
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());

        if (! is_string($bytes) || $bytes === '') {
            return response()->json([
                'status' => 'error',
                'errors' => ['file' => ['The uploaded file is empty.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $original = (string) $file->getClientOriginalName();
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $displayName = trim((string) $request->input('name')) ?: pathinfo($original, PATHINFO_FILENAME);
        $fileName = $extension !== '' ? "{$displayName}.{$extension}" : $displayName;

        $uuid = (string) Str::uuid();

        $attachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'uuid' => $uuid,
            'environment_id' => $template->environment_id ? (int) $template->environment_id : null,
            'storage' => 'database',
            'file_path' => "db://{$uuid}",
            'file_name' => Str::limit($fileName, 120, ''),
            'file_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => strlen($bytes),
            'created_by' => $user->id,
        ]);

        DocumentBlob::create([
            'documentation_attachment_id' => $attachment->id,
            'payload' => base64_encode($bytes),
            'mime_type' => $attachment->file_type,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->documents->present($attachment->fresh()),
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

        // Validate request. A plain grade needs a score; returning work for
        // correction may leave the existing score untouched.
        $validator = Validator::make($request->all(), [
            'score' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => ! $request->boolean('return_for_correction')),
            ],
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

        // Returning for correction without a score must not erase an earlier
        // grade; a provided score still persists.
        if ($request->has('score')) {
            $submission->score = $request->input('score');
        }

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
     * Validate the multipart upload against the assignment's file settings.
     */
    private function validateSubmissionUpload(
        ValidatorContract $validator,
        ?UploadedFile $file,
        AssignmentContent $content,
    ): void {
        if (! $file instanceof UploadedFile) {
            return;
        }

        if (! $file->isValid()) {
            $validator->errors()->add('file', 'The file could not be uploaded. Please try again.');

            return;
        }

        $size = (int) $file->getSize();

        if ($size <= 0) {
            $validator->errors()->add('file', 'The uploaded file is empty.');

            return;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === '' || ! in_array($extension, $this->assignmentAllowedExtensions($content), true)) {
            $validator->errors()->add('file', 'This file type is not allowed for this assignment.');

            return;
        }

        $maxBytes = $this->assignmentMaxBytes($content);

        if ($size > $maxBytes) {
            $validator->errors()->add(
                'file',
                'The file may not be larger than '.(int) ceil($maxBytes / 1024 / 1024).' MB.',
            );
        }
    }

    /**
     * Validate the files.* entries of a submission: each one carries either a
     * legacy Cloudinary descriptor or a document-store reference (uuid).
     *
     * @param  array<int, mixed>  $files
     */
    private function validateSubmissionFiles(
        ValidatorContract $validator,
        array $files,
        AssignmentContent $content,
        Template $template,
    ): void {
        if ($files === []) {
            return;
        }

        $allowedExtensions = $this->assignmentAllowedExtensions($content);

        foreach ($files as $index => $fileData) {
            if (! is_array($fileData)) {
                $validator->errors()->add("files.{$index}", 'Each file entry must be an object.');

                continue;
            }

            $documentUuid = $fileData['document_uuid'] ?? null;

            if (is_string($documentUuid) && $documentUuid !== '') {
                $attachment = DocumentationAttachment::query()
                    ->where('uuid', $documentUuid)
                    ->first();

                if (! $attachment) {
                    // The exists rule already reported this uuid.
                    continue;
                }

                $extension = $this->attachmentExtension($attachment);

                if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
                    $validator->errors()->add(
                        "files.{$index}.document_uuid",
                        'This file type is not allowed for this assignment.',
                    );
                }

                if (
                    $template->environment_id
                    && (int) $attachment->resolvedEnvironmentId() !== (int) $template->environment_id
                ) {
                    $validator->errors()->add(
                        "files.{$index}.document_uuid",
                        'The selected document belongs to another academy.',
                    );
                }

                continue;
            }

            $filePath = $fileData['file_path'] ?? null;

            if (! is_string($filePath) || $filePath === '') {
                $validator->errors()->add(
                    "files.{$index}",
                    'Each file must include either file_path or document_uuid.',
                );

                continue;
            }

            foreach (['file_name', 'file_type'] as $key) {
                if (! isset($fileData[$key]) || (string) $fileData[$key] === '') {
                    $validator->errors()->add("files.{$index}.{$key}", "The {$key} field is required.");
                }
            }

            if (! isset($fileData['file_size']) || ! is_numeric($fileData['file_size'])) {
                $validator->errors()->add("files.{$index}.file_size", 'The file_size field is required.');
            }
        }
    }

    /**
     * The extensions the assignment accepts. `allowed_file_types` is a JSON
     * array of extensions, but may be stored with or without the leading dot.
     *
     * @return array<int, string>
     */
    private function assignmentAllowedExtensions(AssignmentContent $content): array
    {
        $configured = $content->allowed_file_types;

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $extensions = [];

        foreach ((array) $configured as $extension) {
            if (! is_string($extension) && ! is_numeric($extension)) {
                continue;
            }

            $extension = strtolower(ltrim(trim((string) $extension), '.'));

            if ($extension !== '') {
                $extensions[] = $extension;
            }
        }

        return $extensions !== []
            ? array_values(array_unique($extensions))
            : self::DEFAULT_FILE_EXTENSIONS;
    }

    /**
     * Byte cap for one submission file: the assignment's max_file_size (MB)
     * when set, 20 MB otherwise.
     */
    private function assignmentMaxBytes(AssignmentContent $content): int
    {
        $maxMb = $content->max_file_size;

        if ($maxMb !== null && (int) $maxMb > 0) {
            return (int) $maxMb * 1024 * 1024;
        }

        return self::MAX_FILE_BYTES;
    }

    /**
     * Extension of a stored attachment, falling back to its MIME type when the
     * stored name carries none.
     */
    private function attachmentExtension(DocumentationAttachment $attachment): string
    {
        $extension = strtolower(pathinfo((string) $attachment->file_name, PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        return match (strtolower((string) $attachment->file_type)) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/zip' => 'zip',
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => '',
        };
    }

    /**
     * Display name stored on the submission file row: the entry's optional name
     * when the client sends one, otherwise the attachment's own name.
     *
     * @param  array<string, mixed>  $fileData
     */
    private function submissionFileName(array $fileData, DocumentationAttachment $attachment): string
    {
        $name = trim((string) ($fileData['name'] ?? ''));

        return Str::limit($name !== '' ? $name : (string) $attachment->file_name, 255, '');
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
