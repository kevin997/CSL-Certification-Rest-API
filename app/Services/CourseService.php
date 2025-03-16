<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Activity;
use App\Models\Template;
use App\Models\Block;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseService extends Service
{
    /**
     * Get all courses with optional filtering
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllCourses(array $filters = []): Collection
    {
        $query = Course::query();
        
        // Apply filters
        if (isset($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
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
     * Get a course by ID with its sections and activities
     *
     * @param int $id
     * @return Course|null
     */
    public function getCourseById(int $id): ?Course
    {
        return Course::with(['sections.activities'])->find($id);
    }
    
    /**
     * Create a new course
     *
     * @param array $data
     * @return Course
     */
    public function createCourse(array $data): Course
    {
        // Generate slug if not provided
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        
        return DB::transaction(function () use ($data) {
            $course = Course::create($data);
            
            // Create sections if provided
            if (isset($data['sections']) && is_array($data['sections'])) {
                foreach ($data['sections'] as $sectionData) {
                    $this->addSectionToCourse($course->id, $sectionData);
                }
            }
            
            return $course->fresh(['sections.activities']);
        });
    }
    
    /**
     * Create a course from a template
     *
     * @param int $templateId
     * @param array $courseData
     * @return Course|null
     */
    public function createCourseFromTemplate(int $templateId, array $courseData): ?Course
    {
        $template = Template::with(['blocks.activities'])->find($templateId);
        
        if (!$template) {
            return null;
        }
        
        return DB::transaction(function () use ($template, $courseData) {
            // Create the course
            $course = $this->createCourse($courseData);
            
            // Create sections from template blocks
            foreach ($template->blocks as $block) {
                $sectionData = [
                    'title' => $block->title,
                    'description' => $block->description,
                    'order' => $block->order,
                    'is_visible' => true
                ];
                
                $section = $this->addSectionToCourse($course->id, $sectionData);
                
                // Create activities from template block activities
                foreach ($block->activities as $activity) {
                    $activityData = [
                        'title' => $activity->title,
                        'description' => $activity->description,
                        'type' => $activity->type,
                        'order' => $activity->order,
                        'is_required' => $activity->is_required,
                        'estimated_time' => $activity->estimated_time,
                        'section_id' => $section->id
                    ];
                    
                    $this->addActivityToSection($section->id, $activityData);
                }
            }
            
            return $course->fresh(['sections.activities']);
        });
    }
    
    /**
     * Update an existing course
     *
     * @param int $id
     * @param array $data
     * @return Course|null
     */
    public function updateCourse(int $id, array $data): ?Course
    {
        $course = Course::find($id);
        
        if (!$course) {
            return null;
        }
        
        // Update slug if title changed
        if (isset($data['title']) && $course->title !== $data['title'] && (!isset($data['slug']) || empty($data['slug']))) {
            $data['slug'] = Str::slug($data['title']);
        }
        
        $course->update($data);
        
        return $course->fresh(['sections.activities']);
    }
    
    /**
     * Delete a course
     *
     * @param int $id
     * @return bool
     */
    public function deleteCourse(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $course = Course::find($id);
            
            if (!$course) {
                return false;
            }
            
            // Delete all sections and activities
            foreach ($course->sections as $section) {
                Activity::where('section_id', $section->id)->delete();
            }
            
            CourseSection::where('course_id', $id)->delete();
            
            return $course->delete();
        });
    }
    
    /**
     * Add a section to a course
     *
     * @param int $courseId
     * @param array $sectionData
     * @return CourseSection
     */
    public function addSectionToCourse(int $courseId, array $sectionData): CourseSection
    {
        // Set course ID
        $sectionData['course_id'] = $courseId;
        
        // Set order if not provided
        if (!isset($sectionData['order'])) {
            $maxOrder = CourseSection::where('course_id', $courseId)->max('order') ?? 0;
            $sectionData['order'] = $maxOrder + 1;
        }
        
        $section = CourseSection::create($sectionData);
        
        // Create activities if provided
        if (isset($sectionData['activities']) && is_array($sectionData['activities'])) {
            foreach ($sectionData['activities'] as $activityData) {
                $this->addActivityToSection($section->id, $activityData);
            }
        }
        
        return $section->fresh(['activities']);
    }
    
    /**
     * Add an activity to a section
     *
     * @param int $sectionId
     * @param array $activityData
     * @return Activity
     */
    public function addActivityToSection(int $sectionId, array $activityData): Activity
    {
        // Set section ID
        $activityData['section_id'] = $sectionId;
        
        // Set order if not provided
        if (!isset($activityData['order'])) {
            $maxOrder = Activity::where('section_id', $sectionId)->max('order') ?? 0;
            $activityData['order'] = $maxOrder + 1;
        }
        
        return Activity::create($activityData);
    }
    
    /**
     * Publish a course
     *
     * @param int $id
     * @return Course|null
     */
    public function publishCourse(int $id): ?Course
    {
        $course = Course::find($id);
        
        if (!$course) {
            return null;
        }
        
        $course->update([
            'status' => 'published',
            'published_at' => now()
        ]);
        
        return $course->fresh();
    }
    
    /**
     * Archive a course
     *
     * @param int $id
     * @return Course|null
     */
    public function archiveCourse(int $id): ?Course
    {
        $course = Course::find($id);
        
        if (!$course) {
            return null;
        }
        
        $course->update([
            'status' => 'archived'
        ]);
        
        return $course->fresh();
    }
    
    /**
     * Duplicate a course
     *
     * @param int $id
     * @param array $overrideData
     * @return Course|null
     */
    public function duplicateCourse(int $id, array $overrideData = []): ?Course
    {
        $course = $this->getCourseById($id);
        
        if (!$course) {
            return null;
        }
        
        return DB::transaction(function () use ($course, $overrideData) {
            // Create new course with original data
            $newCourseData = $course->toArray();
            
            // Remove ID and timestamps
            unset($newCourseData['id']);
            unset($newCourseData['created_at']);
            unset($newCourseData['updated_at']);
            unset($newCourseData['published_at']);
            
            // Set status to draft
            $newCourseData['status'] = 'draft';
            
            // Apply override data
            $newCourseData = array_merge($newCourseData, $overrideData);
            
            // Ensure unique title and slug
            if (!isset($overrideData['title'])) {
                $newCourseData['title'] = $newCourseData['title'] . ' (Copy)';
            }
            
            $newCourseData['slug'] = Str::slug($newCourseData['title']);
            
            // Create new course
            $newCourse = Course::create($newCourseData);
            
            // Duplicate sections and activities
            foreach ($course->sections as $section) {
                $newSectionData = $section->toArray();
                unset($newSectionData['id']);
                unset($newSectionData['course_id']);
                unset($newSectionData['created_at']);
                unset($newSectionData['updated_at']);
                
                $newSection = $this->addSectionToCourse($newCourse->id, $newSectionData);
                
                // Duplicate activities
                foreach ($section->activities as $activity) {
                    $newActivityData = $activity->toArray();
                    unset($newActivityData['id']);
                    unset($newActivityData['section_id']);
                    unset($newActivityData['created_at']);
                    unset($newActivityData['updated_at']);
                    
                    $this->addActivityToSection($newSection->id, $newActivityData);
                }
            }
            
            return $newCourse->fresh(['sections.activities']);
        });
    }
    
    /**
     * Reorder sections within a course
     *
     * @param int $courseId
     * @param array $sectionOrders Array of section IDs in the desired order
     * @return bool
     */
    public function reorderSections(int $courseId, array $sectionOrders): bool
    {
        $course = Course::find($courseId);
        
        if (!$course) {
            return false;
        }
        
        return DB::transaction(function () use ($courseId, $sectionOrders) {
            foreach ($sectionOrders as $index => $sectionId) {
                CourseSection::where('id', $sectionId)
                    ->where('course_id', $courseId)
                    ->update(['order' => $index + 1]);
            }
            
            return true;
        });
    }
}
