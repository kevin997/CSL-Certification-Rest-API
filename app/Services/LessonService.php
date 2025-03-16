<?php

namespace App\Services;

use App\Models\LessonContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LessonService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'lesson';
        $this->modelClass = LessonContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'resources' => 'nullable|array',
            'resources.*.title' => 'required|string',
            'resources.*.type' => 'required|string|in:document,video,link,image,audio',
            'resources.*.url' => 'required|string',
            'resources.*.description' => 'nullable|string',
            'learning_objectives' => 'nullable|array',
            'learning_objectives.*' => 'string',
            'estimated_time' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced,expert',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'string',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string',
            'metadata' => 'nullable|array'
        ];
    }
    
    /**
     * Process data before saving to the database
     * Encode arrays to JSON
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        if (isset($data['resources']) && is_array($data['resources'])) {
            $data['resources'] = json_encode($data['resources']);
        }
        
        if (isset($data['learning_objectives']) && is_array($data['learning_objectives'])) {
            $data['learning_objectives'] = json_encode($data['learning_objectives']);
        }
        
        if (isset($data['prerequisites']) && is_array($data['prerequisites'])) {
            $data['prerequisites'] = json_encode($data['prerequisites']);
        }
        
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $data['keywords'] = json_encode($data['keywords']);
        }
        
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        return $data;
    }
    
    /**
     * Process data after retrieving from the database
     * Decode JSON to arrays
     *
     * @param Model $model
     * @return Model
     */
    protected function processDataAfterRetrieve(Model $model): Model
    {
        if (isset($model->resources) && is_string($model->resources)) {
            $model->resources = json_decode($model->resources, true);
        }
        
        if (isset($model->learning_objectives) && is_string($model->learning_objectives)) {
            $model->learning_objectives = json_decode($model->learning_objectives, true);
        }
        
        if (isset($model->prerequisites) && is_string($model->prerequisites)) {
            $model->prerequisites = json_decode($model->prerequisites, true);
        }
        
        if (isset($model->keywords) && is_string($model->keywords)) {
            $model->keywords = json_decode($model->keywords, true);
        }
        
        if (isset($model->metadata) && is_string($model->metadata)) {
            $model->metadata = json_decode($model->metadata, true);
        }
        
        return $model;
    }
    
    /**
     * Get lesson content by ID with decoded data
     *
     * @param int $id
     * @return Model|null
     */
    public function getLesson(int $id): ?Model
    {
        $content = $this->getById($id);
        
        if ($content) {
            return $this->processDataAfterRetrieve($content);
        }
        
        return null;
    }
    
    /**
     * Add a resource to lesson content
     *
     * @param int $id
     * @param array $resource
     * @return Model|null
     */
    public function addResource(int $id, array $resource): ?Model
    {
        $content = $this->getLesson($id);
        
        if (!$content) {
            return null;
        }
        
        $resources = $content->resources ?? [];
        $resources[] = $resource;
        
        return $this->update($id, ['resources' => $resources]);
    }
    
    /**
     * Remove a resource from lesson content
     *
     * @param int $id
     * @param string $resourceTitle
     * @return Model|null
     */
    public function removeResource(int $id, string $resourceTitle): ?Model
    {
        $content = $this->getLesson($id);
        
        if (!$content || !isset($content->resources)) {
            return null;
        }
        
        $resources = array_filter($content->resources, function ($resource) use ($resourceTitle) {
            return $resource['title'] !== $resourceTitle;
        });
        
        return $this->update($id, ['resources' => array_values($resources)]);
    }
    
    /**
     * Add learning objectives to lesson content
     *
     * @param int $id
     * @param array $objectives
     * @return Model|null
     */
    public function addLearningObjectives(int $id, array $objectives): ?Model
    {
        $content = $this->getLesson($id);
        
        if (!$content) {
            return null;
        }
        
        $existingObjectives = $content->learning_objectives ?? [];
        $newObjectives = array_unique(array_merge($existingObjectives, $objectives));
        
        return $this->update($id, ['learning_objectives' => $newObjectives]);
    }
    
    /**
     * Add prerequisites to lesson content
     *
     * @param int $id
     * @param array $prerequisites
     * @return Model|null
     */
    public function addPrerequisites(int $id, array $prerequisites): ?Model
    {
        $content = $this->getLesson($id);
        
        if (!$content) {
            return null;
        }
        
        $existingPrerequisites = $content->prerequisites ?? [];
        $newPrerequisites = array_unique(array_merge($existingPrerequisites, $prerequisites));
        
        return $this->update($id, ['prerequisites' => $newPrerequisites]);
    }
    
    /**
     * Update metadata for lesson content
     *
     * @param int $id
     * @param array $metadata
     * @param bool $merge Whether to merge with existing metadata or replace
     * @return Model|null
     */
    public function updateMetadata(int $id, array $metadata, bool $merge = true): ?Model
    {
        $content = $this->getLesson($id);
        
        if (!$content) {
            return null;
        }
        
        if ($merge && isset($content->metadata)) {
            $metadata = array_merge($content->metadata, $metadata);
        }
        
        return $this->update($id, ['metadata' => $metadata]);
    }
    
    /**
     * Find lessons by difficulty level
     *
     * @param string $difficultyLevel
     * @return array
     */
    public function findByDifficultyLevel(string $difficultyLevel): array
    {
        if (!in_array($difficultyLevel, ['beginner', 'intermediate', 'advanced', 'expert'])) {
            return [];
        }
        
        return LessonContent::where('difficulty_level', $difficultyLevel)
            ->get()
            ->map(function ($content) {
                return $this->processDataAfterRetrieve($content);
            })
            ->toArray();
    }
    
    /**
     * Find lessons by prerequisites
     *
     * @param array $prerequisites
     * @return array
     */
    public function findByPrerequisites(array $prerequisites): array
    {
        $results = [];
        $allContent = LessonContent::all();
        
        foreach ($allContent as $content) {
            $contentPrerequisites = json_decode($content->prerequisites ?? '[]', true);
            
            if (!empty(array_intersect($prerequisites, $contentPrerequisites))) {
                $results[] = $this->processDataAfterRetrieve($content);
            }
        }
        
        return $results;
    }
    
    /**
     * Search for lessons by keywords
     *
     * @param array $keywords
     * @return array
     */
    public function searchByKeywords(array $keywords): array
    {
        $results = [];
        $allContent = LessonContent::all();
        
        foreach ($allContent as $content) {
            $contentKeywords = json_decode($content->keywords ?? '[]', true);
            
            if (!empty(array_intersect($keywords, $contentKeywords))) {
                $results[] = $this->processDataAfterRetrieve($content);
            }
        }
        
        return $results;
    }
    
    /**
     * Get related lessons based on keywords and prerequisites
     *
     * @param int $id
     * @return array
     */
    public function getRelatedLessons(int $id): array
    {
        $lesson = $this->getLesson($id);
        
        if (!$lesson) {
            return [];
        }
        
        $keywords = $lesson->keywords ?? [];
        $prerequisites = $lesson->prerequisites ?? [];
        
        // Find lessons with similar keywords or prerequisites
        $relatedByKeywords = empty($keywords) ? [] : $this->searchByKeywords($keywords);
        $relatedByPrerequisites = empty($prerequisites) ? [] : $this->findByPrerequisites($prerequisites);
        
        // Merge results and remove the current lesson
        $relatedLessons = array_merge($relatedByKeywords, $relatedByPrerequisites);
        $relatedLessons = array_filter($relatedLessons, function ($relatedLesson) use ($id) {
            return $relatedLesson['id'] !== $id;
        });
        
        // Remove duplicates
        $uniqueLessons = [];
        foreach ($relatedLessons as $lesson) {
            $uniqueLessons[$lesson['id']] = $lesson;
        }
        
        return array_values($uniqueLessons);
    }
}
