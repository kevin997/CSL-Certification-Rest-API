<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityService extends Service
{
    /**
     * Get an activity by ID
     *
     * @param int $id
     * @return Activity|null
     */
    public function getActivityById(int $id): ?Activity
    {
        return Activity::find($id);
    }
    
    /**
     * Get all activities for a block
     *
     * @param int $blockId
     * @return Collection
     */
    public function getActivitiesByBlockId(int $blockId): Collection
    {
        return Activity::where('block_id', $blockId)
            ->orderBy('order')
            ->get();
    }
    
    /**
     * Create a new activity
     *
     * @param array $data
     * @return Activity
     */
    public function createActivity(array $data): Activity
    {
        // Set order if not provided
        if (!isset($data['order'])) {
            $maxOrder = Activity::where('block_id', $data['block_id'])->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }
        
        return Activity::create($data);
    }
    
    /**
     * Update an existing activity
     *
     * @param int $id
     * @param array $data
     * @return Activity|null
     */
    public function updateActivity(int $id, array $data): ?Activity
    {
        $activity = Activity::find($id);
        
        if (!$activity) {
            return null;
        }
        
        $activity->update($data);
        
        return $activity->fresh();
    }
    
    /**
     * Delete an activity
     *
     * @param int $id
     * @return bool
     */
    public function deleteActivity(int $id): bool
    {
        $activity = Activity::find($id);
        
        if (!$activity) {
            return false;
        }
        
        return $activity->delete();
    }
    
    /**
     * Move an activity to a different block
     *
     * @param int $activityId
     * @param int $newBlockId
     * @return Activity|null
     */
    public function moveActivityToBlock(int $activityId, int $newBlockId): ?Activity
    {
        $activity = Activity::find($activityId);
        
        if (!$activity) {
            return null;
        }
        
        // Set new block ID and adjust order
        $maxOrder = Activity::where('block_id', $newBlockId)->max('order') ?? 0;
        $activity->update([
            'block_id' => $newBlockId,
            'order' => $maxOrder + 1
        ]);
        
        return $activity->fresh();
    }
    
    /**
     * Duplicate an activity
     *
     * @param int $activityId
     * @param int|null $targetBlockId If null, duplicates to the same block
     * @return Activity|null
     */
    public function duplicateActivity(int $activityId, ?int $targetBlockId = null): ?Activity
    {
        $activity = Activity::find($activityId);
        
        if (!$activity) {
            return null;
        }
        
        // Create new activity with original data
        $newActivityData = $activity->toArray();
        
        // Remove ID and timestamps
        unset($newActivityData['id']);
        unset($newActivityData['created_at']);
        unset($newActivityData['updated_at']);
        
        // Set target block ID if provided
        if ($targetBlockId) {
            $newActivityData['block_id'] = $targetBlockId;
            
            // Adjust order for the target block
            $maxOrder = Activity::where('block_id', $targetBlockId)->max('order') ?? 0;
            $newActivityData['order'] = $maxOrder + 1;
        } else {
            // Adjust order for the same block
            $maxOrder = Activity::where('block_id', $activity->block_id)->max('order') ?? 0;
            $newActivityData['order'] = $maxOrder + 1;
        }
        
        // Create new activity
        return Activity::create($newActivityData);
    }
    
    /**
     * Get activities by type
     *
     * @param string $type
     * @return Collection
     */
    public function getActivitiesByType(string $type): Collection
    {
        return Activity::where('type', $type)->get();
    }
    
    /**
     * Get content associated with an activity
     *
     * @param int $activityId
     * @return mixed|null
     */
    public function getActivityContent(int $activityId)
    {
        $activity = Activity::find($activityId);
        
        if (!$activity) {
            return null;
        }
        
        // Determine content type and fetch the associated content
        switch ($activity->type) {
            case 'text':
                return DB::table('text_contents')->where('activity_id', $activityId)->first();
            case 'video':
                return DB::table('video_contents')->where('activity_id', $activityId)->first();
            case 'quiz':
                return DB::table('quiz_contents')->where('activity_id', $activityId)->first();
            case 'lesson':
                return DB::table('lesson_contents')->where('activity_id', $activityId)->first();
            case 'assignment':
                return DB::table('assignment_contents')->where('activity_id', $activityId)->first();
            case 'documentation':
                return DB::table('documentation_contents')->where('activity_id', $activityId)->first();
            case 'event':
                return DB::table('event_contents')->where('activity_id', $activityId)->first();
            case 'certificate':
                return DB::table('certificate_contents')->where('activity_id', $activityId)->first();
            case 'feedback':
                return DB::table('feedback_contents')->where('activity_id', $activityId)->first();
            default:
                return null;
        }
    }
}
