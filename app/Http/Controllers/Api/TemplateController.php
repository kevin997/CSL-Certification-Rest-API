<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Template",
 *     required={"title", "description", "created_by"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="CSL Certification Course Template"),
 *     @OA\Property(property="description", type="string", example="A template for creating certification courses"),
 *     @OA\Property(property="is_public", type="boolean", example=true),
 *     @OA\Property(property="thumbnail_path", type="string", example="path/to/thumbnail", nullable=true),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="created_by", type="integer", format="int64", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="blocks",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", format="int64", example=1),
 *             @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *             @OA\Property(property="title", type="string", example="Introduction Video"),
 *             @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *             @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *             @OA\Property(property="order", type="integer", example=1),
 *             @OA\Property(property="settings", type="object", nullable=true),
 *             @OA\Property(property="created_at", type="string", format="date-time"),
 *             @OA\Property(property="updated_at", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Property(
 *         property="creator",
 *         ref="#/components/schemas/User"
 *     )
 * )
 */

class TemplateController extends Controller
{
    /**
     * Display a listing of the templates.
     *
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/templates",
     *     summary="Get list of templates",
     *     description="Returns paginated list of templates that are either created by the authenticated user or are public",
     *     operationId="getTemplatesList",
     *     tags={"Templates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Template")
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=10),
     *                 @OA\Property(property="per_page", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index()
    {
        $templates = Template::where('created_by', Auth::id())
            ->orWhere('is_public', true)
            ->with('blocks')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }

    /**
     * Store a newly created template in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/templates",
     *     summary="Create a new template",
     *     description="Creates a new template",
     *     operationId="createTemplate",
     *     tags={"Templates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", example="CSL Certification Course Template"),
     *             @OA\Property(property="description", type="string", example="A template for creating certification courses"),
     *             @OA\Property(property="is_public", type="boolean", example=true),
     *             @OA\Property(property="status", type="string", example="draft"),
     *             @OA\Property(property="thumbnail_path", type="string", example="path/to/thumbnail"),
     *             @OA\Property(property="settings", type="object", example={"widgets": {"calendar": true, "progress": false}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Template created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Template"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'status' => 'required|string|in:draft,published,archived',
            'thumbnail_path' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $template = Template::create([
            'name' => $request->name,
            'title' => $request->title,
            'description' => $request->description,
            'is_public' => $request->is_public ?? false,
            'status' => $request->status,
            'thumbnail_path' => $request->thumbnail_path,
            'settings' => $request->settings,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Template created successfully',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified template.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/templates/{id}",
     *     summary="Get a template by ID",
     *     description="Returns a template by ID",
     *     operationId="getTemplateById",
     *     tags={"Templates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Template"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function show($id)
    {
        $template = Template::with(['blocks.activities'])
            ->findOrFail($id);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this template',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $template,
        ]);
    }

    /**
     * Update the specified template in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/templates/{id}",
     *     summary="Update a template",
     *     description="Updates a template",
     *     operationId="updateTemplate",
     *     tags={"Templates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", example="CSL Certification Course Template"),
     *             @OA\Property(property="description", type="string", example="A template for creating certification courses"),
     *             @OA\Property(property="is_public", type="boolean", example=true),
     *             @OA\Property(property="status", type="string", example="draft"),
     *             @OA\Property(property="thumbnail_path", type="string", example="path/to/thumbnail"),
     *             @OA\Property(property="settings", type="object", example={"widgets": {"calendar": true, "progress": false}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Template"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        // Check if user has permission to update this template
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this template',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'status' => 'string|in:draft,published,archived',
            'thumbnail_path' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $template->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Template updated successfully',
            'data' => $template,
        ]);
    }

    /**
     * Remove the specified template from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/templates/{id}",
     *     summary="Delete a template",
     *     description="Deletes a template",
     *     operationId="deleteTemplate",
     *     tags={"Templates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function destroy($id)
    {
        $template = Template::findOrFail($id);

        // Check if user has permission to delete this template
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this template',
            ], Response::HTTP_FORBIDDEN);
        }

        $template->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Duplicate the specified template.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/templates/{id}/duplicate",
     *     summary="Duplicate a template",
     *     description="Duplicates a template",
     *     operationId="duplicateTemplate",
     *     tags={"Templates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Template duplicated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template duplicated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Template"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function duplicate($id)
    {
        $template = Template::with(['blocks.activities'])->findOrFail($id);

        // Check if user has permission to view this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to duplicate this template',
            ], Response::HTTP_FORBIDDEN);
        }

        // Create a new template
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->created_by = Auth::id();
        $newTemplate->save();

        // Duplicate blocks
        foreach ($template->blocks as $block) {
            $newBlock = $block->replicate();
            $newBlock->template_id = $newTemplate->id;
            $newBlock->save();

            // Duplicate activities
            foreach ($block->activities as $activity) {
                $newActivity = $activity->replicate();
                $newActivity->block_id = $newBlock->id;
                $newActivity->save();

                // Note: Content duplication would need to be handled separately
                // depending on the activity type
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Template duplicated successfully',
            'data' => $newTemplate->load('blocks.activities'),
        ]);
    }
}
