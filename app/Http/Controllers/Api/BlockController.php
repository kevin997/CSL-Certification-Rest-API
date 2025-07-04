<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Block",
 *     required={"template_id", "title", "type", "order"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Introduction Video"),
 *     @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *     @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Get(
 *     path="/api/templates/{templateId}/blocks",
 *     summary="Get all blocks for a template",
 *     description="Returns a list of all blocks for a specific template",
 *     operationId="getTemplateBlocks",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
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
 *                     @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="title", type="string", example="Introduction Video"),
 *                     @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *                     @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                     @OA\Property(property="order", type="integer", example=1),
 *                     @OA\Property(property="settings", type="object", nullable=true),
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
 *         response=404,
 *         description="Template not found"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/templates/{templateId}/blocks",
 *     summary="Create a new block",
 *     description="Creates a new block for a specific template",
 *     operationId="createBlock",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"title", "type"},
 *             @OA\Property(property="title", type="string", example="Introduction Video"),
 *             @OA\Property(property="description", type="string", example="A video introduction to the course"),
 *             @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *             @OA\Property(property="order", type="integer", example=1),
 *             @OA\Property(
 *                 property="settings",
 *                 type="object",
 *                 @OA\Property(property="duration", type="integer", example=300),
 *                 @OA\Property(property="autoplay", type="boolean", example=false)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Block created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Block created successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="title", type="string", example="Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="settings", type="object", nullable=true),
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
 *         description="Template not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/templates/{templateId}/blocks/{id}",
 *     summary="Get a specific block",
 *     description="Returns details of a specific block in a template",
 *     operationId="getBlock",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
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
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="title", type="string", example="Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="settings", type="object", nullable=true),
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
 *         response=404,
 *         description="Template or block not found"
 *     )
 * )
 *
 * @OA\Put(
 *     path="/api/templates/{templateId}/blocks/{id}",
 *     summary="Update a block",
 *     description="Updates an existing block in a template",
 *     operationId="updateBlock",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Block ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string", example="Updated Video Introduction"),
 *             @OA\Property(property="description", type="string", example="Updated description for the video introduction"),
 *             @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *             @OA\Property(property="order", type="integer", example=2),
 *             @OA\Property(
 *                 property="settings",
 *                 type="object",
 *                 @OA\Property(property="duration", type="integer", example=450),
 *                 @OA\Property(property="autoplay", type="boolean", example=true)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Block updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Block updated successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="title", type="string", example="Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="settings", type="object", nullable=true),
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
 *         description="Template or block not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 *
 * @OA\Delete(
 *     path="/api/templates/{templateId}/blocks/{id}",
 *     summary="Delete a block",
 *     description="Deletes an existing block from a template",
 *     operationId="deleteBlock",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Block ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Block deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Block deleted successfully")
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
 *         description="Template or block not found"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/templates/{templateId}/blocks/reorder",
 *     summary="Reorder blocks",
 *     description="Updates the order of blocks in a template",
 *     operationId="reorderBlocks",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"blocks"},
 *             @OA\Property(
 *                 property="blocks",
 *                 type="array",
 *                 description="Array of block IDs in the desired order",
 *                 @OA\Items(
 *                     type="integer",
 *                     example=1
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Blocks reordered successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Blocks reordered successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="title", type="string", example="Introduction Video"),
 *                     @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *                     @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                     @OA\Property(property="order", type="integer", example=1),
 *                     @OA\Property(property="settings", type="object", nullable=true),
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
 *         description="Template not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/templates/{templateId}/blocks/{id}/duplicate",
 *     summary="Duplicate a block",
 *     description="Creates a copy of an existing block in a template",
 *     operationId="duplicateBlock",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Block ID to duplicate",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Block duplicated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Block duplicated successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="title", type="string", example="Introduction Video"),
 *                 @OA\Property(property="description", type="string", example="A video introduction to the course", nullable=true),
 *                 @OA\Property(property="type", type="string", enum={"text", "video", "quiz", "assignment", "lesson", "documentation", "event", "certificate", "feedback"}, example="video"),
 *                 @OA\Property(property="order", type="integer", example=1),
 *                 @OA\Property(property="settings", type="object", nullable=true),
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
 *         description="Template or block not found"
 *     )
 * )
 *

 *     description="Creates multiple blocks and their activities for a specific template in one request.",
 *     operationId="batchCreateBlocks",
 *     tags={"Blocks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="templateId",
 *         in="path",
 *         description="Template ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 required={"title"},
 *                 @OA\Property(property="title", type="string"),
 *                 @OA\Property(property="description", type="string"),
 *                 @OA\Property(property="order", type="integer"),
 *                 @OA\Property(property="is_required", type="boolean"),
 *                 @OA\Property(property="activities", type="array",
 *                     @OA\Items(
 *                         required={"title", "type"},
 *                         @OA\Property(property="title", type="string"),
 *                         @OA\Property(property="type", type="string"),
 *                         @OA\Property(property="description", type="string"),
 *                         @OA\Property(property="order", type="integer"),
 *                         @OA\Property(property="is_required", type="boolean")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Blocks and activities created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Blocks and activities created successfully"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthenticated"),
 *     @OA\Response(response=403, description="Forbidden"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */

class BlockController extends Controller
{
    /**
     * Display a listing of blocks for a specific template.
     *
     * @param  int  $templateId
     * @return \Illuminate\Http\Response
     */
    public function index($templateId)
    {
        $template = Template::findOrFail($templateId);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view blocks for this template',
            ], Response::HTTP_FORBIDDEN);
        }

        $blocks = Block::where('template_id', $templateId)
            ->orderBy('order')
            ->with('activities')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $blocks,
        ]);
    }

    /**
     * Store a newly created block in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $templateId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $templateId)
    {
        $template = Template::findOrFail($templateId);

        // Check if user has permission to add blocks to this template
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add blocks to this template',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // If order is not provided, place the block at the end
        if (!$request->has('order')) {
            $maxOrder = Block::where('template_id', $templateId)->max('order') ?? 0;
            $request->merge(['order' => $maxOrder + 1]);
        }

        $block = Block::create([
            'template_id' => $templateId,
            'title' => $request->title,
            'description' => $request->description,
            'is_required' => $request->is_required ?? false,
            'order' => $request->order,
            'created_by' => Auth::id(),
            'status' => 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Block created successfully',
            'data' => $block,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified block.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $block = Block::with(['activities' => function ($query) {
            $query->orderBy('order');
        }])->findOrFail($id);

        $template = Template::findOrFail($block->template_id);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this block',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $block,
        ]);
    }

    /**
     * Update the specified block in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $block = Block::findOrFail($id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to update this block
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this block',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check if template is published and enforce restrictions
        // if ($template->status === 'published') {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Cannot modify blocks in a published template',
        //     ], Response::HTTP_FORBIDDEN);
        // }

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $block->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Block updated successfully',
            'data' => $block,
        ]);
    }

    /**
     * Remove the specified block from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $block = Block::findOrFail($id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to delete this block
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this block',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check if template is published and enforce restrictions
        // if ($template->status === 'published') {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Cannot delete blocks in a published template',
        //     ], Response::HTTP_FORBIDDEN);
        // }

        // Delete the block
        $block->delete();

        // Reorder remaining blocks
        $remainingBlocks = Block::where('template_id', $template->id)
            ->where('order', '>', $block->order)
            ->orderBy('order')
            ->get();

        foreach ($remainingBlocks as $index => $remainingBlock) {
            $remainingBlock->update(['order' => $block->order + $index]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Block deleted successfully',
        ]);
    }

    /**
     * Reorder blocks within a template.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $templateId
     * @return \Illuminate\Http\Response
     */
    public function reorder(Request $request, $templateId)
    {
        $template = Template::findOrFail($templateId);

        // Check if user has permission to reorder blocks in this template
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to reorder blocks in this template',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check if template is published and enforce restrictions
        // if ($template->status === 'published') {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Cannot reorder blocks in a published template',
        //     ], Response::HTTP_FORBIDDEN);
        // }

        $validator = Validator::make($request->all(), [
            'blocks' => 'required|array',
            'blocks.*.id' => 'required|exists:blocks,id',
            'blocks.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update the order of each block
        foreach ($request->blocks as $blockData) {
            $block = Block::findOrFail($blockData['id']);
            
            // Ensure the block belongs to the specified template
            if ($block->template_id != $templateId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more blocks do not belong to this template',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $block->update(['order' => $blockData['order']]);
        }

        $updatedBlocks = Block::where('template_id', $templateId)
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Blocks reordered successfully',
            'data' => $updatedBlocks,
        ]);
    }

    /**
     * Batch create blocks with activities for a template.
     *
     * @OA\Post(
     *     path="/api/templates/{templateId}/blocks/batch",
     *     summary="Batch create blocks with activities",
     *     description="Creates multiple blocks and their activities for a specific template in one request.",
     *     operationId="batchCreateBlocks",
     *     tags={"Blocks"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="templateId",
     *         in="path",
     *         description="Template ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 required={"title"},
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="order", type="integer"),
     *                 @OA\Property(property="is_required", type="boolean"),
     *                 @OA\Property(property="activities", type="array",
     *                     @OA\Items(
     *                         required={"title", "type"},
     *                         @OA\Property(property="title", type="string"),
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="order", type="integer"),
     *                         @OA\Property(property="is_required", type="boolean")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blocks and activities created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Blocks and activities created successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function batchStore(Request $request, $templateId)
    {
        $template = \App\Models\Template::findOrFail($templateId);
        if ($template->created_by !== \Illuminate\Support\Facades\Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add blocks to this template',
            ], 403);
        }
        $data = $request->all();
        if (!is_array($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input format. Expected an array of blocks.'
            ], 422);
        }
        $created = [];
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($data as $blockData) {
                $blockValidator = \Illuminate\Support\Facades\Validator::make($blockData, [
                    'title' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'is_required' => 'boolean',
                    'order' => 'nullable|integer',
                    'activities' => 'array',
                    'activities.*.title' => 'required|string|max:255',
                    'activities.*.type' => 'required|string',
                    'activities.*.description' => 'nullable|string',
                    'activities.*.order' => 'nullable|integer',
                    'activities.*.is_required' => 'boolean',
                ]);
                if ($blockValidator->fails()) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed for block',
                        'errors' => $blockValidator->errors(),
                    ], 422);
                }
                // If order is not provided, place the block at the end
                $order = $blockData['order'] ?? (\App\Models\Block::where('template_id', $templateId)->max('order') + 1);
                $block = \App\Models\Block::create([
                    'template_id' => $templateId,
                    'title' => $blockData['title'],
                    'description' => $blockData['description'] ?? null,
                    'is_required' => $blockData['is_required'] ?? false,
                    'order' => $order,
                    'created_by' => \Illuminate\Support\Facades\Auth::id(),
                    'status' => 'active',
                ]);
                $blockActivities = [];
                if (!empty($blockData['activities']) && is_array($blockData['activities'])) {
                    foreach ($blockData['activities'] as $activityData) {
                        $activity = $block->activities()->create([
                            'title' => $activityData['title'],
                            'type' => $activityData['type'],
                            'description' => $activityData['description'] ?? null,
                            'is_required' => $activityData['is_required'] ?? false,
                            'order' => $activityData['order'] ?? (\App\Models\Activity::where('block_id', $block->id)->max('order') + 1),
                            'created_by' => \Illuminate\Support\Facades\Auth::id(),
                            'status' => 'active',
                        ]);
                        $blockActivities[] = $activity;
                    }
                }
                $block->setRelation('activities', collect($blockActivities));
                $created[] = $block;
            }
            \Illuminate\Support\Facades\DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Blocks and activities created successfully',
                'data' => $created,
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create blocks and activities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
