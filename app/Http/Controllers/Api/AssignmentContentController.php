<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\Block;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="AssignmentContent",
 *     required={"activity_id", "title", "description", "submission_type"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Final Project Submission"),
 *     @OA\Property(property="description", type="string", example="Submit your final project demonstrating your understanding of certification concepts"),
 *     @OA\Property(property="submission_type", type="string", enum={"file", "text", "url", "multiple_files"}, example="file"),
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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Create assignment content for an activity",
     *     description="Creates new assignment content for an assignment-type activity",
     *     operationId="storeAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Assignment content data",
     *         @OA\JsonContent(
     *             required={"title", "description", "submission_type"},
     *             @OA\Property(property="title", type="string", example="Final Project Submission"),
     *             @OA\Property(property="description", type="string", example="Submit your final project demonstrating your understanding of certification concepts"),
     *             @OA\Property(property="submission_type", type="string", enum={"file", "text", "url", "multiple_files"}, example="file"),
     *             @OA\Property(property="allowed_file_types", type="string", example="pdf,doc,docx", nullable=true),
     *             @OA\Property(property="max_file_size", type="integer", example=5000000, description="Maximum file size in bytes", nullable=true),
     *             @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="points", type="integer", example=100, nullable=true),
     *             @OA\Property(property="rubric", type="object", nullable=true),
     *             @OA\Property(property="instructions", type="string", example="Please follow these steps to complete your assignment...", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Assignment content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentContent")
     *         )
     *     ),
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
        if ($activity->type !== 'assignment') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type assignment',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'instructions' => 'required|string',
            'due_days' => 'nullable|integer', // Days from enrollment to complete
            'passing_grade' => 'required|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'allow_late_submissions' => 'boolean',
            'late_submission_penalty' => 'nullable|integer|min:0|max:100',
            'submission_type' => 'required|string|in:text,file,link,multiple_files',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
            'max_file_size' => 'nullable|integer', // in MB
            'rubric' => 'nullable|array',
            'rubric.*.criteria' => 'required_with:rubric|string',
            'rubric.*.description' => 'nullable|string',
            'rubric.*.points' => 'required_with:rubric|integer|min:0',
        ]);

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
        $data = $request->except(['allowed_file_types', 'rubric']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('allowed_file_types')) {
            $data['allowed_file_types'] = json_encode($request->allowed_file_types);
        }
        
        if ($request->has('rubric')) {
            $data['rubric'] = json_encode($request->rubric);
        }
        
        // Add activity_id to data
        $data['activity_id'] = $activityId;

        $assignmentContent = AssignmentContent::create($data);

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
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Get assignment content for an activity",
     *     description="Retrieves assignment content for an assignment-type activity",
     *     operationId="getAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignment content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentContent")
     *         )
     *     ),
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

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $assignmentContent = AssignmentContent::where('activity_id', $activityId)->firstOrFail();
        
        // Decode JSON fields for the response
        if ($assignmentContent->allowed_file_types) {
            $assignmentContent->allowed_file_types = json_decode($assignmentContent->allowed_file_types);
        }
        
        if ($assignmentContent->rubric) {
            $assignmentContent->rubric = json_decode($assignmentContent->rubric);
        }

        return response()->json([
            'status' => 'success',
            'data' => $assignmentContent,
        ]);
    }

    /**
     * Update the specified assignment content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Update assignment content for an activity",
     *     description="Updates assignment content for an assignment-type activity",
     *     operationId="updateAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Assignment content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Final Project Submission"),
     *             @OA\Property(property="description", type="string", example="Submit your final project demonstrating your understanding of certification concepts"),
     *             @OA\Property(property="submission_type", type="string", enum={"file", "text", "url", "multiple_files"}, example="file"),
     *             @OA\Property(property="allowed_file_types", type="string", example="pdf,doc,docx", nullable=true),
     *             @OA\Property(property="max_file_size", type="integer", example=5000000, description="Maximum file size in bytes", nullable=true),
     *             @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="points", type="integer", example=100, nullable=true),
     *             @OA\Property(property="rubric", type="object", nullable=true),
     *             @OA\Property(property="instructions", type="string", example="Please follow these steps to complete your assignment...", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignment content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentContent")
     *         )
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
            'due_days' => 'nullable|integer',
            'passing_grade' => 'integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'allow_late_submissions' => 'boolean',
            'late_submission_penalty' => 'nullable|integer|min:0|max:100',
            'submission_type' => 'string|in:text,file,link,multiple_files',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
            'max_file_size' => 'nullable|integer',
            'rubric' => 'nullable|array',
            'rubric.*.criteria' => 'required_with:rubric|string',
            'rubric.*.description' => 'nullable|string',
            'rubric.*.points' => 'required_with:rubric|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['allowed_file_types', 'rubric']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('allowed_file_types')) {
            $updateData['allowed_file_types'] = json_encode($request->allowed_file_types);
        }
        
        if ($request->has('rubric')) {
            $updateData['rubric'] = json_encode($request->rubric);
        }

        $assignmentContent->update($updateData);

        // Decode JSON fields for the response
        if ($assignmentContent->allowed_file_types) {
            $assignmentContent->allowed_file_types = json_decode($assignmentContent->allowed_file_types);
        }
        
        if ($assignmentContent->rubric) {
            $assignmentContent->rubric = json_decode($assignmentContent->rubric);
        }

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
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/assignment-content",
     *     summary="Delete assignment content for an activity",
     *     description="Deletes assignment content for an assignment-type activity",
     *     operationId="deleteAssignmentContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignment content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment content deleted successfully")
     *         )
     *     ),
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
}
