<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseDraftJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * AI course builder: an instructor describes a course and a queued
 * GenerateCourseDraftJob (via CourseBuilderAgent) generates the full draft
 * template structure (template -> blocks -> activities). The frontend polls
 * result() for completion and is responsible for creating the actual
 * template/blocks/activities from the returned draft via the existing
 * endpoints.
 */
class CourseBuilderController extends Controller
{
    /**
     * Kick off draft generation for a course description and return a job id
     * to poll.
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|min:10|max:2000',
            'language' => 'nullable|string|in:fr,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $user = $request->user();
        $jobId = (string) Str::uuid();

        // Seed the cache before dispatching so polling never 404s while the
        // job is still queued.
        Cache::put("course_builder:{$jobId}", [
            'user_id' => $user->id,
            'status' => 'pending',
        ], now()->addMinutes(45));

        GenerateCourseDraftJob::dispatch(
            $jobId,
            $user->id,
            $data['description'],
            $data['language'] ?? 'fr',
        );

        return response()->json([
            'success' => true,
            'data' => ['job_id' => $jobId],
        ], 202);
    }

    /**
     * Poll the status/result of a previously started draft generation job.
     */
    public function result(Request $request, string $jobId)
    {
        $cached = Cache::get("course_builder:{$jobId}");

        if (! $cached || ($cached['user_id'] ?? null) !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.',
            ], 404);
        }

        $data = ['status' => $cached['status']];

        if ($cached['status'] === 'done') {
            $data['draft'] = $cached['draft'] ?? null;
        } elseif ($cached['status'] === 'failed') {
            $data['message'] = $cached['message'] ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
