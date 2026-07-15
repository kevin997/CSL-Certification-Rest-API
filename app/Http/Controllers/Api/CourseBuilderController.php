<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseDraftJob;
use App\Models\Template;
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
 *
 * When `template_id` is given, the job instead acts on that EXISTING
 * template (via TemplateEnhancerAgent) and proposes additions only — see
 * GenerateCourseDraftJob::handleEnhance().
 */
class CourseBuilderController extends Controller
{
    /**
     * Kick off draft generation for a course description and return a job id
     * to poll. Optionally targets an existing template (`template_id`) to
     * generate additions for instead of a brand new draft.
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|min:10|max:2000',
            'language' => 'nullable|string|in:fr,en',
            'template_id' => 'nullable|integer|exists:templates,id',
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
        $templateId = $data['template_id'] ?? null;

        if ($templateId !== null) {
            // Same ownership rule as TemplateController's edit access: the
            // template must belong to the requester.
            $template = Template::findOrFail($templateId);

            if ($template->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to use this template',
                ], 403);
            }
        }

        $jobId = (string) Str::uuid();
        $kind = $templateId !== null ? 'enhance' : 'new';

        // Seed the cache before dispatching so polling never 404s while the
        // job is still queued.
        Cache::put("course_builder:{$jobId}", [
            'user_id' => $user->id,
            'status' => 'pending',
            'kind' => $kind,
        ], now()->addMinutes(45));

        GenerateCourseDraftJob::dispatch(
            $jobId,
            $user->id,
            $data['description'],
            $data['language'] ?? 'fr',
            $templateId,
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

        $kind = $cached['kind'] ?? 'new';
        $data = ['status' => $cached['status'], 'kind' => $kind];

        if ($cached['status'] === 'done') {
            if ($kind === 'enhance') {
                $data['template_id'] = $cached['template_id'] ?? null;
                $data['additions'] = $cached['additions'] ?? null;
            } else {
                $data['draft'] = $cached['draft'] ?? null;
            }
        } elseif ($cached['status'] === 'failed') {
            $data['message'] = $cached['message'] ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
