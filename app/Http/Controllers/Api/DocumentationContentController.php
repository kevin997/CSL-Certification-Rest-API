<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\DocumentationContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="DocumentationContent",
 *     required={"activity_id", "title", "content", "version"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="API Documentation"),
 *     @OA\Property(property="content", type="string", example="This documentation covers the API endpoints and usage..."),
 *     @OA\Property(property="version", type="string", example="1.0"),
 *     @OA\Property(property="format", type="string", enum={"markdown", "html", "pdf"}, example="markdown"),
 *     @OA\Property(
 *         property="attachments",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="name", type="string", example="API Schema"),
 *             @OA\Property(property="file_path", type="string", example="path/to/schema.json"),
 *             @OA\Property(property="file_type", type="string", example="application/json"),
 *             @OA\Property(property="description", type="string", example="JSON schema for the API", nullable=true)
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class DocumentationContentController extends Controller
{
    /**
     * Store a newly created documentation content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/documentation-content",
     *     summary="Create documentation content for an activity",
     *     description="Creates new documentation content for a documentation-type activity",
     *     operationId="storeDocumentationContent",
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
     *         description="Documentation content data",
     *         @OA\JsonContent(
     *             required={"title", "content", "version"},
     *             @OA\Property(property="title", type="string", example="API Documentation"),
     *             @OA\Property(property="content", type="string", example="This documentation covers the API endpoints and usage..."),
     *             @OA\Property(property="version", type="string", example="1.0"),
     *             @OA\Property(property="format", type="string", enum={"markdown", "html", "pdf"}, example="markdown"),
     *             @OA\Property(
     *                 property="attachments",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="API Schema"),
     *                     @OA\Property(property="file_path", type="string", example="path/to/schema.json"),
     *                     @OA\Property(property="file_type", type="string", example="application/json"),
     *                     @OA\Property(property="description", type="string", example="JSON schema for the API", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Documentation content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Documentation content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DocumentationContent")
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
        if ($activity->type !== 'documentation') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type documentation',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'format' => 'required|string|in:plain,markdown,html',
            'version' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string|max:255',
            'attachments.*.file_url' => 'required_with:attachments|string|url',
            'attachments.*.file_type' => 'required_with:attachments|string|max:50',
            'attachments.*.file_size' => 'nullable|integer', // in KB
            'related_links' => 'nullable|array',
            'related_links.*.title' => 'required_with:related_links|string|max:255',
            'related_links.*.url' => 'required_with:related_links|string|url',
            'related_links.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if documentation content already exists for this activity
        $existingContent = DocumentationContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Documentation content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        // Prepare data for storage
        $data = $request->except(['tags', 'attachments', 'related_links']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('tags')) {
            $data['tags'] = json_encode($request->tags);
        }
        
        if ($request->has('attachments')) {
            $data['attachments'] = json_encode($request->attachments);
        }
        
        if ($request->has('related_links')) {
            $data['related_links'] = json_encode($request->related_links);
        }
        
        // Add activity_id to data
        $data['activity_id'] = $activityId;

        $documentationContent = DocumentationContent::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Documentation content created successfully',
            'data' => $documentationContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified documentation content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/documentation-content",
     *     summary="Get documentation content for an activity",
     *     description="Retrieves documentation content for a documentation-type activity",
     *     operationId="getDocumentationContent",
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
     *         description="Documentation content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/DocumentationContent")
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

        $documentationContent = DocumentationContent::where('activity_id', $activityId)->firstOrFail();
        
        // Decode JSON fields for the response
        if ($documentationContent->tags) {
            $documentationContent->tags = json_decode($documentationContent->tags);
        }
        
        if ($documentationContent->attachments) {
            $documentationContent->attachments = json_decode($documentationContent->attachments);
        }
        
        if ($documentationContent->related_links) {
            $documentationContent->related_links = json_decode($documentationContent->related_links);
        }

        return response()->json([
            'status' => 'success',
            'data' => $documentationContent,
        ]);
    }

    /**
     * Update the specified documentation content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/documentation-content",
     *     summary="Update documentation content for an activity",
     *     description="Updates documentation content for a documentation-type activity",
     *     operationId="updateDocumentationContent",
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
     *         description="Documentation content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="API Documentation"),
     *             @OA\Property(property="content", type="string", example="This documentation covers the API endpoints and usage..."),
     *             @OA\Property(property="version", type="string", example="1.0"),
     *             @OA\Property(property="format", type="string", enum={"markdown", "html", "pdf"}, example="markdown"),
     *             @OA\Property(
     *                 property="attachments",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="API Schema"),
     *                     @OA\Property(property="file_path", type="string", example="path/to/schema.json"),
     *                     @OA\Property(property="file_type", type="string", example="application/json"),
     *                     @OA\Property(property="description", type="string", example="JSON schema for the API", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documentation content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Documentation content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DocumentationContent")
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

        $documentationContent = DocumentationContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'content' => 'string',
            'format' => 'string|in:plain,markdown,html',
            'version' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string|max:255',
            'attachments.*.file_url' => 'required_with:attachments|string|url',
            'attachments.*.file_type' => 'required_with:attachments|string|max:50',
            'attachments.*.file_size' => 'nullable|integer',
            'related_links' => 'nullable|array',
            'related_links.*.title' => 'required_with:related_links|string|max:255',
            'related_links.*.url' => 'required_with:related_links|string|url',
            'related_links.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['tags', 'attachments', 'related_links']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('tags')) {
            $updateData['tags'] = json_encode($request->tags);
        }
        
        if ($request->has('attachments')) {
            $updateData['attachments'] = json_encode($request->attachments);
        }
        
        if ($request->has('related_links')) {
            $updateData['related_links'] = json_encode($request->related_links);
        }

        $documentationContent->update($updateData);

        // Decode JSON fields for the response
        if ($documentationContent->tags) {
            $documentationContent->tags = json_decode($documentationContent->tags);
        }
        
        if ($documentationContent->attachments) {
            $documentationContent->attachments = json_decode($documentationContent->attachments);
        }
        
        if ($documentationContent->related_links) {
            $documentationContent->related_links = json_decode($documentationContent->related_links);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Documentation content updated successfully',
            'data' => $documentationContent,
        ]);
    }

    /**
     * Remove the specified documentation content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/documentation-content",
     *     summary="Delete documentation content for an activity",
     *     description="Deletes documentation content for a documentation-type activity",
     *     operationId="deleteDocumentationContent",
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
     *         description="Documentation content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Documentation content deleted successfully")
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

        $documentationContent = DocumentationContent::where('activity_id', $activityId)->firstOrFail();
        $documentationContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Documentation content deleted successfully',
        ]);
    }
}
