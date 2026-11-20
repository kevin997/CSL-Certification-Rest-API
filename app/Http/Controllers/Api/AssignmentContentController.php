<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\Block;
use App\Models\DocumentationAttachment;
use App\Models\Enrollment;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;

/**
 * @OA\Schema(
 *     schema="AssignmentContent",
 *     required={"activity_id", "title", "description", "submission_type"},
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Final Project Submission"),
 *     @OA\Property(property="description", type="string", example="Submit your final project demonstrating your understanding of certification concepts"),
 *     @OA\Property(property="submission_type", type="string", enum={"file", "text", "url", "multiple_files", "fillable_pdf"}, example="file"),
 *     @OA\Property(property="allowed_file_types", type="string", example="pdf,doc,docx", nullable=true),
 *     @OA\Property(property="max_file_size", type="integer", example=5000000, description="Maximum file size in bytes", nullable=true),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="points", type="integer", example=100, nullable=true),
 *     @OA\Property(property="rubric", type="object", nullable=true),
 *     @OA\Property(property="instructions", type="string", example="Please follow these steps to complete your assignment...", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class AssignmentContentController extends Controller
{
    /**
     * Store a newly created assignment content in storage.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Post(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Create assignment content for an activity",
     *     description="Creates new assignment content for an assignment-type activity",
     *     operationId="storeAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Assignment content data",
     *
     *         @OA\JsonContent(
     *             required={"title", "description", "submission_type"},
     *
     *             @OA\Property(property="title", type="string", example="Final Project Submission"),
     *             @OA\Property(property="description", type="string", example="Submit your final project demonstrating your understanding of certification concepts"),
     *             @OA\Property(property="submission_type", type="string", enum={"file", "text", "url", "multiple_files", "fillable_pdf"}, example="file"),
     *             @OA\Property(property="allowed_file_types", type="string", example="pdf,doc,docx", nullable=true),
     *             @OA\Property(property="max_file_size", type="integer", example=5000000, description="Maximum file size in bytes", nullable=true),
     *             @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="points", type="integer", example=100, nullable=true),
     *             @OA\Property(property="rubric", type="object", nullable=true),
     *             @OA\Property(property="instructions", type="string", example="Please follow these steps to complete your assignment...", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Assignment content created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentContent")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid activity type"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to add content to this activity
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add content to this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate activity type
        if ($activity->type->value !== 'assignment') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type assignment',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'instructions' => 'required|string',
            'instruction_format' => 'required|string|in:plain,markdown,html,wysiwyg',
            'due_days' => 'nullable|integer', // Days from enrollment to complete
            'max_points' => 'required|integer|min:1',
            'passing_score' => 'nullable|integer|min:0',
            'passing_grade' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'allow_late_submissions' => 'boolean',
            'late_submission_penalty' => 'nullable|integer|min:0|max:100',
            'submission_type' => 'required|string|in:text,file,link,multiple_files,fillable_pdf',
            ...$this->fillablePdfRules(),
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
            'max_file_size' => 'nullable|integer', // in MB
            'criteria' => 'nullable|array',
            'criteria.*.title' => 'required_with:criteria|string',
            'criteria.*.description' => 'nullable|string',
            'criteria.*.points' => 'required_with:criteria|integer|min:0',
            'resource_files' => 'nullable|array',
            'resource_files.*.id' => 'nullable|integer|exists:files,id',
            'resource_files.*.title' => 'nullable|string',
            'resource_files.*.file_url' => 'required_with:resource_files|string|url',
            'resource_files.*.file_type' => 'nullable|string',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $template) {
            $this->validateFillablePdfDocument($validator, $request, $template);
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if assignment content already exists for this activity
        $existingContent = AssignmentContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assignment content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        // Prepare data for storage
        $data = $request->except(['rubric', 'criteria']);

        // Handle passing_grade/passing_score compatibility
        if ($request->has('max_points')) {
            if ($request->has('passing_score')) {
                $data['passing_score'] = $request->passing_score;
            } elseif ($request->has('passing_grade')) {
                $data['passing_score'] = $request->passing_grade;
            } else {
                $data['passing_score'] = $request->max_points;
            }
        }

        // Extract criteria to handle separately
        $criteria = $request->has('criteria') ? $request->criteria : [];

        // Add activity_id to data
        $data['activity_id'] = $activityId;

        $assignmentContent = AssignmentContent::create($data);

        // Link the new content to the activity so readers can resolve it
        // through activities.content_id / activities.content_type.
        $activity->content_id = $assignmentContent->id;
        $activity->content_type = AssignmentContent::class;
        $activity->save();

        // Save criteria if provided
        if (! empty($criteria)) {
            $order = 1;
            foreach ($criteria as $criterion) {
                $assignmentContent->criteria()->create([
                    'title' => $criterion['title'],
                    'description' => $criterion['description'] ?? null,
                    'points' => $criterion['points'],
                    'order' => $order++,
                ]);
            }
        }

        // Load the criteria relationship for the response
        $assignmentContent->load('criteria');

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment content created successfully',
            'data' => $assignmentContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified assignment content.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Get(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Get assignment content for an activity",
     *     description="Retrieves assignment content for an assignment-type activity",
     *     operationId="getAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Assignment content retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentContent")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function show($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Template owners can always read the content; learners can read it
        // when they are enrolled in a course built from the template.
        if (! $this->canViewTemplateContent($template)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $assignmentContent = AssignmentContent::with('criteria')
            ->where('activity_id', $activityId)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $assignmentContent,
        ]);
    }

    /**
     * Update the specified assignment content in storage.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Put(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Update assignment content for an activity",
     *     description="Updates assignment content for an assignment-type activity",
     *     operationId="updateAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Assignment content data",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="Final Project Submission"),
     *             @OA\Property(property="description", type="string", example="Submit your final project demonstrating your understanding of certification concepts"),
     *             @OA\Property(property="submission_type", type="string", enum={"file", "text", "url", "multiple_files", "fillable_pdf"}, example="file"),
     *             @OA\Property(property="allowed_file_types", type="string", example="pdf,doc,docx", nullable=true),
     *             @OA\Property(property="max_file_size", type="integer", example=5000000, description="Maximum file size in bytes", nullable=true),
     *             @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="points", type="integer", example=100, nullable=true),
     *             @OA\Property(property="rubric", type="object", nullable=true),
     *             @OA\Property(property="instructions", type="string", example="Please follow these steps to complete your assignment...", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Assignment content updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentContent")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to update this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $assignmentContent = AssignmentContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'instructions' => 'string',
            'instruction_format' => 'string|in:plain,markdown,html,wysiwyg',
            'due_days' => 'nullable|integer',
            'max_points' => 'integer|min:1',
            'passing_score' => 'nullable|integer|min:0',
            'passing_grade' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'allow_late_submissions' => 'boolean',
            'late_submission_penalty' => 'nullable|integer|min:0|max:100',
            'submission_type' => 'string|in:text,file,link,multiple_files,fillable_pdf',
            ...$this->fillablePdfRules(),
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
            'max_file_size' => 'nullable|integer',
            'criteria' => 'nullable|array',
            'criteria.*.title' => 'required_with:criteria|string',
            'criteria.*.description' => 'nullable|string',
            'criteria.*.points' => 'required_with:criteria|integer|min:0',
            'resource_files' => 'nullable|array',
            'resource_files.*.id' => 'nullable|integer|exists:files,id',
            'resource_files.*.title' => 'nullable|string',
            'resource_files.*.file_url' => 'required_with:resource_files|string|url',
            'resource_files.*.file_type' => 'nullable|string',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $template) {
            $this->validateFillablePdfDocument($validator, $request, $template);
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $data = $request->except(['rubric', 'criteria']);

        // Handle passing_grade/passing_score compatibility
        if ($request->has('max_points')) {
            if ($request->has('passing_score')) {
                $data['passing_score'] = $request->passing_score;
            } elseif ($request->has('passing_grade')) {
                $data['passing_score'] = $request->passing_grade;
            } else {
                $data['passing_score'] = $request->max_points;
            }
        }

        // Extract criteria to handle separately
        $criteria = $request->has('criteria') ? $request->criteria : [];

        $assignmentContent->update($data);

        // Keep the activity pointer in step (legacy rows were written without it).
        $activity->content_id = $assignmentContent->id;
        $activity->content_type = AssignmentContent::class;
        $activity->save();

        // Update criteria if provided
        if (! empty($criteria)) {
            // Delete existing criteria
            $assignmentContent->criteria()->delete();

            // Create new criteria
            $order = 1;
            foreach ($criteria as $criterion) {
                $assignmentContent->criteria()->create([
                    'title' => $criterion['title'],
                    'description' => $criterion['description'] ?? null,
                    'points' => $criterion['points'],
                    'order' => $order++,
                ]);
            }
        }

        // Load the criteria relationship for the response
        $assignmentContent->load('criteria');

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment content updated successfully',
            'data' => $assignmentContent,
        ]);
    }

    /**
     * Remove the specified assignment content from storage.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Delete(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Delete assignment content for an activity",
     *     description="Deletes assignment content for an assignment-type activity",
     *     operationId="deleteAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Assignment content deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment content deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function destroy($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to delete this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $assignmentContent = AssignmentContent::where('activity_id', $activityId)->firstOrFail();
        $assignmentContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment content deleted successfully',
        ]);
    }

    /**
     * Validation rules shared by store() and update() for fillable PDF
     * documents. Only enforced when submission_type is fillable_pdf.
     *
     * @return array<string, string>
     */
    private function fillablePdfRules(): array
    {
        $required = 'required_if:submission_type,fillable_pdf';

        return [
            'fillable_pdf' => $required.'|array',
            'fillable_pdf.document_uuid' => $required.'|string|exists:documentation_attachments,uuid',
            'fillable_pdf.file_name' => 'nullable|string|max:255',
            'fillable_pdf.file_type' => 'nullable|string|max:150',
            'fillable_pdf.file_size' => 'nullable|integer|min:0',
            'fillable_pdf.page_sizes' => 'nullable|array',
            'fillable_pdf.page_sizes.*.width' => 'required_with:fillable_pdf.page_sizes|numeric',
            'fillable_pdf.page_sizes.*.height' => 'required_with:fillable_pdf.page_sizes|numeric',
            'fillable_pdf.fields' => $required.'|array|min:1',
            'fillable_pdf.fields.*.name' => 'required|string|distinct',
            'fillable_pdf.fields.*.page' => 'required|integer|min:1',
            'fillable_pdf.fields.*.type' => 'required|string|in:text,checkbox,radio,select,multiselect,signature',
            'fillable_pdf.fields.*.rect' => 'required|array',
            'fillable_pdf.fields.*.rect.x' => 'required|numeric|between:0,1',
            'fillable_pdf.fields.*.rect.y' => 'required|numeric|between:0,1',
            'fillable_pdf.fields.*.rect.w' => 'required|numeric|between:0,1',
            'fillable_pdf.fields.*.rect.h' => 'required|numeric|between:0,1',
            'fillable_pdf.fields.*.required' => 'boolean',
            'fillable_pdf.fields.*.options' => 'nullable|array',
            'fillable_pdf.fields.*.option' => 'nullable|string',
            'fillable_pdf.fields.*.max_length' => 'nullable|integer',
        ];
    }

    /**
     * The document behind a fillable PDF must exist, be a PDF, and belong to
     * the academy of the activity's template.
     */
    private function validateFillablePdfDocument(ValidatorContract $validator, Request $request, Template $template): void
    {
        if ($request->input('submission_type') !== 'fillable_pdf') {
            return;
        }

        $uuid = $request->input('fillable_pdf.document_uuid');

        if (! is_string($uuid) || $uuid === '') {
            return;
        }

        $attachment = DocumentationAttachment::query()->where('uuid', $uuid)->first();

        if (! $attachment) {
            return;
        }

        $isPdf = strtolower((string) $attachment->file_type) === 'application/pdf'
            || strtolower((string) pathinfo((string) $attachment->file_name, PATHINFO_EXTENSION)) === 'pdf';

        if (! $isPdf) {
            $validator->errors()->add(
                'fillable_pdf.document_uuid',
                'The selected document must be a PDF.'
            );

            return;
        }

        if ($template->environment_id && (int) $attachment->resolvedEnvironmentId() !== (int) $template->environment_id) {
            $validator->errors()->add(
                'fillable_pdf.document_uuid',
                'The selected document belongs to another academy.'
            );
        }
    }

    /**
     * Template owners and public templates are readable by everyone; a private
     * template is readable by learners enrolled in a course built from it.
     */
    private function canViewTemplateContent(Template $template): bool
    {
        if ($template->is_public || $template->created_by === Auth::id()) {
            return true;
        }

        return Enrollment::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', [
                Enrollment::STATUS_ENROLLED,
                Enrollment::STATUS_IN_PROGRESS,
                Enrollment::STATUS_COMPLETED,
            ])
            ->when($template->environment_id, function ($query) use ($template) {
                $query->where('environment_id', $template->environment_id);
            })
            ->whereHas('course', fn ($query) => $query->where('template_id', $template->id))
            ->exists();
    }
}
