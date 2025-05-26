<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Activity",
 *     required={"block_id", "title", "type", "order"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="block_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Introduction Video"),
 *     @OA\Property(property="description", type="string", example="Watch this introduction video to understand the certification process", nullable=true),
 *     @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="is_required", type="boolean", example=true),
 *     @OA\Property(property="estimated_time", type="integer", example=15, description="Estimated time in minutes"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Get(
 *     path="/api/blocks/{blockId}/activities",
 *     summary="Get all activities for a block",
 *     description="Returns a list of all activities for a specific block",
 *     operationId="getBlockActivities",
 *     tags={"Activities"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="blockId",
 *         in="path",
 *         description="Block ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="block_id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="title", type="string", example="Introduction Video"),
 *                     @OA\Property(property="description", type="string", example="Watch this introduction video to understand the certification process", nullable=true),
 *                     @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                     @OA\Property(property="order", type="integer", example=1),
 *                     @OA\Property(property="is_required", type="boolean", example=true),
 *                     @OA\Property(property="estimated_time", type="integer", example=15, description="Estimated time in minutes"),
 *                     @OA\Property(property="created_at", type="string", format="date-time"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Block not found"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/blocks/{blockId}/activities",
 *     summary="Create a new activity",
 *     description="Creates a new activity for a specific block",
 *     operationId="createActivity",
 *     tags={"Activities"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="blockId",
 *         in="path",
 *         description="Block ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "type"},
 *             @OA\Property(property="name", type="string", example="Introduction Video"),
 *             @OA\Property(property="description", type="string", example="Watch this introduction video to understand the certification process"),
 *             @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *             @OA\Property(property="is_required", type="boolean", example=true),
 *             @OA\Property(property="order", type="integer", example=1),
 *             @OA\Property(property="settings", type="object", example={"duration": 300, "autoplay": false})
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Activity created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Activity created successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="block_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="name", type="string", example="Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="Watch this introduction video to understand the certification process", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="is_required", type="boolean", example=true),
 *                 @OA\Property(property="settings", type="object", example={"duration": 300, "autoplay": false}),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Block not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/activities/{id}",
 *     summary="Get a specific activity",
 *     description="Returns details of a specific activity",
 *     operationId="getActivity",
 *     tags={"Activities"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Activity ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="block_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="title", type="string", example="Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="Watch this introduction video to understand the certification process", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="is_required", type="boolean", example=true),
 *                 @OA\Property(property="estimated_time", type="integer", example=15, description="Estimated time in minutes"),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time"),
 *                 @OA\Property(
 *                     property="content",
 *                     type="object",
 *                     description="Content specific to the activity type"
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Activity not found"
 *     )
 * )
 *
 * @OA\Put(
 *     path="/api/activities/{id}",
 *     summary="Update an activity",
 *     description="Updates an existing activity",
 *     operationId="updateActivity",
 *     tags={"Activities"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Activity ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Updated Introduction Video"),
 *             @OA\Property(property="description", type="string", example="Updated description for the introduction video"),
 *             @OA\Property(property="is_required", type="boolean", example=false),
 *             @OA\Property(property="order", type="integer", example=2),
 *             @OA\Property(property="settings", type="object", example={"duration": 450, "autoplay": true})
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activity updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Activity updated successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="block_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="name", type="string", example="Updated Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="Updated description for the introduction video", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=2),
 *                 @OA\Property(property="is_required", type="boolean", example=false),
 *                 @OA\Property(property="settings", type="object", example={"duration": 450, "autoplay": true}),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
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
 *
 * @OA\Delete(
 *     path="/api/activities/{id}",
 *     summary="Delete an activity",
 *     description="Deletes an existing activity",
 *     operationId="deleteActivity",
 *     tags={"Activities"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Activity ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activity deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Activity deleted successfully")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Activity not found"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/blocks/{blockId}/activities/reorder",
 *     summary="Reorder activities",
 *     description="Updates the order of activities within a block",
 *     operationId="reorderActivities",
 *     tags={"Activities"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="blockId",
 *         in="path",
 *         description="Block ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"activities"},
 *             @OA\Property(
 *                 property="activities",
 *                 type="array",
 *                 description="Array of activity data with new order",
 *                 @OA\Items(
 *                     type="object",
 *                     required={"id", "order"},
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="order", type="integer", example=1)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activities reordered successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Activities reordered successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="block_id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="title", type="string", example="Introduction Video"),
 *                     @OA\Property(property="description", type="string", example="Watch this introduction video to understand the certification process", nullable=true),
 *                     @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "lesson", "assignment", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                     @OA\Property(property="order", type="integer", example=1),
 *                     @OA\Property(property="is_required", type="boolean", example=true),
 *                     @OA\Property(property="estimated_time", type="integer", example=15, description="Estimated time in minutes"),
 *                     @OA\Property(property="created_at", type="string", format="date-time"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Block not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 */

class ActivityController extends Controller
{
    /**
     * Display a listing of activities for a specific block.
     *
     * @param  int  $blockId
     * @return \Illuminate\Http\Response
     */
    public function index($blockId)
    {
        $block = Block::findOrFail($blockId);
        $template = Template::findOrFail($block->template_id);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view activities for this block',
            ], Response::HTTP_FORBIDDEN);
        }

        $activities = Activity::where('block_id', $blockId)
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $activities,
        ]);
    }

    /**
     * Store a newly created activity in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $blockId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $blockId)
    {
        $block = Block::findOrFail($blockId);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to add activities to this block
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add activities to this block',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:text,video,quiz,lesson,assignment,documentation,event,certificate,feedback',
            'is_required' => 'boolean',
            'order' => 'nullable|integer',
            'settings' => 'nullable|json',
            'learning_objectives' => 'nullable|json',
            'conditions' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // If order is not provided, place the activity at the end
        if (!$request->has('order')) {
            $maxOrder = Activity::where('block_id', $blockId)->max('order') ?? 0;
            $request->merge(['order' => $maxOrder + 1]);
        }

        $activity = Activity::create([
            'block_id' => $blockId,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'is_required' => $request->is_required ?? false,
            'order' => $request->order,
            'settings' => $request->settings,
            'learning_objectives' => $request->learning_objectives,
            'conditions' => $request->conditions,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Activity created successfully',
            'data' => $activity,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified activity.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            // Add detailed logging to identify where the error occurs
            Log::info('Activity show method called with ID: ' . $id);
            
            $activity = Activity::findOrFail($id);
            Log::info('Activity found', ['activity_id' => $activity->id, 'block_id' => $activity->block_id]);
            
            $block = Block::findOrFail($activity->block_id);
            Log::info('Block found', ['block_id' => $block->id, 'template_id' => $block->template_id]);
            
            $template = Template::findOrFail($block->template_id);
            Log::info('Template found', ['template_id' => $template->id, 'is_public' => $template->is_public]);
            
            // Check if user has access to this template
            if (!$template->is_public && $template->created_by !== Auth::id()) {
                Log::warning('Permission denied for user to access activity', [
                    'user_id' => Auth::id(),
                    'template_owner' => $template->created_by,
                    'is_public' => $template->is_public
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to view this activity',
                ], Response::HTTP_FORBIDDEN);
            }

            // Load the specific content based on activity type
            $contentRelation = $this->getContentRelationship($activity->type);
            Log::info('Loading content relation', ['type' => $activity->type, 'relation' => $contentRelation]);
            
            // Check if the relationship exists before trying to load it
            if ($contentRelation && method_exists($activity, $contentRelation)) {
                $activity->load($contentRelation);
                Log::info('Content relation loaded successfully');
            } else {
                // Just log a warning if the relationship doesn't exist
                Log::warning('Relationship does not exist on Activity model', [
                    'relation' => $contentRelation,
                    'available_relations' => get_class_methods($activity)
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $activity,
            ]);
        } catch (\Exception $e) {
            // Log the detailed error
            Log::error('Error in ActivityController@show: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'activity_id' => $id
            ]);
            
            // Return a more informative error response
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified activity in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $activity = Activity::findOrFail($id);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to update this activity
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this activity',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check if template is published and enforce restrictions
        if ($template->status === 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot modify activities in a published template',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'order' => 'integer',
            'settings' => 'nullable|json',
            'learning_objectives' => 'nullable|json',
            'conditions' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activity->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Activity updated successfully',
            'data' => $activity,
        ]);
    }

    /**
     * Remove the specified activity from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to delete this activity
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this activity',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check if template is published and enforce restrictions
        if ($template->status === 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete activities in a published template',
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete the activity
        $activity->delete();

        // Reorder remaining activities
        $remainingActivities = Activity::where('block_id', $block->id)
            ->where('order', '>', $activity->order)
            ->orderBy('order')
            ->get();

        foreach ($remainingActivities as $index => $remainingActivity) {
            $remainingActivity->update(['order' => $activity->order + $index]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Activity deleted successfully',
        ]);
    }

    /**
     * Reorder activities within a block.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $blockId
     * @return \Illuminate\Http\Response
     */
    public function reorder(Request $request, $blockId)
    {
        $block = Block::findOrFail($blockId);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to reorder activities in this block
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to reorder activities in this block',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check if template is published and enforce restrictions
        if ($template->status === 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot reorder activities in a published template',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'activities' => 'required|array',
            'activities.*.id' => 'required|exists:activities,id',
            'activities.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update the order of each activity
        foreach ($request->activities as $activityData) {
            $activity = Activity::findOrFail($activityData['id']);
            
            // Ensure the activity belongs to the specified block
            if ($activity->block_id != $blockId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more activities do not belong to this block',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $activity->update(['order' => $activityData['order']]);
        }

        $updatedActivities = Activity::where('block_id', $blockId)
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Activities reordered successfully',
            'data' => $updatedActivities,
        ]);
    }
    
    /**
     * Duplicate an existing activity.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicate($id)
    {
        // Find the activity to duplicate
        $activity = Activity::findOrFail($id);
        
        // Get the block
        $block = Block::findOrFail($activity->block_id);
        
        // Check if user has permission to duplicate this activity
        $template = Template::findOrFail($block->template_id);
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to duplicate this activity',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Create a duplicate activity
        $newActivity = $activity->replicate();
        $newActivity->title = $activity->title . ' (Copy)';
        
        // Get the highest order in the block and add 1
        $maxOrder = Activity::where('block_id', $block->id)->max('order');
        $newActivity->order = $maxOrder + 1;
        
        $newActivity->save();
        
        // Note: Content duplication would need to be handled separately
        // depending on the activity type
        $contentRelationship = $this->getContentRelationship($activity->type);
        if ($contentRelationship && $activity->$contentRelationship) {
            $content = $activity->$contentRelationship;
            $newContent = $content->replicate();
            $newContent->activity_id = $newActivity->id;
            $newContent->save();
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Activity duplicated successfully',
            'data' => $newActivity->load($contentRelationship ?? []),
        ]);
    }

    /**
     * Get the content relationship name based on activity type.
     *
     * @param  mixed  $type
     * @return string|null
     */
    private function getContentRelationship($type)
    {
        // Convert enum to string if it's an enum object
        if ($type instanceof \App\Enums\ActivityType) {
            $type = $type->value;
        } else if (is_object($type)) {
            // If it's another type of object, try to convert to string
            $type = (string) $type;
        }
        
        // Log the type for debugging
        Log::info('Activity type in getContentRelationship', [
            'type' => $type,
            'type_class' => is_object($type) ? get_class($type) : gettype($type)
        ]);
        
        $contentRelationships = [
            'text' => 'textContent',
            'video' => 'videoContent',
            'quiz' => 'quizContent',
            'lesson' => 'lessonContent',
            'assignment' => 'assignmentContent',
            'documentation' => 'documentationContent',
            'event' => 'eventContent',
            'certificate' => 'certificateContent',
            'feedback' => 'feedbackContent',
            'activity_completion' => 'activityCompletionContent',
            'webinar' => 'webinarContent',
        ];

        // Check if the type exists in our mapping
        if (!isset($contentRelationships[$type])) {
            Log::warning('Unknown activity type encountered', ['type' => $type]);
            return null;
        }
        
        return $contentRelationships[$type];
    }
}
