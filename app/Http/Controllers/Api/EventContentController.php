<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\EventContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="EventContent",
 *     required={"activity_id", "title", "description", "start_date", "end_date", "location"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Certification Workshop"),
 *     @OA\Property(property="description", type="string", example="Join us for a hands-on workshop on certification standards"),
 *     @OA\Property(property="start_date", type="string", format="date-time", example="2025-04-15T09:00:00Z"),
 *     @OA\Property(property="end_date", type="string", format="date-time", example="2025-04-15T17:00:00Z"),
 *     @OA\Property(property="location", type="string", example="Virtual Meeting Room"),
 *     @OA\Property(property="location_type", type="string", enum={"online", "in_person", "hybrid"}, example="online"),
 *     @OA\Property(property="max_attendees", type="integer", example=100, nullable=true),
 *     @OA\Property(property="registration_required", type="boolean", example=true),
 *     @OA\Property(property="registration_url", type="string", example="https://example.com/register", nullable=true),
 *     @OA\Property(
 *         property="speakers",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="name", type="string", example="Jane Doe"),
 *             @OA\Property(property="title", type="string", example="Certification Expert"),
 *             @OA\Property(property="bio", type="string", example="Jane is a leading expert in certification standards", nullable=true),
 *             @OA\Property(property="photo_url", type="string", example="https://example.com/photos/jane.jpg", nullable=true)
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class EventContentController extends Controller
{
    /**
     * Store a newly created event content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/event-content",
     *     summary="Create event content for an activity",
     *     description="Creates new event content for an event-type activity",
     *     operationId="storeEventContent",
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
     *         description="Event content data",
     *         @OA\JsonContent(
     *             required={"title", "description", "start_date", "end_date", "location"},
     *             @OA\Property(property="title", type="string", example="Certification Workshop"),
     *             @OA\Property(property="description", type="string", example="Join us for a hands-on workshop on certification standards"),
     *             @OA\Property(property="start_date", type="string", format="date-time", example="2025-04-15T09:00:00Z"),
     *             @OA\Property(property="end_date", type="string", format="date-time", example="2025-04-15T17:00:00Z"),
     *             @OA\Property(property="location", type="string", example="Virtual Meeting Room"),
     *             @OA\Property(property="location_type", type="string", enum={"online", "in_person", "hybrid"}, example="online"),
     *             @OA\Property(property="max_attendees", type="integer", example=100, nullable=true),
     *             @OA\Property(property="registration_required", type="boolean", example=true),
     *             @OA\Property(property="registration_url", type="string", example="https://example.com/register", nullable=true),
     *             @OA\Property(
     *                 property="speakers",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Jane Doe"),
     *                     @OA\Property(property="title", type="string", example="Certification Expert"),
     *                     @OA\Property(property="bio", type="string", example="Jane is a leading expert in certification standards", nullable=true),
     *                     @OA\Property(property="photo_url", type="string", example="https://example.com/photos/jane.jpg", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Event content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EventContent")
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
        if ($activity->type !== 'event') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type event',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'event_type' => 'required|string|in:webinar,workshop,conference,meeting,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'timezone' => 'required|string|max:50',
            'location_type' => 'required|string|in:online,physical,hybrid',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|string|url',
            'meeting_id' => 'nullable|string|max:100',
            'meeting_password' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:100',
            'host_name' => 'nullable|string|max:255',
            'host_email' => 'nullable|email',
            'max_participants' => 'nullable|integer|min:1',
            'registration_required' => 'boolean',
            'registration_deadline' => 'nullable|date|before:start_date',
            'registration_url' => 'nullable|string|url',
            'calendar_invite' => 'nullable|string|url',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string|max:255',
            'attachments.*.file_url' => 'required_with:attachments|string|url',
            'attachments.*.file_type' => 'required_with:attachments|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if event content already exists for this activity
        $existingContent = EventContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        // Prepare data for storage
        $data = $request->except(['attachments']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('attachments')) {
            $data['attachments'] = json_encode($request->attachments);
        }
        
        // Add activity_id to data
        $data['activity_id'] = $activityId;

        $eventContent = EventContent::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Event content created successfully',
            'data' => $eventContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified event content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/event-content",
     *     summary="Get event content for an activity",
     *     description="Retrieves event content for an event-type activity",
     *     operationId="getEventContent",
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
     *         description="Event content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/EventContent")
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

        $eventContent = EventContent::where('activity_id', $activityId)->firstOrFail();
        
        // Decode JSON fields for the response
        if ($eventContent->attachments) {
            $eventContent->attachments = json_decode($eventContent->attachments);
        }

        return response()->json([
            'status' => 'success',
            'data' => $eventContent,
        ]);
    }

    /**
     * Update the specified event content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/event-content",
     *     summary="Update event content for an activity",
     *     description="Updates event content for an event-type activity",
     *     operationId="updateEventContent",
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
     *         description="Event content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Certification Workshop"),
     *             @OA\Property(property="description", type="string", example="Join us for a hands-on workshop on certification standards"),
     *             @OA\Property(property="start_date", type="string", format="date-time", example="2025-04-15T09:00:00Z"),
     *             @OA\Property(property="end_date", type="string", format="date-time", example="2025-04-15T17:00:00Z"),
     *             @OA\Property(property="location", type="string", example="Virtual Meeting Room"),
     *             @OA\Property(property="location_type", type="string", enum={"online", "in_person", "hybrid"}, example="online"),
     *             @OA\Property(property="max_attendees", type="integer", example=100, nullable=true),
     *             @OA\Property(property="registration_required", type="boolean", example=true),
     *             @OA\Property(property="registration_url", type="string", example="https://example.com/register", nullable=true),
     *             @OA\Property(
     *                 property="speakers",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Jane Doe"),
     *                     @OA\Property(property="title", type="string", example="Certification Expert"),
     *                     @OA\Property(property="bio", type="string", example="Jane is a leading expert in certification standards", nullable=true),
     *                     @OA\Property(property="photo_url", type="string", example="https://example.com/photos/jane.jpg", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Event content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EventContent")
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

        $eventContent = EventContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'event_type' => 'string|in:webinar,workshop,conference,meeting,other',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'timezone' => 'string|max:50',
            'location_type' => 'string|in:online,physical,hybrid',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|string|url',
            'meeting_id' => 'nullable|string|max:100',
            'meeting_password' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:100',
            'host_name' => 'nullable|string|max:255',
            'host_email' => 'nullable|email',
            'max_participants' => 'nullable|integer|min:1',
            'registration_required' => 'boolean',
            'registration_deadline' => 'nullable|date|before:start_date',
            'registration_url' => 'nullable|string|url',
            'calendar_invite' => 'nullable|string|url',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string|max:255',
            'attachments.*.file_url' => 'required_with:attachments|string|url',
            'attachments.*.file_type' => 'required_with:attachments|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['attachments']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('attachments')) {
            $updateData['attachments'] = json_encode($request->attachments);
        }

        $eventContent->update($updateData);

        // Decode JSON fields for the response
        if ($eventContent->attachments) {
            $eventContent->attachments = json_decode($eventContent->attachments);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Event content updated successfully',
            'data' => $eventContent,
        ]);
    }

    /**
     * Remove the specified event content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/event-content",
     *     summary="Delete event content for an activity",
     *     description="Deletes event content for an event-type activity",
     *     operationId="deleteEventContent",
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
     *         description="Event content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Event content deleted successfully")
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

        $eventContent = EventContent::where('activity_id', $activityId)->firstOrFail();
        $eventContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Event content deleted successfully',
        ]);
    }
}
