<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\VideoContent;
use App\Services\Templates\TemplateAccess;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderVideoCatalogueController extends Controller
{
    public function __invoke(Request $request, int $activityId, TemplateAccess $access): JsonResponse
    {
        return DB::transaction(function () use ($request, $activityId, $access) {
            $activity = Activity::query()->with('block.template')->lockForUpdate()->findOrFail($activityId);
            $template = $activity->block?->template;
            abort_unless($template && $access->canWrite($template, $request->user()), 403);

            // A creator serving more than one academy must still be in the
            // academy for the template being edited. Legacy unscoped
            // templates retain the creator-only authorization.
            $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
            if ($template->environment_id !== null) {
                abort_unless(
                    $context instanceof EnvironmentContext
                        && $context->environment
                        && (int) $context->environment->id === (int) $template->environment_id,
                    403
                );
            }
            abort_unless($activity->type === ActivityType::VIDEO, 422, 'This is not a video activity.');

            $validated = $request->validate([
                'video_ids' => 'required|array|list|min:1',
                'video_ids.*' => 'required|integer|distinct|min:1',
                'previous_video_ids' => 'required|array|list|min:1',
                'previous_video_ids.*' => 'required|integer|distinct|min:1',
            ]);
            $order = array_map('intval', $validated['video_ids']);
            $previousOrder = array_map('intval', $validated['previous_video_ids']);
            $videos = VideoContent::query()->where('activity_id', $activity->id)
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $currentOrder = array_map('intval', $videos->modelKeys());

            $sortedOrder = $order;
            $sortedCurrent = $currentOrder;
            sort($sortedOrder, SORT_NUMERIC);
            sort($sortedCurrent, SORT_NUMERIC);
            if ($previousOrder !== $currentOrder || $sortedOrder !== $sortedCurrent) {
                return response()->json([
                    'message' => 'The video catalogue changed. Reload it before reordering.',
                ], 409);
            }

            $byId = $videos->keyBy('id');
            foreach ($order as $position => $videoId) {
                $video = $byId->get($videoId);
                if ($video->sort_order !== $position) {
                    $video->update(['sort_order' => $position]);
                }
            }

            return response()->json(['video_ids' => $order]);
        });
    }
}
