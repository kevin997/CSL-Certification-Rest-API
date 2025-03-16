<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Base Content Service class that all content-specific services will extend
 */
abstract class ContentService extends Service
{
    /**
     * The activity type this service handles
     *
     * @var string
     */
    protected string $activityType;
    
    /**
     * The model class this service handles
     *
     * @var string
     */
    protected string $modelClass;
    
    /**
     * Validation rules for content creation/update
     *
     * @var array
     */
    protected array $validationRules = [];
    
    /**
     * Validation messages for content creation/update
     *
     * @var array
     */
    protected array $validationMessages = [];
    
    /**
     * Get content by ID
     *
     * @param int $id
     * @return Model|null
     */
    public function getById(int $id): ?Model
    {
        return $this->modelClass::find($id);
    }
    
    /**
     * Get content by activity ID
     *
     * @param int $activityId
     * @return Model|null
     */
    public function getByActivityId(int $activityId): ?Model
    {
        return $this->modelClass::where('activity_id', $activityId)->first();
    }
    
    /**
     * Create new content
     *
     * @param array $data
     * @return Model
     * @throws ValidationException
     */
    public function create(array $data): Model
    {
        // Validate data
        $validator = Validator::make($data, $this->validationRules, $this->validationMessages);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        // Verify activity exists and is of the correct type
        $activity = Activity::find($data['activity_id']);
        
        if (!$activity) {
            throw ValidationException::withMessages([
                'activity_id' => ['Activity not found.']
            ]);
        }
        
        if ($activity->type !== $this->activityType) {
            throw ValidationException::withMessages([
                'activity_id' => ["Activity is not of type '{$this->activityType}'."]
            ]);
        }
        
        // Process data before saving if needed
        $processedData = $this->processDataBeforeSave($data);
        
        // Create content
        return $this->modelClass::create($processedData);
    }
    
    /**
     * Update existing content
     *
     * @param int $id
     * @param array $data
     * @return Model|null
     * @throws ValidationException
     */
    public function update(int $id, array $data): ?Model
    {
        $content = $this->getById($id);
        
        if (!$content) {
            return null;
        }
        
        // Validate data
        $validator = Validator::make($data, $this->validationRules, $this->validationMessages);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        // Process data before saving if needed
        $processedData = $this->processDataBeforeSave($data);
        
        // Update content
        $content->update($processedData);
        
        return $content->fresh();
    }
    
    /**
     * Delete content
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $content = $this->getById($id);
        
        if (!$content) {
            return false;
        }
        
        return $content->delete();
    }
    
    /**
     * Process data before saving to the database
     * Override this method in child classes if needed
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        return $data;
    }
}
