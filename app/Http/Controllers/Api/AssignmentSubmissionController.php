<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Block;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="AssignmentSubmission",
 *     required={"assignment_content_id", "user_id", "status", "attempt_number"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="assignment_content_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="submission_text", type="string", example="This is my assignment submission", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"draft", "submitted", "graded"}, example="submitted"),
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
     * @param int $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/submissions",
     *     summary="Get all submissions for an assignment",
     *     description="Returns a list of all submissions for a specific assignment",
     *     operationId="getAssignmentSubmissions",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AssignmentSubmission"))
     *         )
     *     ),
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

        // For viewing submissions, only the template creator or users with access to the template can view all submissions
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view these submissions',
            ], Response::HTTP_FORBIDDEN);
        }
        
        $assignmentContent = AssignmentContent::where('activity_id', $activityId)->firstOrFail();
        
        $submissions = AssignmentSubmission::where('assignment_content_id', $assignmentContent->id)
            ->with(['user:id,name,email', 'files'])
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $submissions,
        ]);
    }
    
    /**
     * Get a specific submission
     *
     * @param int $activityId
     * @param int $submissionId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/submissions/{submissionId}",
     *     summary="Get a specific assignment submission",
     *     description="Returns details of a specific assignment submission",
     *     operationId="getAssignmentSubmission",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentSubmission")
     *         )
     *     ),
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

        // For viewing a specific submission, users can view their own submissions
        // or if they're the template creator or have access to a public template
        $isTemplateCreator = ($template->created_by === Auth::id());
        $hasTemplateAccess = ($template->is_public || $isTemplateCreator);
        
        if (!$hasTemplateAccess) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this submission',
            ], Response::HTTP_FORBIDDEN);
        }
        
        $assignmentContent = AssignmentContent::where('id', $activity->content_id)->firstOrFail();
        $submission = AssignmentSubmission::with(['user:id,name,email', 'files', 'criterionScores.criterion'])
            ->where('assignment_content_id', $assignmentContent->id)
            ->findOrFail($submissionId);
        
        // Additional check: users can only view their own submissions unless they're the template owner
        if (Auth::id() !== $submission->user_id && Auth::id() !== $template->created_by) {
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
     * @param \Illuminate\Http\Request $request
     * @param int $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/submissions",
     *     summary="Create a new assignment submission",
     *     description="Creates a new submission for an assignment with optional files",
     *     operationId="createAssignmentSubmission",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Submission data",
     *         @OA\JsonContent(
     *             required={},
     *             @OA\Property(property="submission_text", type="string", example="This is my assignment submission", nullable=true),
     *             @OA\Property(property="files", type="array", nullable=true,
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="file_path", type="string", example="https://res.cloudinary.com/example/image/upload/v1234567890/example.jpg"),
     *                     @OA\Property(property="file_name", type="string", example="assignment.pdf"),
     *                     @OA\Property(property="file_type", type="string", example="pdf"),
     *                     @OA\Property(property="file_size", type="integer", example=1024000),
     *                     @OA\Property(property="is_video", type="boolean", example=false, nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Submission created successfully",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Assignment submitted successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentSubmission")
     *         )
     *     ),
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
        
        // For submissions, we don't check if the user is the template creator
        // because submissions are made by learners, not template creators
        
        $assignmentContent = AssignmentContent::where('id', $activity->content_id)->firstOrFail();
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'submission_text' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*.file_path' => 'required_with:files|string',
            'files.*.file_name' => 'required_with:files|string',
            'files.*.file_type' => 'required_with:files|string',
            'files.*.file_size' => 'required_with:files|numeric',
            'files.*.is_video' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Check if user has reached maximum attempts
        if ($assignmentContent->max_attempts) {
            $attemptCount = AssignmentSubmission::where('assignment_content_id', $assignmentContent->id)
                ->where('user_id', Auth::id())
                ->count();
                
            if ($attemptCount >= $assignmentContent->max_attempts) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have reached the maximum number of attempts for this assignment',
                ], Response::HTTP_FORBIDDEN);
            }
        }
        
        // Create submission
        $submission = new AssignmentSubmission([
            'assignment_content_id' => $assignmentContent->id,
            'user_id' => Auth::id(),
            'submission_text' => $request->submission_text,
            'status' => 'submitted',
            'attempt_number' => AssignmentSubmission::where('assignment_content_id', $assignmentContent->id)
                ->where('user_id', Auth::id())
                ->count() + 1,
            'submitted_at' => now(),
        ]);
        
        $submission->save();
        
        // Save files if provided
        if ($request->has('files') && is_array($request->files)) {
            foreach ($request->files as $fileData) {
                $submissionFile = new AssignmentSubmissionFile([
                    'assignment_submission_id' => $submission->id,
                    'file_path' => $fileData['file_path'],
                    'file_name' => $fileData['file_name'],
                    'file_type' => $fileData['file_type'],
                    'file_size' => $fileData['file_size'],
                    'is_video' => $fileData['is_video'] ?? false,
                ]);
                
                $submissionFile->save();
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Assignment submitted successfully',
            'data' => $submission->load('files'),
        ], Response::HTTP_CREATED);
    }
    
    /**
     * Grade a submission
     *
     * @param \Illuminate\Http\Request $request
     * @param int $activityId
     * @param int $submissionId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/submissions/{submissionId}/grade",
     *     summary="Grade an assignment submission",
     *     description="Grades a specific assignment submission with score and feedback",
     *     operationId="gradeAssignmentSubmission",
     *     tags={"Assignment Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="ID of the activity",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Grading data",
     *         @OA\JsonContent(
     *             required={"score"},
     *             @OA\Property(property="score", type="number", format="float", example=85),
     *             @OA\Property(property="feedback", type="string", example="Good work on your submission", nullable=true),
     *             @OA\Property(property="criterion_scores", type="array", nullable=true,
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="criterion_id", type="integer", example=1),
     *                     @OA\Property(property="score", type="number", format="float", example=8.5),
     *                     @OA\Property(property="feedback", type="string", example="Good analysis", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submission graded successfully",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Submission graded successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssignmentSubmission")
     *         )
     *     ),
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
        
        $assignmentContent = AssignmentContent::where('id', $activity->content_id)->firstOrFail();
        $submission = AssignmentSubmission::where('assignment_content_id', $assignmentContent->id)
            ->findOrFail($submissionId);
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
            'criterion_scores' => 'nullable|array',
            'criterion_scores.*.criterion_id' => 'required_with:criterion_scores|exists:assignment_criteria,id',
            'criterion_scores.*.score' => 'required_with:criterion_scores|numeric|min:0',
            'criterion_scores.*.feedback' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Update submission
        $submission->score = $request->score;
        $submission->feedback = $request->feedback;
        $submission->status = 'graded';
        $submission->graded_by = Auth::id();
        $submission->graded_at = now();
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
            'message' => 'Submission graded successfully',
            'data' => $submission->load(['files', 'criterionScores.criterion']),
        ]);
    }
}
