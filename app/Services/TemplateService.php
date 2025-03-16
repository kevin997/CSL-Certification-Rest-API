<?php

namespace App\Services;

use App\Models\Template;
use App\Models\Block;
use App\Models\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateService extends Service
{
    /**
     * Get all templates with optional filtering
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllTemplates(array $filters = []): Collection
    {
        $query = Template::query();
        
        // Apply filters
        if (isset($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }
        
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        
        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->get();
    }
    
    /**
     * Get a template by ID with its blocks and activities
     *
     * @param int $id
     * @return Template|null
     */
    public function getTemplateById(int $id): ?Template
    {
        return Template::with(['blocks.activities'])->find($id);
    }
    
    /**
     * Create a new template
     *
     * @param array $data
     * @return Template
     */
    public function createTemplate(array $data): Template
    {
        // Generate slug if not provided
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        
        return DB::transaction(function () use ($data) {
            $template = Template::create($data);
            
            // Create blocks if provided
            if (isset($data['blocks']) && is_array($data['blocks'])) {
                foreach ($data['blocks'] as $blockData) {
                    $this->addBlockToTemplate($template->id, $blockData);
                }
            }
            
            return $template->fresh(['blocks.activities']);
        });
    }
    
    /**
     * Update an existing template
     *
     * @param int $id
     * @param array $data
     * @return Template|null
     */
    public function updateTemplate(int $id, array $data): ?Template
    {
        $template = Template::find($id);
        
        if (!$template) {
            return null;
        }
        
        // Update slug if title changed
        if (isset($data['title']) && $template->title !== $data['title'] && (!isset($data['slug']) || empty($data['slug']))) {
            $data['slug'] = Str::slug($data['title']);
        }
        
        $template->update($data);
        
        return $template->fresh(['blocks.activities']);
    }
    
    /**
     * Delete a template
     *
     * @param int $id
     * @return bool
     */
    public function deleteTemplate(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $template = Template::find($id);
            
            if (!$template) {
                return false;
            }
            
            // Delete all blocks and activities
            foreach ($template->blocks as $block) {
                Activity::where('block_id', $block->id)->delete();
            }
            
            Block::where('template_id', $id)->delete();
            
            return $template->delete();
        });
    }
    
    /**
     * Add a block to a template
     *
     * @param int $templateId
     * @param array $blockData
     * @return Block
     */
    public function addBlockToTemplate(int $templateId, array $blockData): Block
    {
        // Set template ID
        $blockData['template_id'] = $templateId;
        
        // Set order if not provided
        if (!isset($blockData['order'])) {
            $maxOrder = Block::where('template_id', $templateId)->max('order') ?? 0;
            $blockData['order'] = $maxOrder + 1;
        }
        
        $block = Block::create($blockData);
        
        // Create activities if provided
        if (isset($blockData['activities']) && is_array($blockData['activities'])) {
            foreach ($blockData['activities'] as $activityData) {
                $this->addActivityToBlock($block->id, $activityData);
            }
        }
        
        return $block->fresh(['activities']);
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
     * Duplicate a template
     *
     * @param int $id
     * @param array $overrideData
     * @return Template|null
     */
    public function duplicateTemplate(int $id, array $overrideData = []): ?Template
    {
        $template = $this->getTemplateById($id);
        
        if (!$template) {
            return null;
        }
        
        return DB::transaction(function () use ($template, $overrideData) {
            // Create new template with original data
            $newTemplateData = $template->toArray();
            
            // Remove ID and timestamps
            unset($newTemplateData['id']);
            unset($newTemplateData['created_at']);
            unset($newTemplateData['updated_at']);
            
            // Apply override data
            $newTemplateData = array_merge($newTemplateData, $overrideData);
            
            // Ensure unique title and slug
            if (!isset($overrideData['title'])) {
                $newTemplateData['title'] = $newTemplateData['title'] . ' (Copy)';
            }
            
            $newTemplateData['slug'] = Str::slug($newTemplateData['title']);
            
            // Create new template
            $newTemplate = Template::create($newTemplateData);
            
            // Duplicate blocks and activities
            foreach ($template->blocks as $block) {
                $newBlockData = $block->toArray();
                unset($newBlockData['id']);
                unset($newBlockData['template_id']);
                unset($newBlockData['created_at']);
                unset($newBlockData['updated_at']);
                
                $newBlock = $this->addBlockToTemplate($newTemplate->id, $newBlockData);
                
                // Duplicate activities
                foreach ($block->activities as $activity) {
                    $newActivityData = $activity->toArray();
                    unset($newActivityData['id']);
                    unset($newActivityData['block_id']);
                    unset($newActivityData['created_at']);
                    unset($newActivityData['updated_at']);
                    
                    $this->addActivityToBlock($newBlock->id, $newActivityData);
                }
            }
            
            return $newTemplate->fresh(['blocks.activities']);
        });
    }
    
    /**
     * Reorder blocks within a template
     *
     * @param int $templateId
     * @param array $blockOrders Array of block IDs in the desired order
     * @return bool
     */
    public function reorderBlocks(int $templateId, array $blockOrders): bool
    {
        $template = Template::find($templateId);
        
        if (!$template) {
            return false;
        }
        
        return DB::transaction(function () use ($templateId, $blockOrders) {
            foreach ($blockOrders as $index => $blockId) {
                Block::where('id', $blockId)
                    ->where('template_id', $templateId)
                    ->update(['order' => $index + 1]);
            }
            
            return true;
        });
    }
}
