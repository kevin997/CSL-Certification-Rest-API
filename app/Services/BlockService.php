<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BlockService extends Service
{
    /**
     * Get a block by ID with its activities
     *
     * @param int $id
     * @return Block|null
     */
    public function getBlockById(int $id): ?Block
    {
        return Block::with(['activities'])->find($id);
    }
    
    /**
     * Get all blocks for a template
     *
     * @param int $templateId
     * @return Collection
     */
    public function getBlocksByTemplateId(int $templateId): Collection
    {
        return Block::with(['activities'])
            ->where('template_id', $templateId)
            ->orderBy('order')
            ->get();
    }
    
    /**
     * Create a new block
     *
     * @param array $data
     * @return Block
     */
    public function createBlock(array $data): Block
    {
        // Set order if not provided
        if (!isset($data['order'])) {
            $maxOrder = Block::where('template_id', $data['template_id'])->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }
        
        return DB::transaction(function () use ($data) {
            $block = Block::create($data);
            
            // Create activities if provided
            if (isset($data['activities']) && is_array($data['activities'])) {
                foreach ($data['activities'] as $activityData) {
                    $this->addActivityToBlock($block->id, $activityData);
                }
            }
            
            return $block->fresh(['activities']);
        });
    }
    
    /**
     * Update an existing block
     *
     * @param int $id
     * @param array $data
     * @return Block|null
     */
    public function updateBlock(int $id, array $data): ?Block
    {
        $block = Block::find($id);
        
        if (!$block) {
            return null;
        }
        
        $block->update($data);
        
        return $block->fresh(['activities']);
    }
    
    /**
     * Delete a block
     *
     * @param int $id
     * @return bool
     */
    public function deleteBlock(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $block = Block::find($id);
            
            if (!$block) {
                return false;
            }
            
            // Delete all activities in this block
            Activity::where('block_id', $id)->delete();
            
            // Delete the block
            return $block->delete();
        });
    }
    
    /**
     * Add an activity to a block
     *
     * @param int $blockId
     * @param array $activityData
     * @return Activity
     */
    public function addActivityToBlock(int $blockId, array $activityData): Activity
    {
        // Set block ID
        $activityData['block_id'] = $blockId;
        
        // Set order if not provided
        if (!isset($activityData['order'])) {
            $maxOrder = Activity::where('block_id', $blockId)->max('order') ?? 0;
            $activityData['order'] = $maxOrder + 1;
        }
        
        return Activity::create($activityData);
    }
    
    /**
     * Reorder activities within a block
     *
     * @param int $blockId
     * @param array $activityOrders Array of activity IDs in the desired order
     * @return bool
     */
    public function reorderActivities(int $blockId, array $activityOrders): bool
    {
        $block = Block::find($blockId);
        
        if (!$block) {
            return false;
        }
        
        return DB::transaction(function () use ($blockId, $activityOrders) {
            foreach ($activityOrders as $index => $activityId) {
                Activity::where('id', $activityId)
                    ->where('block_id', $blockId)
                    ->update(['order' => $index + 1]);
            }
            
            return true;
        });
    }
    
    /**
     * Move a block to a different template
     *
     * @param int $blockId
     * @param int $newTemplateId
     * @return Block|null
     */
    public function moveBlockToTemplate(int $blockId, int $newTemplateId): ?Block
    {
        $block = Block::find($blockId);
        
        if (!$block) {
            return null;
        }
        
        // Set new template ID and adjust order
        $maxOrder = Block::where('template_id', $newTemplateId)->max('order') ?? 0;
        $block->update([
            'template_id' => $newTemplateId,
            'order' => $maxOrder + 1
        ]);
        
        return $block->fresh(['activities']);
    }
    
    /**
     * Duplicate a block
     *
     * @param int $blockId
     * @param int|null $targetTemplateId If null, duplicates to the same template
     * @return Block|null
     */
    public function duplicateBlock(int $blockId, ?int $targetTemplateId = null): ?Block
    {
        $block = Block::with(['activities'])->find($blockId);
        
        if (!$block) {
            return null;
        }
        
        return DB::transaction(function () use ($block, $targetTemplateId) {
            // Create new block with original data
            $newBlockData = $block->toArray();
            
            // Remove ID and timestamps
            unset($newBlockData['id']);
            unset($newBlockData['created_at']);
            unset($newBlockData['updated_at']);
            
            // Set target template ID if provided
            if ($targetTemplateId) {
                $newBlockData['template_id'] = $targetTemplateId;
                
                // Adjust order for the target template
                $maxOrder = Block::where('template_id', $targetTemplateId)->max('order') ?? 0;
                $newBlockData['order'] = $maxOrder + 1;
            } else {
                // Adjust order for the same template
                $maxOrder = Block::where('template_id', $block->template_id)->max('order') ?? 0;
                $newBlockData['order'] = $maxOrder + 1;
            }
            
            // Create new block
            $newBlock = Block::create($newBlockData);
            
            // Duplicate activities
            if (isset($block->activities)) {
                foreach ($block->activities as $activity) {
                    $newActivityData = $activity->toArray();
                    unset($newActivityData['id']);
                    unset($newActivityData['block_id']);
                    unset($newActivityData['created_at']);
                    unset($newActivityData['updated_at']);
                    
                    $this->addActivityToBlock($newBlock->id, $newActivityData);
                }
            }
            
            return $newBlock->fresh(['activities']);
        });
    }
}
