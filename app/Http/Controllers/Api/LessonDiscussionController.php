<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonContent;
use App\Models\LessonDiscussion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LessonDiscussionController extends Controller
{
    /**
     * Display a listing of discussions for a lesson content.
     */
    public function index(int $lessonId)
    {
        $lesson = LessonContent::withTrashed()->findOrFail($lessonId);

        $discussions = LessonDiscussion::where('lesson_content_id', $lesson->id)
            ->whereNull('parent_id')
            ->with(['replies.user', 'user'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($discussions);
    }

    /**
     * Store a newly created discussion.
     */
    public function store(Request $request, int $lessonId)
    {
        $lesson = LessonContent::withTrashed()->findOrFail($lessonId);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'question_id' => 'nullable|exists:lesson_questions,id',
            'content_part_id' => 'nullable|exists:lesson_content_parts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $discussion = LessonDiscussion::create([
            'lesson_content_id' => $lesson->id,
            'question_id' => $request->input('question_id'),
            'content_part_id' => $request->input('content_part_id'),
            'user_id' => Auth::id(),
            'message' => $request->input('message'),
            'is_instructor_feedback' => false,
        ]);

        return response()->json($discussion->load('user'), Response::HTTP_CREATED);
    }

    /**
     * Reply to a discussion.
     */
    public function reply(Request $request, int $lessonId, int $discussionId)
    {
        $parent = LessonDiscussion::findOrFail($discussionId);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reply = LessonDiscussion::create([
            'lesson_content_id' => $parent->lesson_content_id,
            'parent_id' => $parent->id,
            'user_id' => Auth::id(),
            'message' => $request->input('message'),
            'is_instructor_feedback' => false,
        ]);

        return response()->json($reply->load('user'), Response::HTTP_CREATED);
    }

    /**
     * Remove the specified discussion (soft delete).
     */
    public function destroy(int $lessonId, int $discussionId)
    {
        $discussion = LessonDiscussion::where('lesson_content_id', $lessonId)->findOrFail($discussionId);
       
        $discussion->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
