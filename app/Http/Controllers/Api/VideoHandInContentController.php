<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\Template;
use App\Models\VideoHandInContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VideoHandInContentController extends Controller
{
    public function show($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $content = VideoHandInContent::where('activity_id', $activityId)->firstOrFail();
        return response()->json(['status' => 'success', 'data' => $content]);
    }

    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if ($activity->type->value !== 'video_hand_in') {
            return response()->json(['status' => 'error', 'message' => 'Activity is not of type video_hand_in'], Response::HTTP_BAD_REQUEST);
        }

        if (VideoHandInContent::where('activity_id', $activityId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Content already exists'], Response::HTTP_CONFLICT);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'required|string',
            'instructions_format' => 'required|string|in:plain,markdown,html,wysiwyg',
            'max_duration' => 'nullable|integer|min:1',
            'allowed_formats' => 'nullable|array',
            'allowed_formats.*' => 'string',
            'max_file_size' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date',
            'allow_late_submissions' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->except(['allowed_formats']);
        if ($request->has('allowed_formats')) {
            $data['allowed_formats'] = json_encode($request->allowed_formats);
        }
        $data['activity_id'] = $activityId;

        $content = VideoHandInContent::create($data);
        return response()->json(['status' => 'success', 'message' => 'Video hand-in content created', 'data' => $content], Response::HTTP_CREATED);
    }

    public function update(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $content = VideoHandInContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'sometimes|required|string',
            'instructions_format' => 'sometimes|required|string|in:plain,markdown,html,wysiwyg',
            'max_duration' => 'nullable|integer|min:1',
            'allowed_formats' => 'nullable|array',
            'allowed_formats.*' => 'string',
            'max_file_size' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date',
            'allow_late_submissions' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->except(['allowed_formats']);
        if ($request->has('allowed_formats')) {
            $data['allowed_formats'] = json_encode($request->allowed_formats);
        }

        $content->update($data);
        return response()->json(['status' => 'success', 'message' => 'Video hand-in content updated', 'data' => $content->fresh()]);
    }

    public function destroy($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $content = VideoHandInContent::where('activity_id', $activityId)->firstOrFail();
        $content->delete();
        return response()->json(['status' => 'success', 'message' => 'Deleted']);
    }
}
